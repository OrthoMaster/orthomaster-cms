<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ScatAssessment;
use App\Models\ScatPatient;
use App\Support\Scat6\Scat6Content;
use App\Support\Scat6\Scat6Scoring;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * SCAT6 (Outils › SCAT6, médecins seulement — middleware `doctor`).
 * Le médecin gère ses patients (nom libre, année de naissance, sport) et leurs examens hors terrain.
 * Un médecin ne voit jamais les patients d'un autre : toutes les requêtes filtrent sur user_id.
 */
class Scat6Controller extends Controller
{
    /** Le SCAT6 s'applique à partir de 13 ans (R5) ; avant, Child SCAT6. */
    private const MIN_AGE = 13;

    /** Taille maximale du JSON d'un examen (garde-fou contre un envoi anormal). */
    private const MAX_DATA_BYTES = 64 * 1024;

    /* ===================== PATIENTS ===================== */

    /**
     * Patients du médecin, activité la plus récente en tête, avec leur dernier examen.
     * GET /scat/patients?q=
     */
    public function patients(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $userId = $request->user()->id;

        $patients = DB::table('scat_patients as p')
            ->where('p.user_id', $userId)
            ->whereNull('p.deleted_at')
            ->when($request->filled('q'), fn ($q) => $q->where('p.name', 'like', '%' . addcslashes($request->q, '%_\\') . '%'))
            ->select('p.*')
            ->selectSub(
                DB::table('scat_assessments as a')
                    ->whereColumn('a.scat_patient_id', 'p.id')
                    ->whereNull('a.deleted_at')
                    ->selectRaw('MAX(a.updated_at)'),
                'last_activity_at'
            )
            ->orderByRaw('COALESCE(last_activity_at, p.updated_at) DESC')
            ->get();

        $assessments = DB::table('scat_assessments')
            ->whereIn('scat_patient_id', $patients->pluck('id'))
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('scat_patient_id');

        return response()->json([
            'success' => true,
            'data'    => $patients->map(fn ($p) => $this->patientRow($p, $assessments->get($p->id, collect())))->values(),
        ]);
    }

