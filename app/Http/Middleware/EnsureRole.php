<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $rol): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'No tienes acceso a esta sección.');
        }

        if ($user->role !== $rol) {
            /*
             * Una cuenta activa en la sección del otro rol: un organizador en
             * el panel o un administrador en mi-cuenta. Antes era un 403 «No
             * puedes ver esta página», una pantalla muerta (04/10). No es un
             * intento de colarse —casi siempre es un enlace guardado o un
             * destino pendiente de otra sesión—, así que se le lleva a SU
             * panel con una línea que diga por qué.
             *
             * Sólo al navegar. Un envío de formulario o una consulta de fondo
             * siguen recibiendo el 403: redirigirlos perdería lo enviado sin
             * que se note.
             */
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                return $user->role === User::ROL_ADMIN
                    ? redirect()->route('admin.dashboard')
                        ->with('error', 'Esa página es de las cuentas de organización. Te dejamos en el panel de administración.')
                    : redirect()->route('account.activities.index')
                        ->with('error', 'Esa página es del panel de administración. Te dejamos en tu cuenta.');
            }

            abort(403, 'No tienes acceso a esta sección.');
        }

        return $next($request);
    }
}
