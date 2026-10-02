<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Que una consulta de fondo no se lleve los avisos de un envío rechazado.
 *
 * Los errores de validación y lo escrito en un formulario rechazado viven en
 * la sesión «para la petición siguiente», y Laravel los gasta en la primera
 * que llegue, sea cual sea. Si entre el envío y la recarga de la página entra
 * una consulta de fondo —el buscador de organizaciones con su retardo entre
 * teclas, el aviso de correo existente, las sugerencias de dirección—, se los
 * queda ella, que no los pinta, y la página vuelve **vacía y sin decir por
 * qué**. Se vio en Panel → Usuarios (02/10) escribiendo una organización y
 * pulsando «Crear usuario» enseguida, y el wizard tiene tres consultas así:
 * ahí se perdía todo lo escrito en los cinco pasos.
 *
 * Una petición que pide JSON nunca pinta avisos, así que si los hay
 * pendientes se dejan para la siguiente. Sólo los que están pendientes: los
 * que ya se enseñaron no vuelven.
 */
class ConservaAvisosPendientes
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->wantsJson() && $request->hasSession() && $request->session()->get('_flash.old')) {
            $request->session()->reflash();
        }

        return $next($request);
    }
}
