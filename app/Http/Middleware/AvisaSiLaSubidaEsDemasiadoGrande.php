<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convierte en un aviso legible el fallo más mudo de PHP con las subidas.
 *
 * ── Qué pasa sin esto ──
 *
 * Cuando lo que se envía supera `post_max_size`, PHP **descarta la petición
 * entera antes de que Laravel exista**: `$_POST` y `$_FILES` llegan vacíos. Y
 * vacío quiere decir sin token CSRF, así que lo siguiente que ocurre es un 419
 * «Page Expired» en inglés. La persona ve una página de error que no menciona
 * ninguna fotografía, pierde todo lo que había escrito y no tiene forma de
 * saber qué hizo mal. Una regla `max:5120` en el formulario no ayuda: la
 * validación nunca llega a ejecutarse.
 *
 * Es el mismo patrón del bloque K con otra cara —el aviso correcto que nadie
 * ve— y por eso se ataja aquí, antes del CSRF.
 *
 * ── Cómo se detecta ──
 *
 * `Content-Length` sí sobrevive: lo pone el navegador en la cabecera y PHP no
 * lo toca. Si viene un POST con cuerpo declarado, ese cuerpo pasa del límite y
 * además no ha llegado nada, es esto y no otra cosa. Las tres condiciones
 * juntas, porque cada una por su lado tiene falsos positivos.
 */
class AvisaSiLaSubidaEsDemasiadoGrande
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->seLePasoDeTamano($request)) {
            return $next($request);
        }

        $limite = $this->limiteLegible();

        $aviso = "Lo que intentaste enviar pesa demasiado (el máximo es {$limite}) "
            .'y no llegó al servidor. Si adjuntaste una fotografía, prueba con una más pequeña.';

        // `back()` sin referente cae en la misma URL, que en un POST no existe
        // como GET: se manda a la anterior, y si no la hay, a la portada.
        return redirect()
            ->to($request->headers->get('referer') ?: url('/'))
            ->with('error', $aviso);
    }

    private function seLePasoDeTamano(Request $request): bool
    {
        if (! $request->isMethod('POST')) {
            return false;
        }

        $declarado = (int) $request->server('CONTENT_LENGTH');
        $limite = $this->limiteEnBytes();

        if ($declarado <= 0 || $limite <= 0 || $declarado <= $limite) {
            return false;
        }

        /*
         * Y que efectivamente no haya llegado nada. Un POST grande que SÍ trae
         * datos es un POST normal —el límite puede estar declarado más alto en
         * otro sitio de la cadena— y no hay que tocarlo.
         */
        return count($request->all()) === 0 && count($request->allFiles()) === 0;
    }

    private function limiteEnBytes(): int
    {
        return $this->aBytes((string) ini_get('post_max_size'));
    }

    private function limiteLegible(): string
    {
        $bytes = $this->limiteEnBytes();

        return $bytes >= 1048576
            ? round($bytes / 1048576).' MB'
            : round($bytes / 1024).' KB';
    }

    /** `8M`, `512K`, `1G` → bytes. Un `0` o un vacío significan «sin límite». */
    private function aBytes(string $valor): int
    {
        $valor = trim($valor);

        if ($valor === '' || $valor === '0' || $valor === '-1') {
            return 0;
        }

        $numero = (int) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g' => $numero * 1024 * 1024 * 1024,
            'm' => $numero * 1024 * 1024,
            'k' => $numero * 1024,
            default => $numero,
        };
    }
}
