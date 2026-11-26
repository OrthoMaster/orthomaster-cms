<?php

namespace App\Http\Middleware;

use App\Status;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Outils réservés aux médecins (SCAT6) : compte actif qui n'est pas un compte étudiant.
 * L'application masque déjà l'outil ; ce contrôle protège l'API elle-même.
 */
class EnsureDoctor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (int) $user->status !== Status::ACTIVE->value || $user->student_flg) {
            return response()->json([
                'success' => false,
                'error'   => 'Outil réservé aux médecins.',
            ], 403);
        }

        return $next($request);
    }
}