    /**
     * Fiche patient et tous ses examens (plus récent en tête).
     * GET /scat/patients/{id}
     */
    public function patient(Request $request, int $id): JsonResponse
    {
        $patient = $this->findPatient($request, $id);
        $assessments = $patient->assessments()->orderByDesc('started_at')->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'data'    => [
                ...$this->patientRow($patient, $assessments),
                'assessments' => $assessments->map(fn ($a) => $this->assessmentRow($a, $patient))->values(),
            ],
        ]);
    }

    /** POST /scat/patients */
    public function storePatient(Request $request): JsonResponse
    {
        $data = $this->validatePatient($request);
        $patient = ScatPatient::create([...$data, 'user_id' => $request->user()->id]);

        return response()->json(['success' => true, 'data' => $this->patientRow($patient, collect())], 201);
    }

    /** PUT /scat/patients/{id} */
    public function updatePatient(Request $request, int $id): JsonResponse
    {
        $patient = $this->findPatient($request, $id);
        $patient->update($this->validatePatient($request));

        return response()->json(['success' => true, 'data' => $this->patientRow($patient, $patient->assessments()->get())]);
    }

    /**
     * Suppression du patient et de ses examens (suppression logique).
     * DELETE /scat/patients/{id}
     */
    public function destroyPatient(Request $request, int $id): JsonResponse
    {
        $patient = $this->findPatient($request, $id);

        DB::transaction(function () use ($patient) {
            $patient->assessments()->delete();
            $patient->delete();
        });

        return response()->json(['success' => true]);
    }

    /* ===================== EXAMENS ===================== */

    /**
     * Tous les examens du médecin (onglet Examens) : brouillons puis signés, plus récents en tête.
     * GET /scat/assessments?status=draft|completed
     */
    public function assessments(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::in([ScatAssessment::STATUS_DRAFT, ScatAssessment::STATUS_COMPLETED])]]);

        $rows = ScatAssessment::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('patient')
            ->with('patient')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByRaw("CASE WHEN status = 'draft' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->limit(300)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $rows->map(fn ($a) => $this->assessmentRow($a, $a->patient))->values(),
        ]);
    }

    /**
     * Un examen complet (reprise d'un brouillon ou lecture), avec la dernière baseline signée du patient.
     * GET /scat/assessments/{uuid}
     */
    public function assessment(Request $request, string $uuid): JsonResponse
    {
        $assessment = $this->findAssessment($request, $uuid);

        return response()->json(['success' => true, 'data' => $this->assessmentDetail($assessment)]);
    }

    /**
     * Sauvegarde automatique (création au premier appel). Un examen signé n'est plus modifiable.
     * PUT /scat/assessments/{uuid}
     */
    public function saveAssessment(Request $request, string $uuid): JsonResponse
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            return response()->json(['success' => false, 'error' => 'Identifiant invalide.'], 422);
        }

        $data = $request->validate([
            'patient_id' => ['required', 'integer'],
            'exam_kind'  => ['required', Rule::in(array_keys(Scat6Content::EXAM_KINDS))],
            'injury_at'  => ['nullable', 'date'],
            'word_list'  => ['required', Rule::in(Scat6Content::WORD_LIST_IDS)],
            'step'       => ['nullable', 'string', 'max:30'],
            'started_at' => ['nullable', 'date'],
            'data'       => ['required', 'array'],
        ]);

        if (strlen(json_encode($data['data'])) > self::MAX_DATA_BYTES) {
            return response()->json(['success' => false, 'error' => 'Examen trop volumineux.'], 422);
        }

        $patient = $this->findPatient($request, (int) $data['patient_id']);
        $age = Scat6Content::ageFromYear($patient->birth_year);
        if ($age !== null && $age < self::MIN_AGE) {
            return response()->json([
                'success' => false,
                'error'   => 'Le SCAT6 ne s’applique pas avant 13 ans (Child SCAT6).',
            ], 422);
        }

        $existing = ScatAssessment::withTrashed()->where('uuid', strtolower($uuid))->first();
        if ($existing && ($existing->user_id !== $request->user()->id || $existing->trashed())) {
            return response()->json(['success' => false, 'error' => 'Examen introuvable.'], 404);
        }
        if ($existing?->isCompleted()) {
            return response()->json(['success' => false, 'error' => 'Examen déjà signé : il ne peut plus être modifié.'], 409);
        }

        $fields = [
            'scat_patient_id' => $patient->id,
            'exam_kind'       => $data['exam_kind'],
            'injury_at'       => $data['exam_kind'] === 'baseline' ? null : ($data['injury_at'] ?? null),
            'word_list'       => $data['word_list'],
            'step'            => $data['step'] ?? null,
            'data'            => $data['data'],
        ];

        $assessment = $existing
            ? tap($existing)->update($fields)
            : ScatAssessment::create([
                ...$fields,
                'uuid'            => strtolower($uuid),
                'user_id'         => $request->user()->id,
                'status'          => ScatAssessment::STATUS_DRAFT,
                'content_version' => Scat6Content::CONTENT_VERSION,
                'started_at'      => $data['started_at'] ?? now(),
            ]);

        return response()->json(['success' => true, 'data' => $this->assessmentRow($assessment, $patient)]);
    }

    /**
     * Signature : scores recalculés par le serveur, décision du médecin, examen figé.
     * POST /scat/assessments/{uuid}/complete
     */
    public function completeAssessment(Request $request, string $uuid): JsonResponse
    {
        $assessment = $this->findAssessment($request, $uuid);
        if ($assessment->isCompleted()) {
            return response()->json(['success' => false, 'error' => 'Examen déjà signé.'], 409);
        }

        $baseline = $assessment->exam_kind === 'baseline';
        $data = $request->validate([
            'diagnosis' => [$baseline ? 'nullable' : 'required', Rule::in(array_keys(Scat6Content::DIAGNOSIS))],
            'neuro'     => ['nullable', Rule::in(array_keys(Scat6Content::NEURO))],
            'notes'     => ['nullable', 'string', 'max:5000'],
            'attested'  => ['accepted'],
        ]);

        $user = $request->user();
        $content = $assessment->data ?? [];

        $assessment->update([
            'status'       => ScatAssessment::STATUS_COMPLETED,
            'step'         => 'resultat',
            'summary'      => Scat6Scoring::summarize($content['answers'] ?? [], $content['tests'] ?? []),
            'diagnosis'    => $baseline ? null : $data['diagnosis'],
            'neuro'        => $baseline ? null : ($data['neuro'] ?? null),
            'notes'        => $data['notes'] ?? null,
            'signed_by'    => trim(($user->first_name ?? '') . ' ' . ($user->name ?? '')),
            'completed_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => $this->assessmentDetail($assessment->fresh())]);
    }

    /**
     * Abandon d'un brouillon (un examen signé ne se supprime pas).
     * DELETE /scat/assessments/{uuid}
     */
    public function destroyAssessment(Request $request, string $uuid): JsonResponse
    {
        $assessment = $this->findAssessment($request, $uuid);
        if ($assessment->isCompleted()) {
            return response()->json(['success' => false, 'error' => 'Un examen signé ne peut pas être supprimé.'], 409);
        }
        $assessment->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Rapport PDF d'un examen signé.
     * GET /scat/assessments/{uuid}/pdf
     */
    public function pdf(Request $request, string $uuid)
    {
        $assessment = $this->findAssessment($request, $uuid);
        if (! $assessment->isCompleted()) {
            return response()->json(['success' => false, 'error' => 'Le rapport est disponible une fois l’examen signé.'], 409);
        }

        $patient = $assessment->patient;
        $baseline = $this->baselineFor($assessment);
        $age = Scat6Content::ageFromYear($patient->birth_year);

        $pdf = Pdf::loadView('pdf.scat6_report', [
            'assessment' => $assessment,
            'patient'    => $patient,
            'age'        => $age,
            'baseline'   => $baseline,
            'rows'       => $this->compareRows($assessment->summary ?? [], $baseline?->summary),
            'returnLines' => Scat6Content::returnLines($patient->sport, $age),
        ])->setPaper('a4');

        $file = 'SCAT6-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $patient->name) . '-' . $assessment->completed_at->format('Y-m-d') . '.pdf';

        return $pdf->download($file);
    }

    /* ===================== OUTILS ===================== */

    private function validatePatient(Request $request): array
    {
        $year = (int) now()->year;

        return $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'birth_year' => ['required', 'integer', 'min:' . ($year - 100), 'max:' . $year],
            'sport'      => ['required', Rule::in(array_keys(Scat6Content::SPORTS))],
            'notes'      => ['nullable', 'string', 'max:2000'],
        ], [
            'birth_year.min' => 'Année de naissance invalide.',
            'birth_year.max' => 'Année de naissance invalide.',
        ]);
    }

    private function findPatient(Request $request, int $id): ScatPatient
    {
        return ScatPatient::where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function findAssessment(Request $request, string $uuid): ScatAssessment
    {
        return ScatAssessment::where('user_id', $request->user()->id)
            ->where('uuid', strtolower($uuid))
            ->whereHas('patient')
            ->with('patient')
            ->firstOrFail();
    }

    /** Dernière baseline signée du patient, hors l'examen lui-même et signée avant lui. */
    private function baselineFor(ScatAssessment $assessment): ?ScatAssessment
    {
        return ScatAssessment::where('scat_patient_id', $assessment->scat_patient_id)
            ->where('exam_kind', 'baseline')
            ->where('status', ScatAssessment::STATUS_COMPLETED)
            ->where('id', '!=', $assessment->id)
            ->when($assessment->completed_at, fn ($q) => $q->where('completed_at', '<=', $assessment->completed_at))
            ->orderByDesc('completed_at')
            ->first();
    }

    /** Tableau « Actuel / Baseline / Écart » (aucun seuil, aucun verdict). */
    private function compareRows(array $current, ?array $baseline): array
    {
        $rows = [];
        foreach (Scat6Scoring::ROWS as $key => $row) {
            $c = $current[$key] ?? null;
            $b = $baseline[$key] ?? null;
            $rows[] = [
                ...$row,
                'key'      => $key,
                'current'  => $c,
                'baseline' => $b,
                'delta'    => $c !== null && $b !== null ? round($c - $b, 1) : null,
            ];
        }

        return $rows;
    }

    private function patientRow(object $patient, $assessments): array
    {
        $last = $assessments->sortByDesc('updated_at')->first();

        return [
            'id'                => (int) $patient->id,
            'name'              => $patient->name,
            'birth_year'        => (int) $patient->birth_year,
            'age'               => Scat6Content::ageFromYear((int) $patient->birth_year),
            'sport'             => $patient->sport,
            'notes'             => $patient->notes,
            'created_at'        => $this->iso($patient->created_at),
            'assessments_count' => $assessments->count(),
            'has_baseline'      => $assessments->contains(fn ($a) => $a->exam_kind === 'baseline' && $a->status === ScatAssessment::STATUS_COMPLETED),
            'drafts_count'      => $assessments->where('status', ScatAssessment::STATUS_DRAFT)->count(),
            'last_assessment'   => $last ? [
                'uuid'       => $last->uuid,
                'exam_kind'  => $last->exam_kind,
                'status'     => $last->status,
                'diagnosis'  => $last->diagnosis,
                'updated_at' => $this->iso($last->updated_at),
            ] : null,
        ];
    }

    private function assessmentRow(ScatAssessment $a, ?ScatPatient $patient): array
    {
        return [
            'uuid'         => $a->uuid,
            'patient_id'   => (int) $a->scat_patient_id,
            'patient_name' => $patient?->name,
            'patient_sport' => $patient?->sport,
            'exam_kind'    => $a->exam_kind,
            'status'       => $a->status,
            'step'         => $a->step,
            'word_list'    => $a->word_list,
            'diagnosis'    => $a->diagnosis,
            'neuro'        => $a->neuro,
            'summary'      => $a->summary,
            'signed_by'    => $a->signed_by,
            'injury_at'    => $this->iso($a->injury_at),
            'started_at'   => $this->iso($a->started_at),
            'completed_at' => $this->iso($a->completed_at),
            'updated_at'   => $this->iso($a->updated_at),
        ];
    }

    private function assessmentDetail(ScatAssessment $a): array
    {
        $patient = $a->patient;
        $baseline = $this->baselineFor($a);

        return [
            ...$this->assessmentRow($a, $patient),
            'content_version' => $a->content_version,
            'data'            => $a->data ?? (object) [],
            'notes'           => $a->notes,
            'patient'         => $this->patientRow($patient, $patient->assessments()->get()),
            'baseline'        => $baseline ? $this->assessmentRow($baseline, $patient) : null,
        ];
    }

    private function iso($date): ?string
    {
        if (! $date) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($date)->toIso8601String();
    }
}
