<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityEvaluation;
use App\Services\Biblioteca;
use App\Services\Evaluaciones;
use App\Services\Exportador;
use App\Support\Fecha;
use App\Support\Filtro;
use App\Support\Listado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Las evaluaciones que dejan los asistentes, para que la ONG pueda leerlas.
 *
 * No estaba en el encargo y hace falta igual: sin esta pantalla se recogen
 * datos que no puede mirar nadie, que es la peor versión de recoger datos.
 *
 * **Sólo administrador.** Lo decidió Jonas: el organizador ve su actividad, no
 * las opiniones que dejaron sobre ella. Eso lo garantiza el `role:admin` del
 * grupo de rutas, no este controlador.
 */
class EvaluationController extends Controller
{
    public function index(Request $request, Evaluaciones $evaluaciones)
    {
        $consulta = $this->consulta($request);

        /*
         * Los promedios se calculan sobre TODO lo filtrado, no sobre la página
         * que se está viendo. El promedio de las veinticinco filas de la
         * primera página no es el promedio de nada.
         *
         * Se traen sólo las dos columnas de las escalas: son dos enteros por
         * fila, así que aunque la encuesta acumule miles de respuestas esto
         * cabe de sobra en memoria, y se lee mucho mejor que diez `SUM(...)`
         * condicionales metidos en el SELECT.
         */
        $resumen = $evaluaciones->resumen(
            (clone $consulta)->reorder()->get(['experiencia', 'motivacion'])
        );

        $listado = Listado::ordenar($consulta->with('activity'), $request, [
            'nombre', 'correo', 'experiencia', 'motivacion', 'created_at',
        ], 'created_at', 'desc')
            ->paginate(Listado::porPagina($request))
            ->withQueryString();

        return view('admin.evaluaciones.index', [
            'evaluaciones' => $listado,
            'resumen' => $resumen,
            'filtros' => $this->filtros($request),
            'actividades' => $this->actividadesConEvaluaciones(),
            'escalas' => ActivityEvaluation::ESCALAS,
            'origenes' => ActivityEvaluation::ORIGENES,
        ]);
    }

    /**
     * Las fotografías, con las autorizadas separadas de las que no lo están.
     *
     * La separación es el punto entero de la pantalla. Una foto sin
     * autorización no es una foto que falte revisar: es una foto que **no se
     * puede publicar**, y tenerlas mezcladas en una misma cuadrícula es la
     * forma más fácil de que alguien coja la equivocada.
     */
    public function fotos(Request $request)
    {
        $cual = Filtro::texto($request, 'estado') === 'sin-autorizar' ? 'sin-autorizar' : 'autorizadas';

        $consulta = $this->consulta($request)->conFoto();

        $consulta = $cual === 'autorizadas'
            ? $consulta->where('foto_autorizada', true)
            : $consulta->where('foto_autorizada', false);

        return view('admin.evaluaciones.fotos', [
            'fotos' => $consulta->with('activity')->paginate(24)->withQueryString(),
            'cual' => $cual,
            'filtros' => $this->filtros($request),
            'actividades' => $this->actividadesConEvaluaciones(),
            'cuantas' => [
                'autorizadas' => ActivityEvaluation::query()->autorizadas()->count(),
                'sin-autorizar' => ActivityEvaluation::query()->sinAutorizar()->count(),
            ],
        ]);
    }

    /**
     * Sirve una foto del disco privado.
     *
     * Las fotos NO están en `storage/app/public` a propósito: ahí se sirven por
     * URL directa y cualquiera con el enlace las vería, autorizada o no. Esta
     * ruta está dentro del grupo `role:admin`, así que la sesión es el permiso.
     *
     * Se responde en streaming para no cargar en memoria una foto de varios
     * megabytes por cada miniatura de la cuadrícula.
     */
    public function foto(ActivityEvaluation $evaluacion): StreamedResponse
    {
        abort_unless($evaluacion->tieneFoto(), 404);
        abort_unless(Storage::disk('local')->exists($evaluacion->foto_path), 404);

        return Storage::disk('local')->response(
            $evaluacion->foto_path,
            null,
            // `inline` y no `attachment`: esto se pinta en la cuadrícula del
            // panel, no se descarga.
            ['Content-Disposition' => 'inline'],
        );
    }

