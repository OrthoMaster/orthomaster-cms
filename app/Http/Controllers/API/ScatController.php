<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HealthcareProfessional;
use App\Models\ScatHandoffCode;
use App\Models\User;
use App\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * SCAT6 integration.
 *
 *   App OrthoMaster  →  POST /api/scat/handoff                       (auth:sanctum)
 *   scat-server      →  POST /api/partner/scat/verify-credentials    (X-Partner-Key)
 *   scat-server      →  POST /api/partner/scat/handoff/redeem        (X-Partner-Key)
 *   scat-server      →  POST /api/partner/scat/email-lookup          (X-Partner-Key)
 *   scat-server      →  GET  /api/partner/scat/rpps/{rpps}            (X-Partner-Key)
 */
class ScatController extends Controller
{
    /**
     * Called by the OrthoMaster app for the signed-in user.
     * Returns the URL to open; SCAT6 signs the doctor in without a password.
     */
    public function createHandoff(Request $request)
    {
        $user = $request->user();

        if (! self::isDoctor($user)) {
            return response()->json([
                'error' => "SCAT6 est réservé aux médecins. Créez un compte joueur directement dans l'application SCAT6.",
            ], 403);
        }

        $code = ScatHandoffCode::issueFor($user);

        return response()->json([
            'status' => 'success',
            'data' => [
                'url' => config('scat.handoff_url').'?'.http_build_query(['code' => $code]),
                'expires_in' => config('scat.handoff_ttl', 60),
            ],
        ]);
    }

    /** scat-server: check OrthoMaster e-mail + password (no token created, no device, no tracking). */
    public function verifyCredentials(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => "Ces informations d'identification ne correspondent pas à nos enregistrements.",
            ], 422);
        }

        if ((int) $user->status !== Status::ACTIVE->value) {
            return response()->json(['error' => "Ce compte n'est pas actif."], 422);
        }

        Log::channel('api')->info('SCAT verify-credentials', ['user_id' => $user->id]);

        return response()->json(['status' => 'success', 'data' => ['user' => self::toScatUser($user)]]);
    }

    /** scat-server: exchange a handoff code (single use). */
    public function redeemHandoff(Request $request)
    {
        $request->validate(['code' => 'required|string|max:128']);

        return DB::transaction(function () use ($request) {
            $handoff = ScatHandoffCode::where('code_hash', hash('sha256', $request->code))
                ->lockForUpdate()
                ->first();

            if (! $handoff) {
                return response()->json(['error' => 'Code inconnu.'], 404);
            }
            if (! $handoff->isUsable()) {
                return response()->json(['error' => 'Code expiré ou déjà utilisé.'], 410);
            }

            $handoff->update(['used_at' => now()]);

            $user = $handoff->user;
            if (! $user || (int) $user->status !== Status::ACTIVE->value) {
                return response()->json(['error' => "Ce compte n'est pas actif."], 410);
            }

            Log::channel('api')->info('SCAT handoff redeemed', ['user_id' => $user->id]);

            return response()->json(['status' => 'success', 'data' => ['user' => self::toScatUser($user)]]);
        });
    }

    /**
     * scat-server: look an RPPS number up in healthcare_professionals (doctor sign-up check).
     * 404 when unknown.
     */
    public function rpps(string $rpps)
    {
        if (! preg_match('/^\d{11}$/', $rpps)) {
            return response()->json(['error' => 'Le numéro RPPS doit comporter 11 chiffres.'], 422);
        }

        $hcp = self::findHealthcareProfessional($rpps);
        if (! $hcp) {
            return response()->json(['error' => 'RPPS introuvable.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'found' => true,
                'rpps' => $hcp->pp,
                'name' => $hcp->name,
                'first_name' => $hcp->first_name,
                'profession' => $hcp->activities->first()?->label,
            ],
        ]);
    }

    /**
     * scat-server: does this e-mail belong to an OrthoMaster account, and is it a doctor's?
     * Used at SCAT6 player sign-up and e-mail change: a doctor's OrthoMaster address cannot become a player
     * account (one person = a doctor account and a player account with two addresses). Students may.
     */
    public function emailLookup(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', strtolower(trim($data['email'])))->first();

        return response()->json([
            'status' => 'success',
            'data' => ['exists' => $user !== null, 'is_doctor' => $user !== null && self::isDoctor($user)],
        ]);
    }

    /** Doctor for SCAT6 = active OrthoMaster account that is not a student account. */
    public static function isDoctor(User $user): bool
    {
        return (int) $user->status === Status::ACTIVE->value && ! $user->student_flg;
    }

    private static function findHealthcareProfessional(string $rpps): ?HealthcareProfessional
    {
        $rpps = trim($rpps);

        return HealthcareProfessional::where('pp', $rpps)
            ->orWhereIn('national_pp', [$rpps, '8'.$rpps])
            ->with('activities')
            ->first();
    }

    private static function toScatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'is_doctor' => self::isDoctor($user),
            'rpps' => $user->no_prof,
            'profession' => $user->profession?->name,
        ];
    }
}
