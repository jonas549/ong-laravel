<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\VoluntariadosChile\RevisorDeRespuesta;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Pantalla para probar a mano la API de Voluntariados Chile.
 *
 * No es la integración: es la herramienta para ver qué devuelve de verdad el
 * endpoint antes de construirla, y para llevar a la reunión con quien lo
 * mantiene qué falta, qué viene vacío y qué no estaba documentado.
 *
 * ── Por qué en el panel y no una página suelta ──
 *
 * La llamada sale del servidor: así no depende de lo que el navegador deje
 * hacer con CORS, y el revisor del contrato (`RevisorDeRespuesta`) es el
 * mismo código que usará la sincronización. Entra un administrador con su
 * sesión de siempre.
 *
 * ── Quién la ve ──
 *
 * Sólo un administrador: va en el grupo `role:admin` del panel. La consulta
 * lleva freno propio (30 por minuto).
 *
 * ── La API Key ──
 *
 * Llega en el cuerpo de una petición `fetch` y se usa para esa llamada y
 * nada más: no se guarda en sesión, ni en base de datos, ni en caché, ni se
 * escribe en el log. Tampoco vuelve al navegador en la respuesta.
 */
class ProbadorVoluntariadosController extends Controller
{
    public function index()
    {
        return view('admin.voluntariados-chile.probador', [
            'url' => config('services.voluntariados_chile.url'),
        ]);
    }

    public function consultar(Request $request, RevisorDeRespuesta $revisor): JsonResponse
    {
        $datos = $request->validate([
            'api_key' => ['nullable', 'string', 'max:2000'],
            // Sin reglas de formato a propósito: si se escribe un
            // updated_since mal formado, lo que interesa es ver qué
            // contesta la API (un 400 invalid_parameter), no que lo frene
            // esta pantalla antes.
            'updated_since' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'string', 'max:20'],
            'page_size' => ['nullable', 'string', 'max:20'],
        ]);

        $parametros = array_filter([
            'updated_since' => trim((string) ($datos['updated_since'] ?? '')),
            'page' => trim((string) ($datos['page'] ?? '')),
            'page_size' => trim((string) ($datos['page_size'] ?? '')),
        ], fn ($v) => $v !== '');

        $clave = trim((string) ($datos['api_key'] ?? ''));
        $url = config('services.voluntariados_chile.url');

        $peticion = Http::accept('application/json')->timeout(30)->connectTimeout(10);

        // Sin clave también se consulta: la API responde 401 y eso es lo que
        // prueba que la pantalla funciona antes de tener la clave.
        if ($clave !== '') {
            $peticion = $peticion->withToken($clave);
        }

        $inicio = hrtime(true);

        try {
            $respuesta = $peticion->get($url, $parametros);
        } catch (ConnectionException $e) {
            return response()->json([
                'http' => null,
                'ms' => (int) round((hrtime(true) - $inicio) / 1e6),
                'url' => $url.($parametros ? '?'.http_build_query($parametros) : ''),
                'con_clave' => $clave !== '',
                'fallo_de_red' => 'No se pudo conectar con la API: '.$e->getMessage(),
            ]);
        }

        $ms = (int) round((hrtime(true) - $inicio) / 1e6);
        $cuerpo = $respuesta->body();
        $json = json_decode($cuerpo, true);
        $esJson = json_last_error() === JSON_ERROR_NONE;

        return response()->json([
            'http' => $respuesta->status(),
            'ms' => $ms,
            'url' => $url.($parametros ? '?'.http_build_query($parametros) : ''),
            'con_clave' => $clave !== '',
            'cabeceras' => collect($respuesta->headers())
                ->only(['Content-Type', 'content-type', 'Retry-After', 'retry-after', 'X-RateLimit-Limit', 'x-ratelimit-limit', 'X-RateLimit-Remaining', 'x-ratelimit-remaining', 'x-sb-edge-region'])
                ->map(fn ($v) => implode(', ', (array) $v)),
            'bytes' => strlen($cuerpo),
            // El cuerpo tal cual: si no es JSON, se enseña como texto.
            'json' => $esJson ? $json : null,
            'texto' => $esJson ? null : mb_substr($cuerpo, 0, 20000),
            'informe' => $revisor->revisar($respuesta->status(), $esJson ? $json : null),
        ]);
    }

    /**
     * Revisar un JSON pegado a mano, sin llamar a la API.
     *
     * Para la reunión: lo que el desarrollador enseñe o mande se puede pasar
     * por el mismo revisor; y sirve para comprobar la pantalla con el
     * ejemplo del PDF mientras no haya clave.
     */
    public function revisarPegado(Request $request, RevisorDeRespuesta $revisor): JsonResponse
    {
        $datos = $request->validate([
            'json' => ['required', 'string', 'max:5000000'],
            'http' => ['nullable', 'integer', 'min:100', 'max:599'],
        ]);

        $json = json_decode($datos['json'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error_json' => 'No es JSON válido: '.json_last_error_msg().'.'], 422);
        }

        return response()->json([
            'http' => $datos['http'] ?? 200,
            'json' => $json,
            'informe' => $revisor->revisar($datos['http'] ?? 200, $json),
        ]);
    }
}
