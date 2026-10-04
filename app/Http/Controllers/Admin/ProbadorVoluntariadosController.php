<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\VoluntariadosChile\RevisorDeRespuesta;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

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

        /*
         * Tiempos cortos a propósito (04/10). Con 30 s, una llamada que se
         * cuelga desde el servidor —le pasa a Photon: «0 bytes received»—
         * dejaba la pantalla medio minuto sin decir nada, y parecía que el
         * botón no funcionaba. Quince segundos en total bastan para una API
         * que contesta en uno, y lo que pase se dice.
         */
        $peticion = Http::accept('application/json')->timeout(15)->connectTimeout(8);

        // Sin clave también se consulta: la API responde 401 y eso es lo que
        // prueba que la pantalla funciona antes de tener la clave.
        if ($clave !== '') {
            $peticion = $peticion->withToken($clave);
        }

        $base = [
            'url' => $url.($parametros ? '?'.http_build_query($parametros) : ''),
            'con_clave' => $clave !== '',
            // La hora de Chile, para leerla proyectada sin hacer cuentas.
            'consultado_en' => now()->timezone('America/Santiago')->locale('es')
                ->isoFormat('D [de] MMMM [de] YYYY, [a las] HH:mm:ss'),
        ];

        $inicio = hrtime(true);

        try {
            $respuesta = $peticion->get($url, $parametros);
        } catch (Throwable $e) {
            /*
             * Cualquier fallo de la llamada, no sólo los de conexión: un
             * certificado que el servidor no reconoce o una redirección rara
             * acababan en un 500 sin explicación. Se dice qué fue, porque es
             * justo lo que distingue «nuestro servidor no tiene salida» de
             * «la API no contesta». El mensaje no lleva la clave: la cabecera
             * no aparece en los errores de conexión de Guzzle.
             */
            return response()->json($base + [
                'http' => null,
                'ms' => (int) round((hrtime(true) - $inicio) / 1e6),
                'fallo_de_red' => Str::limit($e->getMessage(), 400),
                'titular' => 'No se pudo contactar a Voluntariados Chile',
                'explicacion' => $e instanceof ConnectionException
                    ? 'La consulta salió de nuestro servidor y no obtuvo respuesta. No es un problema de la credencial: la petición no llegó a la API.'
                    : 'La consulta falló antes de obtener una respuesta de la API.',
            ]);
        }

        $ms = (int) round((hrtime(true) - $inicio) / 1e6);
        $cuerpo = $respuesta->body();
        $json = json_decode($cuerpo, true);
        $esJson = json_last_error() === JSON_ERROR_NONE;

        [$titular, $explicacion] = $this->veredicto($respuesta->status(), $clave !== '', $esJson ? $json : null);

        return response()->json($base + [
            'http' => $respuesta->status(),
            'ms' => $ms,
            'titular' => $titular,
            'explicacion' => $explicacion,
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
     * Qué pasó, dicho para alguien que no es técnico (la pantalla se proyecta
     * en una reunión). El mensaje literal de la API va aparte, sin tocar.
     *
     * @return array{0: string, 1: string}
     */
    private function veredicto(int $http, bool $conClave, ?array $json): array
    {
        return match (true) {
            $http === 401 && ! $conClave => [
                'La API rechaza la petición: falta la credencial',
                'Voluntariados Chile exige una API Key para entregar las oportunidades. Sin ella no se puede obtener ningún dato.',
            ],
            $http === 401 => [
                'La API rechaza la petición: la credencial no es válida',
                'La API Key enviada no es la correcta o ya no está vigente.',
            ],
            $http === 400 => [
                'La API rechaza la petición: un parámetro no es válido',
                'Revisa updated_since, page o page_size.',
            ],
            $http >= 200 && $http < 300 => [
                'La API respondió: '.(int) data_get($json, 'pagination.total', count((array) data_get($json, 'data', []))).' oportunidades',
                'La credencial es válida y la API entrega datos.',
            ],
            $http >= 500 => [
                'Error en el servidor de Voluntariados Chile',
                'La API recibió la petición pero falló al responder. No depende de nosotros.',
            ],
            default => [
                "La API respondió con el código {$http}",
                'Es un código que la documentación no menciona.',
            ],
        };
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
