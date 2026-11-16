<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only scat-server (holder of SCAT_PARTNER_KEY) may call /api/partner/scat/*. */
class ScatPartnerKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('scat.partner_key');
        $given = (string) $request->header('X-Partner-Key');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
