<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Redirige les utilisateurs sans rôle d'administration hors du back-office.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->hasRole(Role::SUPER_ADMIN) || $user->hasRole(Role::ADMIN))) {
            return $next($request);
        }

        return redirect()->route('home');
    }
}
