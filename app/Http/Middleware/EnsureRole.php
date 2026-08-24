<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $rol): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || $user->role !== $rol) {
            abort(403, 'No tienes acceso a esta sección.');
        }

        return $next($request);
    }
}
