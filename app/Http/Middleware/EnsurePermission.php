<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Refuse l'accès (403) si l'utilisateur ne dispose pas de la permission.
     *
     * Usage : middleware('permission:roles.view')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user && $user->hasPermission($permission)) {
            return $next($request);
        }

        abort(403);
    }
}
