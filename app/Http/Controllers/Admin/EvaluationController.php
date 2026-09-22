<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityEvaluation;
use App\Models\EvaluationPhoto;
use App\Services\Biblioteca;
use App\Services\Evaluaciones;
use App\Services\Exportador;
use App\Support\Fecha;
use App\Support\Filtro;
use App\Support\Listado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Las evaluaciones que dejan los asistentes, para que la ONG pueda leerlas.
 *
 * No estaba en el encargo y hace falta igual: sin esta pantalla se recogen
 * datos que no puede mirar nadie, que es la peor versión de recoger datos.
 *
 * **Sólo administrador**, por decisión de producto: el organizador ve su
 * actividad, no las opiniones que dejaron sobre ella. Eso lo garantiza el
 * `role:admin` del grupo de rutas, no este controlador.
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

        $listado = Listado::ordenar($consulta->with(['activity', 'fotos']), $request, [
            'id', 'nombre', 'correo', 'experiencia', 'motivacion', 'created_at',
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
     *
     * Desde que una respuesta puede traer varias fotos, lo que se pagina son
     * las FOTOS y no las respuestas: con veinticuatro respuestas de tres fotos
     * cada una, paginar respuestas daba páginas de setenta y dos imágenes.
     */
    public function fotos(Request $request)
    {
        $cual = Filtro::texto($request, 'estado') === 'sin-autorizar' ? 'sin-autorizar' : 'autorizadas';

        return view('admin.evaluaciones.fotos', [
            'fotos' => $this->consultaDeFotos($request, $cual)
                ->with('evaluation.activity')
                ->paginate(24)
                ->withQueryString(),
            'cual' => $cual,
            'filtros' => $this->filtros($request),
            'actividades' => $this->actividadesConEvaluaciones(),
            'cuantas' => [
                'autorizadas' => $this->consultaDeFotos($request, 'autorizadas')->count(),
                'sin-autorizar' => $this->consultaDeFotos($request, 'sin-autorizar')->count(),
            ],
        ]);
    }

    /**
     * Las fotos que caen dentro de los filtros de la pantalla.
     *
     * Se apoya en `consulta()` en vez de repetir los filtros: son los mismos
     * que los del listado de respuestas, y dos listas de filtros que tienen
     * que decir lo mismo acaban diciendo cosas distintas.
     */
    private function consultaDeFotos(Request $request, string $cual)
    {
        $evaluaciones = (clone $this->consulta($request))->reorder()
            ->where('foto_autorizada', $cual === 'autorizadas');

        return EvaluationPhoto::query()
            ->whereIn('activity_evaluation_id', $evaluaciones->select('id'))
            ->orderByDesc('activity_evaluation_id')
            ->orderBy('orden');
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
    public function foto(EvaluationPhoto $foto): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($foto->ruta), 404);

        return Storage::disk('local')->response(
            $foto->ruta,
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
    public function aBiblioteca(Request $request, EvaluationPhoto $foto, Biblioteca $biblioteca)
    {
        if (! $foto->estaAutorizada()) {
            return back()->with('error', 'Esa fotografía no tiene autorización de difusión.');
        }

        $absoluta = Storage::disk('local')->path($foto->ruta);

        abort_unless(is_file($absoluta), 404);

        $evaluacion = $foto->evaluation;

        $medio = $biblioteca->adoptar(
            $absoluta,
            'evaluacion-'.$evaluacion->id.'-'.$foto->id.'-'
                .($evaluacion->activity?->slug ?? 'actividad').'.'.$foto->extension(),
            carpeta: 'Evaluaciones',
            usuario: $request->user(),
        );

        return back()->with('ok', 'La fotografía ya está en la biblioteca de medios como «'.$medio->nombre.'».');
    }

    /**
     * Descarga en un zip las fotografías que están a la vista.
     *
     * **Respeta los filtros de la pantalla**, que es el encargo entero: si se
     * está filtrando por una actividad o por fechas, se bajan ésas y no todo.
     * Un botón que siempre se lo lleva todo obliga a separar a mano después, y
     * entonces no ahorra nada.
     *
     * El zip se arma en un archivo temporal y no en memoria: cien fotos de
     * medio mega son cincuenta megas, y `memory_limit` no está para eso.
     *
     * Las carpetas de dentro van por actividad, que es como se van a usar.
     */
    public function descargarFotos(Request $request): BinaryFileResponse
    {
        abort_unless(class_exists(\ZipArchive::class), 503, 'Este servidor no tiene la extensión zip de PHP.');

        $cual = Filtro::texto($request, 'estado') === 'sin-autorizar' ? 'sin-autorizar' : 'autorizadas';

        $fotos = $this->consultaDeFotos($request, $cual)->with('evaluation.activity')->get();

        abort_if($fotos->isEmpty(), 404, 'No hay fotografías que descargar con esos filtros.');

        $temporal = tempnam(sys_get_temp_dir(), 'dps-fotos-');

        $zip = new \ZipArchive;
        $zip->open($temporal, \ZipArchive::OVERWRITE);

        foreach ($fotos as $foto) {
            $absoluta = Storage::disk('local')->path($foto->ruta);

            // Una fila sin su archivo no puede tumbar la descarga entera.
            if (! is_file($absoluta)) {
                continue;
            }

            $evaluacion = $foto->evaluation;
            $carpeta = Str::slug($evaluacion?->activity?->titulo ?? 'sin-actividad');

            $zip->addFile(
                $absoluta,
                $carpeta.'/'.$evaluacion?->id.'-'.Str::slug($evaluacion?->nombre ?? 'anonimo')
                    .'-'.$foto->id.'.'.$foto->extension(),
            );
        }

        /*
         * Un aviso dentro del propio zip con de dónde salió.
         *
         * Las sin autorizar son las que más falta hacen: el zip se descomprime
         * en el escritorio de alguien, lejos de la pestaña del panel que decía
         * que esas fotos no se pueden publicar.
         */
        $zip->addFromString('LEEME.txt', $this->avisoDelZip($cual, $fotos->count()));
        $zip->close();

        return response()->download(
            $temporal,
            'fotografias-'.$cual.'-'.Fecha::iso(now()).'.zip',
            ['Content-Type' => 'application/zip'],
        )->deleteFileAfterSend(true);
    }

    private function avisoDelZip(string $cual, int $cuantas): string
    {
        $cabecera = "Fotografías de las evaluaciones del Día del Patrimonio Social\n"
            ."Descargadas el ".Fecha::iso(now())." · {$cuantas} archivos\n\n";

        return $cabecera.($cual === 'autorizadas'
            ? "AUTORIZADAS PARA DIFUSIÓN.\nQuien las subió marcó la autorización de uso de su imagen.\n"
            : "SIN AUTORIZACIÓN DE DIFUSIÓN.\nEstas fotografías NO se pueden publicar: quien las subió no\n"
                ."autorizó el uso de su imagen. Sirven para ver cómo fue la actividad,\ny ahí se acaba lo que se puede hacer con ellas.\n");
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
        // Todas sus fotos, no una: el borrado en cascada de la base se lleva
        // las filas, pero los archivos del disco hay que quitarlos a mano.
        foreach ($evaluacion->fotos as $foto) {
            Storage::disk('local')->delete($foto->ruta);
        }

        $evaluacion->delete();

        return back()->with('ok', 'Evaluación eliminada.');
    }

    /** La descarga a Excel o CSV, con los componentes del bloque H. */
    public function descargar(Request $request, Exportador $exportador): StreamedResponse
    {
        $formato = Filtro::texto($request, 'formato') === 'csv' ? 'csv' : 'xlsx';

        $cabeceras = [
            'ID', 'Fecha', 'Actividad', 'Organización', 'Nombre', 'Correo',
            'Experiencia (1-5)', 'Motivación (1-5)',
            'Qué significa el Patrimonio Social', 'Cómo se enteró',
            'Fotografías', 'Autoriza difusión', 'Enlaces a las fotografías',
        ];

        /*
         * Un generador y no un `get()`: el exportador escribe en streaming, así
         * que las filas se leen de cien en cien y no se acumulan en memoria.
         */
        $filas = function () use ($request) {
            foreach ($this->consulta($request)->with(['activity.organization', 'fotos'])->lazyById(100) as $e) {
                yield [
                    $e->id,
                    /*
                     * La fecha va como objeto y no como texto ISO: así Excel la
                     * reconoce como fecha y se puede ordenar y filtrar por ella.
                     * Era uno de los dos defectos que salieron al probar la
                     * exportación de contenidos.
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
                    // El otro defecto de aquella prueba: «Si» sin tilde. Ahora
                    // además dice cuántas son, que con varias por respuesta importa.
                    $e->fotos->count() ?: 'No',
                    $e->fotos->isNotEmpty() ? ($e->foto_autorizada ? 'Sí' : 'No') : '',
                    /*
                     * Los enlaces a las fotos, uno por línea dentro de la misma
                     * celda. Son URL del panel y piden sesión de administrador:
                     * quien abra el Excel sin estar dentro no verá nada, que es
                     * justo lo que tiene que pasar con fotos de asistentes.
                     *
                     * Absolutas y no relativas: esto se abre en Excel, no en el
                     * navegador, y una ruta relativa ahí no lleva a ninguna parte.
                     */
                    $e->fotos
                        ->map(fn ($f) => route('admin.evaluaciones.foto', $f))
                        ->implode("
"),
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
