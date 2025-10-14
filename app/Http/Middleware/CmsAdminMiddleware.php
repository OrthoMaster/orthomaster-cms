<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CmsAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('voyager.login');
        }

        auth()->setDefaultDriver(app('VoyagerGuard'));
        $user = Auth::user();
        if (!$user->hasPermission('browse_admin')) {
            return abort(403); 
        }

        return $next($request);
    }
}
