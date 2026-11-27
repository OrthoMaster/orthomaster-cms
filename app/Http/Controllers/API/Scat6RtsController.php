<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ScatAssessment;
use App\Models\ScatPatient;
use App\Models\ScatRts;
use App\Support\Scat6\Scat6Content;
use App\Support\Scat6\Scat6PdfFooter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * SCAT6 › Retour au sport (médecins seulement — middleware `doctor`).
 *
 * Le patient n'a pas de compte : le médecin fait avancer les étapes lors d'une consultation ou d'un
 * appel. Les règles (24 h par étape, conditions avant l'étape 4, délai de la fédération) sont des
 * avertissements affichés par l'application : le médecin peut passer outre, c'est sa décision.
 * Le serveur vérifie seulement la cohérence des données et la propriété.
 */
class Scat6RtsController extends Controller
{
    private const TYPES = ['contact' => ScatRts::STEP_CONTACT, 'competition' => ScatRts::STEP_COMPETITION];

    /**
     * Un suivi, avec le patient et ses examens signés depuis le choc (re-test avant l'étape 4).
     * GET /scat/rts/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $rts = $this->find($request, $id);

        return response()->json(['success' => true, 'data' => $this->detail($rts)]);
    }

    /**
     * Ouverture à la main (commotion diagnostiquée hors application, ou suivi précédent clos).
     * POST /scat/rts
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer'],
            'injury_at'  => ['required', 'date', 'before_or_equal:today'],
        ]);
        $patient = ScatPatient::where('user_id', $request->user()->id)->findOrFail($data['patient_id']);

        $active = ScatRts::where('scat_patient_id', $patient->id)->where('status', ScatRts::STATUS_ACTIVE)->first();
        if ($active) {
            return response()->json(['success' => false, 'error' => 'Un suivi de reprise est déjà en cours.', 'rts_id' => $active->id], 409);
        }

        $rts = ScatRts::create([
            'scat_patient_id' => $patient->id,
            'user_id'         => $request->user()->id,
            'injury_at'       => Carbon::parse($data['injury_at'])->toDateString(),
            'current_step'    => 1,
            'status'          => ScatRts::STATUS_ACTIVE,
            'steps'           => ['1' => ['started_at' => now()->toIso8601String(), 'done_at' => null, 'forced' => false, 'note' => null]],
            'setbacks'        => [],
        ]);

        return response()->json(['success' => true, 'data' => $this->detail($rts)], 201);
    }

    /**
     * Enregistre l'état calculé par l'application (étape validée, retour en arrière, notes, clôture).
     * Étape 6 atteinte → suivi terminé (retour à la compétition autorisé par le médecin).
     * PUT /scat/rts/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $rts = $this->find($request, $id);
        if ($rts->status !== ScatRts::STATUS_ACTIVE) {
            return response()->json(['success' => false, 'error' => 'Ce suivi est terminé : il ne peut plus être modifié.'], 409);
        }

        $data = $request->validate([
            'current_step'         => ['required', 'integer', 'between:1,6'],
            'injury_at'            => ['sometimes', 'date', 'before_or_equal:today'],
            'steps'                => ['required', 'array'],
            'steps.*.started_at'   => ['nullable', 'date'],
            'steps.*.done_at'      => ['nullable', 'date'],
            'steps.*.forced'       => ['nullable', 'boolean'],
            'steps.*.note'         => ['nullable', 'string', 'max:500'],
            'setbacks'             => ['nullable', 'array', 'max:50'],
            'setbacks.*.at'        => ['required', 'date'],
            'setbacks.*.from'      => ['required', 'integer', 'between:1,6'],
            'setbacks.*.to'        => ['required', 'integer', 'between:1,6'],
            'setbacks.*.note'      => ['nullable', 'string', 'max:500'],
            'notes'                => ['nullable', 'string', 'max:5000'],
            'close'                => ['sometimes', 'boolean'],
        ]);

        // Clés « 1 » → « 6 » seulement ; l'étape en cours doit avoir une date de début.
        $steps = collect($data['steps'])->only(array_map('strval', range(1, 6)))->all();
        $current = (int) $data['current_step'];
        if (empty($steps[(string) $current]['started_at'])) {
            return response()->json(['success' => false, 'error' => 'Étape en cours sans date de début.'], 422);
        }

        $completed = $current === ScatRts::STEP_COMPETITION;
        $rts->update([
            'current_step' => $current,
            'injury_at'    => isset($data['injury_at']) ? Carbon::parse($data['injury_at'])->toDateString() : $rts->injury_at,
            'steps'        => $steps,
            'setbacks'     => $data['setbacks'] ?? [],
            'notes'        => $data['notes'] ?? null,
            'status'       => $completed ? ScatRts::STATUS_COMPLETED : (($data['close'] ?? false) ? ScatRts::STATUS_CLOSED : ScatRts::STATUS_ACTIVE),
            'completed_at' => $completed ? Carbon::parse($steps['6']['started_at']) : null,
        ]);

        return response()->json(['success' => true, 'data' => $this->detail($rts->fresh())]);
    }

    /**
     * Suppression d'un suivi ouvert par erreur.
     * DELETE /scat/rts/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->find($request, $id)->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Certificat médical de reprise (PDF à imprimer, signer et tamponner).
     * contact : entraînement avec contact (étape 5) ; competition : compétition (étape 6).
     * Les champs d'identité restent à remplir à la main : l'application ne stocke qu'un nom libre.
     * GET /scat/rts/{id}/certificate/{type}
     */
    public function certificate(Request $request, int $id, string $type)
    {
        $rts = $this->find($request, $id);
        abort_unless(isset(self::TYPES[$type]), 404);
        if ($rts->current_step < self::TYPES[$type]) {
            return response()->json([
                'success' => false,
                'error'   => $type === 'contact'
                    ? 'Le certificat est disponible une fois l’étape 5 (contact) autorisée.'
                    : 'Le certificat est disponible une fois le retour à la compétition autorisé.',
            ], 409);
        }

        $patient = $rts->patient;
        $user = $request->user();
        $age = Scat6Content::ageFromYear($patient->birth_year);

        $pdf = Pdf::loadView('pdf.scat6_rts_certificate', [
            'rts'        => $rts,
            'patient'    => $patient,
            'type'       => $type,
            'doctor'     => trim(($user->first_name ?? '') . ' ' . ($user->name ?? '')),
            'grantedAt'  => Carbon::parse($rts->stepStartedAt(self::TYPES[$type]) ?? now()),
            'routing'    => Scat6Content::CERTIFICATE_ROUTING[$patient->sport] ?? Scat6Content::CERTIFICATE_ROUTING['general'],
            'minDays'    => Scat6Content::minCompetitionDays($patient->sport, $age),
            'steps'      => Scat6Content::RTS_STEPS,
        ])->setPaper('a4');

        // Pied de page sur chaque page : document, patient, date d'édition, « Page X / Y ».
        Scat6PdfFooter::apply(
            $pdf,
            'Certificat de reprise · ' . $patient->name . ' · édité le ' . now()->timezone('Europe/Paris')->format('d/m/Y'),
            16
        );

        $file = 'Certificat-reprise-' . $type . '-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $patient->name) . '.pdf';

        return $pdf->download($file);
    }

    /* ===================== OUTILS ===================== */

    private function find(Request $request, int $id): ScatRts
    {
        return ScatRts::where('user_id', $request->user()->id)->whereHas('patient')->with('patient')->findOrFail($id);
    }

    private function detail(ScatRts $rts): array
    {
        $patient = $rts->patient;

        // Examens signés depuis le choc : l'application avertit s'il n'y a eu aucun re-test avant l'étape 4.
        $exams = ScatAssessment::where('scat_patient_id', $patient->id)
            ->where('status', ScatAssessment::STATUS_COMPLETED)
            ->where('exam_kind', '!=', 'baseline')
            ->where('completed_at', '>=', Carbon::parse($rts->injury_at)->startOfDay())
            ->orderBy('completed_at')
            ->get(['uuid', 'exam_kind', 'diagnosis', 'completed_at']);

        return [
            ...self::row($rts, $patient),
            'exams_since_injury' => $exams->map(fn ($a) => [
                'uuid'         => $a->uuid,
                'exam_kind'    => $a->exam_kind,
                'diagnosis'    => $a->diagnosis,
                'completed_at' => $a->completed_at?->toIso8601String(),
            ])->values(),
        ];
    }

    /** Ligne d'un suivi (fiche patient, écran de suivi). */
    public static function row(ScatRts $rts, ScatPatient $patient): array
    {
        $age = Scat6Content::ageFromYear($patient->birth_year);
        $minDays = Scat6Content::minCompetitionDays($patient->sport, $age);
        $injury = Carbon::parse($rts->injury_at);

        return [
            'id'                   => (int) $rts->id,
            'patient_id'           => (int) $patient->id,
            'assessment_uuid'      => $rts->assessment_uuid,
            'injury_at'            => $injury->toDateString(),
            'current_step'         => (int) $rts->current_step,
            'status'               => $rts->status,
            'steps'                => (object) ($rts->steps ?? []),
            'setbacks'             => $rts->setbacks ?? [],
            'notes'                => $rts->notes,
            'min_competition_days' => $minDays,
            'earliest_competition' => $minDays !== null ? $injury->copy()->addDays($minDays)->toDateString() : null,
            'completed_at'         => $rts->completed_at?->toIso8601String(),
            'created_at'           => $rts->created_at?->toIso8601String(),
            'updated_at'           => $rts->updated_at?->toIso8601String(),
        ];
    }
}
