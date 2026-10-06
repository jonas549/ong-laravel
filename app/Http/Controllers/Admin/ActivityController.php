<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Services\ActivityModerationService;
use App\Support\Filtro;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * Cada estado tiene su nodo en el menu, asi que el estado puede venir fijo
     * desde la ruta. Sigue aceptandose por query para no romper los enlaces
     * que ya existian.
     */
    public function index(Request $request, ?string $estadoFijo = null)
    {
        $estado = $estadoFijo ?: Filtro::texto($request, 'estado');

        // Lo que se publicó solo, para poder repasarlo: sin este filtro no
        // aparece en «pendientes» y no hay forma de encontrarlo.
        $soloAutomaticas = $request->boolean('auto');

        // Las que volvieron corregidas de una petición de ajustes. Es a donde
        // apunta la alerta del escritorio.
        $soloVueltas = $request->boolean('vueltas');

        $actividades = Activity::with(['organization', 'commune', 'region'])
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($soloAutomaticas, fn ($q) => $q->where('publicada_automaticamente', true))
            ->when($soloVueltas, fn ($q) => $q->vueltasDeAjustes())
            // 8b del 05/10: por nombre de actividad O de organización, con el
            // mismo scope que el buscador del sitio público.
            ->when(Filtro::texto($request, 'q'), fn ($q, $b) => $q->byTexto($b))
            ->withCount(['registrations as inscritos' => fn ($q) => $q->where('estado', '!=', 'cancelado')])
            ->withExists('registrations as tiene_inscripciones')
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $conteos = Activity::selectRaw('estado, COUNT(*) n')->groupBy('estado')->pluck('n', 'estado');
        $automaticas = Activity::where('publicada_automaticamente', true)->count();
        $vueltas = Activity::vueltasDeAjustes()->count();

        /*
         * Cuáles de las que se están enseñando vuelven de ajustes. Se resuelve
         * de una vez para toda la página en lugar de preguntárselo a cada fila:
         * `vuelveDeAjustes()` hace una consulta por actividad y en un listado
         * de veinte eso son veinte viajes a la base de datos.
         */
        $vuelvenDeAjustes = Activity::vueltasDeAjustes()
            ->whereIn('id', $actividades->pluck('id'))
            ->pluck('id')
            ->flip();

        return view('admin.activities.index', compact(
            'actividades', 'conteos', 'estado', 'estadoFijo', 'soloAutomaticas', 'automaticas',
            'soloVueltas', 'vueltas', 'vuelvenDeAjustes',
        ));
    }

    /**
     * Actividades → Exportar: la pantalla. Replica la de Exportar
     * inscripciones (ajustes del 06/10): se elige el recorte, se ve cuántas
     * saldrían y se descarga. Sin filtros, salen TODAS, en cualquier estado.
     */
    public function exportar(Request $request)
    {
        return view('admin.activities.exportar', [
            'filtros' => $this->filtrosDeExportacion($request),
            'estados' => Activity::ESTADOS,
            'cuantas' => $this->consultaDeExportacion($request)->count(),
        ]);
    }

    /**
     * La descarga, con los mismos filtros que la pantalla.
     *
     * Sin paginación ni tope: se leen de 200 en 200 (`lazyById`, que sí carga
     * las relaciones de cada tanda) y se escriben según llegan. Las borradas
     * a la papelera no salen, como no salen en ninguna pantalla.
     */
    public function descargar(Request $request, \App\Services\Exportador $exportador)
    {
        $consulta = $this->consultaDeExportacion($request)
            ->with(['organization.user', 'commune', 'region', 'terms', 'collaborators']);

        $filas = (function () use ($consulta) {
            foreach ($consulta->lazyById(200) as $a) {
                yield self::filaDeExportacion($a);
            }
        })();

        return $exportador->xlsx('Actividades', self::COLUMNAS_EXPORTACION, $filas);
    }

    /** @return array<string, string> */
    private function filtrosDeExportacion(Request $request): array
    {
        return [
            'q' => mb_substr(Filtro::texto($request, 'q'), 0, 100),
            'estado' => Filtro::texto($request, 'estado'),
            'desde' => Filtro::texto($request, 'desde'),
            'hasta' => Filtro::texto($request, 'hasta'),
        ];
    }

    /**
     * El buscador es el del sitio público (actividad u organización); el
     * estado, uno de los cinco; y el rango, sobre la FECHA DE REGISTRO de la
     * actividad, que es lo que en inscripciones es la fecha de inscripción.
     */
    private function consultaDeExportacion(Request $request)
    {
        $f = $this->filtrosDeExportacion($request);

        return Activity::query()
            ->when($f['q'], fn ($q, $b) => $q->byTexto($b))
            ->when(array_key_exists($f['estado'], Activity::ESTADOS), fn ($q) => $q->where('estado', $f['estado']))
            ->when($f['desde'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($f['hasta'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
    }

    public const COLUMNAS_EXPORTACION = [
        'ID', 'Actividad', 'Organización', 'Estado', 'Correo que registró la actividad', 'Fecha de registro',
        'Fecha de la actividad', 'Fecha de término', 'Hora de inicio', 'Hora de término',
        'Dirección', 'Comuna', 'Región', 'Formato', 'Cupos totales', 'Cupos disponibles',
        'Requiere inscripción previa', 'Descripción', 'Temas', 'Características', 'Dirigido a', 'Colaboración',
        'Red social', 'Sitio web',
    ];

    /** @return list<string|int> Una celda por cada `COLUMNAS_EXPORTACION`. */
    public static function filaDeExportacion(Activity $a): array
    {
        // Las fechas tal cual: `Fecha` convierte de zona y un día sin hora a
        // medianoche podía acabar en el anterior.
        $dia = fn ($f) => $f ? $f->format('d-m-Y') : '';
        $hora = fn ($h) => $h ? substr((string) $h, 0, 5) : '';
        $terminos = fn (string $grupo) => $a->termsDe($grupo)->pluck('nombre')->implode(', ');

        return [
            $a->id,
            $a->titulo,
            $a->organization?->nombre ?? '',
            $a->estado_label,
            $a->organization?->user?->email ?? '',
            \App\Support\Fecha::conHora($a->created_at),
            $a->sin_fecha_definida ? 'Por definir' : $dia($a->fecha_inicio),
            $a->sin_fecha_definida ? '' : $dia($a->fecha_termino),
            $hora($a->hora_inicio),
            $hora($a->hora_termino),
            $a->direccion ?? '',
            $a->commune?->nombre ?? '',
            $a->region?->nombre ?? '',
            $a->formato ?? '',
            $a->cupos_totales ?? '',
            $a->cupos_disponibles ?? '',
            $a->inscripcion_label,
            $a->descripcion ?? '',
            $terminos('tema'),
            $terminos('caracteristica'),
            collect([$terminos('publico'), $a->publico_otro])->filter()->implode(', '),
            $a->collaborators->map(fn ($c) => $c->tipo ? "{$c->nombre} ({$c->tipo})" : $c->nombre)->implode(', '),
            $a->organization?->enlace_red_social ?? '',
            $a->organization?->enlace_web ?? '',
        ];
    }

    public function show(Activity $activity)
    {
        $activity->load(['organization.user', 'region', 'commune', 'terms', 'collaborators', 'statusLogs.user']);

        return view('admin.activities.show', compact('activity'));
    }

    public function approve(Request $request, Activity $activity, ActivityModerationService $moderacion)
    {
        $moderacion->cambiar($activity, 'publicada', $request->user());

        return back()->with('ok', 'Actividad publicada. Se avisó al organizador.');
    }

    public function requestChanges(Request $request, Activity $activity, ActivityModerationService $moderacion)
    {
        $datos = $request->validate([
            'comentario' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'comentario.required' => 'Escribe qué hay que ajustar: el organizador solo recibe este texto.',
            'comentario.min' => 'Explica el ajuste con un poco más de detalle.',
        ]);

        $moderacion->cambiar($activity, 'ajustes', $request->user(), $datos['comentario']);

        return back()->with('ok', 'Le pedimos ajustes al organizador.');
    }

    public function reject(Request $request, Activity $activity, ActivityModerationService $moderacion)
    {
        $moderacion->cambiar(
            $activity,
            'cancelada',
            $request->user(),
            Filtro::texto($request, 'comentario') ?: null,
        );

        return back()->with('ok', 'Actividad cancelada.');
    }

    public function toggleFeatured(Activity $activity)
    {
        $activity->update(['destacada' => ! $activity->destacada]);

        return back()->with('ok', $activity->destacada
            ? 'La actividad aparece ahora en el home.'
            : 'La actividad ya no aparece en el home.');
    }
}