    /**
     * Pasa una foto autorizada a la biblioteca de medios.
     *
     * Sólo las autorizadas, y sólo pulsando el botón. Las fotos de la encuesta
     * no se indexan solas —ensuciarían el detector de «dónde se usa» con
     * archivos que no usa ninguna vista— y una autorización es el permiso para
     * publicar, no la publicación.
     */
    public function aBiblioteca(Request $request, ActivityEvaluation $evaluacion, Biblioteca $biblioteca)
    {
        abort_unless($evaluacion->tieneFoto(), 404);

        if (! $evaluacion->foto_autorizada) {
            return back()->with('error', 'Esa fotografía no tiene autorización de difusión.');
        }

        $absoluta = Storage::disk('local')->path($evaluacion->foto_path);

        abort_unless(is_file($absoluta), 404);

        $medio = $biblioteca->adoptar(
            $absoluta,
            'evaluacion-'.$evaluacion->id.'-'.($evaluacion->activity?->slug ?? 'actividad').'.'
                .pathinfo($evaluacion->foto_path, PATHINFO_EXTENSION),
            carpeta: 'Evaluaciones',
            usuario: $request->user(),
        );

        return back()->with('ok', 'La fotografía ya está en la biblioteca de medios como «'.$medio->nombre.'».');
    }

    /**
     * Borra una respuesta.
     *
     * Sin papelera y de verdad, que es lo que dice el modelo: lo que se quita
     * de aquí es un insulto o una prueba, y guardarlo «por si acaso» es
     * quedarse justo con lo que se quería quitar. La foto se va con ella.
     */
    public function destroy(ActivityEvaluation $evaluacion)
    {
        if ($evaluacion->tieneFoto()) {
            Storage::disk('local')->delete($evaluacion->foto_path);
        }

        $evaluacion->delete();

        return back()->with('ok', 'Evaluación eliminada.');
    }

    /** La descarga a Excel o CSV, con los componentes del bloque H. */
    public function descargar(Request $request, Exportador $exportador): StreamedResponse
    {
        $formato = Filtro::texto($request, 'formato') === 'csv' ? 'csv' : 'xlsx';

        $cabeceras = [
            'Fecha', 'Actividad', 'Organización', 'Nombre', 'Correo',
            'Experiencia (1-5)', 'Motivación (1-5)',
            'Qué significa el Patrimonio Social', 'Cómo se enteró',
            'Fotografía', 'Autoriza difusión',
        ];

        /*
         * Un generador y no un `get()`: el exportador escribe en streaming, así
         * que las filas se leen de cien en cien y no se acumulan en memoria.
         */
        $filas = function () use ($request) {
            foreach ($this->consulta($request)->with('activity.organization')->lazyById(100) as $e) {
                yield [
                    /*
                     * La fecha va como objeto y no como texto ISO: así Excel la
                     * reconoce como fecha y se puede ordenar y filtrar por ella.
                     * Era uno de los dos defectos que el testing de Cowork dejó
                     * anotados sobre la exportación de contenidos.
                     */
                    $e->created_at,
                    $e->activity?->titulo ?? '(actividad borrada)',
                    $e->activity?->organization?->nombre ?? '',
                    $e->nombre,
                    $e->correo,
                    $e->experiencia,
                    $e->motivacion,
                    $e->significado,
                    $e->origen_label,
                    // El otro defecto de Cowork: «Si» sin tilde.
                    $e->tieneFoto() ? 'Sí' : 'No',
                    $e->tieneFoto() ? ($e->foto_autorizada ? 'Sí' : 'No') : '',
                ];
            }
        };

        return $exportador->descargar($formato, 'evaluaciones-'.Fecha::iso(now()), $cabeceras, $filas());
    }

    /* ── Interno ─────────────────────────────────────────── */

    /** @return array<string, string> */
    private function filtros(Request $request): array
    {
        return [
            'q' => Filtro::texto($request, 'q'),
            'actividad' => Filtro::texto($request, 'actividad'),
            'desde' => Filtro::texto($request, 'desde'),
            'hasta' => Filtro::texto($request, 'hasta'),
            'foto' => Filtro::texto($request, 'foto'),
        ];
    }

    private function consulta(Request $request)
    {
        $f = $this->filtros($request);

        return ActivityEvaluation::query()
            ->when($f['q'], function ($q, $busca) {
                $busca = Filtro::like($busca);

                $q->where(function ($w) use ($busca) {
                    $w->where('nombre', 'like', "%{$busca}%")
                        ->orWhere('correo', 'like', "%{$busca}%")
                        // El texto libre también: es donde está lo que se busca
                        // cuando alguien recuerda una frase y no un nombre.
                        ->orWhere('significado', 'like', "%{$busca}%");
                });
            })
            ->when($f['actividad'], fn ($q, $a) => $q->where('activity_id', $a))
            ->when($f['desde'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($f['hasta'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($f['foto'] === 'con', fn ($q) => $q->conFoto())
            ->when($f['foto'] === 'autorizadas', fn ($q) => $q->autorizadas())
            ->latest('id');
    }

    /**
     * Sólo las actividades que tienen alguna evaluación.
     *
     * Un desplegable con las trescientas actividades del sitio no es un filtro,
     * es un listado: la mayoría no tendría ni una respuesta que enseñar.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function actividadesConEvaluaciones()
    {
        return Activity::query()
            ->whereIn('id', ActivityEvaluation::query()->select('activity_id'))
            ->orderBy('titulo')
            ->pluck('titulo', 'id');
    }
}
