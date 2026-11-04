<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sous-domaine des pages partenaires (config brochures.domain, ex. expert.orthomaster.fr) :
 * seules les pages partenaires y répondent, toute autre route renvoie 404.
 * Les autres domaines (cms., auth., …) ne sont pas concernés.
 */
class RestrictExpertDomain
{
    /** Routes autorisées sur le sous-domaine. */
    private const ALLOWED = [
        'brochures.home',          // /             → page de démonstration
        'brochures.sample',        // /demo         page de démonstration
        'brochures.public',        // /{slug}       page publique de l'expert
        'brochures.demo',          // /demo/{slug}  éditeur (lien signé)
        'brochures.demo.save',
        'brochures.demo.avatar',
        'brochures.demo.domain',
        'brochures.demo.publish',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $domain = config('brochures.domain');

        if ($domain && strcasecmp($request->getHost(), $domain) === 0) {
            $name = $request->route()?->getName();
            abort_unless($name && in_array($name, self::ALLOWED, true), 404);
        }

        return $next($request);
    }
}
