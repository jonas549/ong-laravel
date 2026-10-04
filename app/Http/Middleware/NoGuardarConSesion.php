<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Que el navegador no guarde una pantalla con sesión.
 *
 * ── El fallo (04/10) ──
 *
 * Cerrar sesión en el panel y pulsar «atrás» volvía a pintar el dashboard
 * como si la sesión siguiera viva. Laravel responde por defecto con
 * `Cache-Control: no-cache, private`, que obliga a revalidar al pedirla de
 * nuevo pero NO impide que el navegador restaure la página entera de su
 * memoria al ir hacia atrás. Desde esa copia, «Cerrar sesión» daba un 419 —el
 * token era de una sesión que ya no existe— y refrescarla dejaba apuntada la
 * URL del panel como destino pendiente, que es lo que acababa en el 403 al
 * entrar después con otra cuenta.
 *
 * `no-store` es lo único que saca la página de esa memoria. Va en toda
 * respuesta servida con sesión abierta, panel o mi-cuenta, y también en las
 * públicas que se pintan con ella (la cabecera dice «Mi cuenta»).
 *
 * Y por si un navegador la restaura igual, el layout lleva
 * `data-con-sesion` y `app.js` recarga la página al volver a ella: entonces
 * decide el servidor, que ya sabe que no hay sesión.
 */
class NoGuardarConSesion
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (Auth::check()) {
            $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
