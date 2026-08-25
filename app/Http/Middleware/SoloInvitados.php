<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aparta de una pantalla a quien ya tiene la sesión abierta.
 *
 * Existe por el wizard de publicar: crea siempre una cuenta nueva y entra con
 * ella, así que alguien que ya tenía cuenta acababa dentro de otra distinta,
 * dejando la suya —con sus actividades— abandonada sin enterarse.
 *
 * No se comprueba dentro del controlador porque el `FormRequest` valida antes
 * de que el método llegue a ejecutarse: por ahí la persona veía primero una
 * lista de errores de un formulario que nunca debió llegar a enviar.
 */
class SoloInvitados
{
    public function handle(Request $request, Closure $next, ?string $aviso = null): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        return redirect()
            ->route(Auth::user()->esAdmin() ? 'admin.dashboard' : 'account.activities.index')
            ->with('error', $aviso ?: 'Ya tienes la sesión abierta.');
    }
}
