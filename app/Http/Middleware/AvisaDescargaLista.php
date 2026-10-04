<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Le dice a la página que la descarga que pidió ya salió.
 *
 * ── El problema ──
 *
 * El botón que se pulsa se marca como ocupado y se suelta cuando la página
 * navega. Una descarga no navega: el navegador guarda el archivo y la página
 * se queda donde estaba, así que el botón seguía girando para siempre aunque
 * el zip ya estuviera en la carpeta de descargas («Descargar seleccionadas»,
 * 04/10). Y desde la página no hay forma de saber cuándo ha terminado: la
 * respuesta se la lleva el navegador, no el JavaScript.
 *
 * ── El testigo ──
 *
 * Quien pide una descarga marcada con `data-descarga` le añade `_descarga`
 * con un valor al azar. Si la respuesta es un archivo, aquí se devuelve una
 * cookie con ese mismo valor, y la página —que la está esperando— suelta el
 * botón en cuanto aparece. Si la respuesta es otra cosa —un error, una
 * redirección—, la página navega y el botón se va con ella.
 *
 * La cookie no puede ir cifrada: la lee el JavaScript. Está fuera de
 * `encryptCookies` en `bootstrap/app.php`, no lleva nada que no estuviera ya
 * en la URL y caduca en un minuto.
 */
class AvisaDescargaLista
{
    public const COOKIE = 'dps_descarga';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $testigo = $request->query('_descarga');

        if (! is_string($testigo) || ! preg_match('/^[a-z0-9]{8,40}$/', $testigo) || ! $this->esArchivo($response)) {
            return $response;
        }

        $response->headers->setCookie(new Cookie(
            self::COOKIE,
            $testigo,
            now()->addMinute(),
            '/',
            null,
            $request->isSecure(),
            false, // la tiene que leer el JavaScript
            false,
            Cookie::SAMESITE_LAX,
        ));

        return $response;
    }

    private function esArchivo(Response $response): bool
    {
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return true;
        }

        return str_contains(strtolower((string) $response->headers->get('Content-Disposition')), 'attachment');
    }
}
