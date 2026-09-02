<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateActivityRequest;
use App\Models\Activity;
use App\Models\ActivityCollaborator;
use App\Models\Commune;
use App\Services\ActivityCatalogService;
use App\Services\ActivityModerationService;
use App\Services\AprobacionAutomatica;
use App\Support\Filtro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MyActivityController extends Controller
{
    /**
     * El orden de los filtros es el del prototipo, no el de ESTADOS: ahí
     * "Borradores" va al final, después de "Canceladas".
     */
    private const ORDEN_FILTROS = [
        'Publicadas',
        'Estamos revisando',
        'Necesita ajustes',
        'Canceladas',
        'Borradores',
    ];

    public function index(Request $request)
    {
        $organizacion = $request->user()->organization;

        if (! $organizacion) {
            return view('account.activities.index', [
                'actividades' => collect(),
                'filtros' => collect(['Todas' => 0]),
                'filtroActivo' => 'Todas',
                'necesitanAjustes' => 0,
            ]);
        }

        $base = Activity::where('organization_id', $organizacion->id);

        $conteos = (clone $base)->selectRaw('estado, COUNT(*) n')->groupBy('estado')->pluck('n', 'estado');

        $porFiltro = collect(Activity::ESTADOS)
            ->mapWithKeys(fn ($meta, $clave) => [$meta['filtro'] => $conteos[$clave] ?? 0]);

        $filtros = collect(['Todas' => (clone $base)->count()])
            ->merge(collect(self::ORDEN_FILTROS)->mapWithKeys(fn ($f) => [$f => $porFiltro[$f] ?? 0]));

        $filtroActivo = Filtro::texto($request, 'filtro') ?: 'Todas';

        $estadoBuscado = collect(Activity::ESTADOS)
            ->search(fn ($m) => $m['filtro'] === $filtroActivo);

        $actividades = (clone $base)
            ->when($estadoBuscado, fn ($q) => $q->where('estado', $estadoBuscado))
            ->with(['commune', 'region', 'terms'])
            ->withCount(['registrations as inscritos' => fn ($q) => $q->where('estado', '!=', 'cancelado')])
            ->latest('updated_at')
            ->get();

        return view('account.activities.index', [
            'actividades' => $actividades,
            'filtros' => $filtros,
            'filtroActivo' => $filtroActivo,
            // Alimenta el aviso amarillo de "una actividad requiere tu atención".
            'necesitanAjustes' => $porFiltro['Necesita ajustes'] ?? 0,
        ]);
    }

    public function edit(Request $request, Activity $activity, ActivityCatalogService $catalogos)
    {
        $this->authorize('update', $activity);

        $activity->load(['terms', 'collaborators', 'commune']);

        return view('account.activities.edit', $catalogos->todos() + [
            'activity' => $activity,
            'tiposColaborador' => ActivityCollaborator::TIPOS,
        ]);
    }

    // El permiso lo comprueba UpdateActivityRequest::authorize(), que corre
    // antes que las reglas de validación. Repetirlo aquí sería una segunda
    // verdad que mantener, y la de aquí llegaría tarde.
    public function update(UpdateActivityRequest $request, Activity $activity)
    {
        $datos = $request->validated();
        $comuna = Commune::find($datos['commune_id'] ?? null);

        DB::transaction(function () use ($request, $activity, $datos, $comuna) {
            $activity->fill([
                'titulo' => $datos['titulo'],
                'descripcion' => $datos['descripcion'],
                'formato' => $datos['formato'],
                'sin_fecha_definida' => $request->boolean('sin_fecha_definida'),
                'fecha_inicio' => $datos['fecha_inicio'] ?? null,
                'fecha_termino' => $datos['fecha_termino'] ?? null,
                'hora_inicio' => $datos['hora_inicio'] ?? null,
                'hora_termino' => $datos['hora_termino'] ?? null,
                'region_id' => $comuna?->region_id,
                'commune_id' => $comuna?->id,
                'direccion' => $datos['direccion'] ?? null,
                'participantes_estimados' => $datos['participantes_estimados'] ?? null,
                'cupos_disponibles' => $datos['cupos_disponibles'] ?? null,
                'abierta_publico' => $request->boolean('abierta_publico'),
                'inscripcion_habilitada' => $request->boolean('inscripcion_habilitada'),
                'info_previa' => $datos['info_previa'] ?? null,
                'correo_contacto' => $datos['correo_contacto'] ?? null,
                'enlace_red_social' => $datos['enlace_red_social'] ?? null,
                'enlace_web' => $datos['enlace_web'] ?? null,
            ]);

            // El título sólo rehace el slug mientras la ficha no se haya publicado:
            // después ya hay enlaces circulando.
            if ($activity->isDirty('titulo') && ! $activity->published_at) {
                $activity->slug = Activity::slugUnico($activity->titulo);
            }

            if ($imagen = $request->file('imagen')) {
                $anterior = $activity->imagen_portada;

                $activity->imagen_portada = 'storage/'.$imagen->store('actividades', 'public');

                $this->borrarImagen($anterior);
            }

            // La accesibilidad queda en "sí" en cuanto se marca alguna medida.
            $activity->tiene_accesibilidad = ! empty($datos['accesos']);

            $activity->save();

            $activity->terms()->sync(
                collect($datos['temas'] ?? [])
                    ->merge($datos['publicos'] ?? [])
                    ->merge($datos['caracteristicas'] ?? [])
                    ->merge($datos['accesos'] ?? [])
                    ->filter()
                    ->unique()
            );

            $this->guardarColaboradores($activity, $datos['colaboradores'] ?? []);
        });

        return redirect()->route('account.activities.saved', $activity);
    }

    /** La pantalla "¡Tus cambios ya están publicados!" del prototipo. */
    public function saved(Request $request, Activity $activity)
    {
        $this->authorize('update', $activity);

        return view('account.activities.saved', compact('activity'));
    }

    /**
     * "Duplicar" del listado: copia la ficha como borrador. No arrastra las
     * inscripciones ni la fecha de publicación, que son de la original.
     */
    public function duplicate(Request $request, Activity $activity)
    {
        $this->authorize('duplicate', $activity);

        $activity->load(['terms', 'collaborators']);

        $copia = DB::transaction(function () use ($activity) {
            $copia = $activity->replicate();

            $copia->titulo = $this->tituloDeCopia($activity->titulo);
            $copia->slug = Activity::slugUnico($copia->titulo);
            $copia->estado = 'borrador';
            $copia->published_at = null;
            $copia->observaciones_revision = null;
            $copia->destacada = false;
            $copia->cupos_disponibles = $activity->cupos_totales;
            $copia->save();

            $copia->terms()->sync($activity->terms->pluck('id'));

            foreach ($activity->collaborators as $c) {
                $copia->collaborators()->create([
                    'nombre' => $c->nombre,
                    'tipo' => $c->tipo,
                    'orden' => $c->orden,
                ]);
            }

            return $copia;
        });

        return redirect()
            ->route('account.activities.edit', $copia)
            ->with('ok', 'Duplicamos la actividad como borrador. Revisa los datos antes de enviarla a revisión.');
    }

    public function cancel(Request $request, Activity $activity, ActivityModerationService $moderacion)
    {
        $this->authorize('cancel', $activity);

        $moderacion->cambiar($activity, 'cancelada', $request->user(), 'Cancelada por el organizador.');

        return redirect()
            ->route('account.activities.index')
            ->with('ok', 'La actividad fue cancelada.');
    }

    public function submitForReview(
        Request $request,
        Activity $activity,
        ActivityModerationService $moderacion,
        AprobacionAutomatica $aprobacion,
    ) {
        $this->authorize('submit', $activity);

        /*
         * Una actividad que vuelve de «necesita ajustes» SIEMPRE pasa por
         * revisión, por muchas publicadas que tenga la organización: si la
         * ONG pidió cambios, quiere ver cómo quedaron. Es la única excepción
         * a la aprobación automática y se decide aquí, que es donde se sabe
         * de dónde viene la actividad.
         */
        [$estado, $motivo] = $activity->estado === 'ajustes'
            ? ['revision', 'A revisión: vuelve de una petición de ajustes.']
            : $aprobacion->estadoAlEnviar($activity->organization);

        $moderacion->cambiar($activity, $estado, $request->user(), $motivo, automatica: $estado === 'publicada');

        return back()->with('ok', $estado === 'publicada'
            ? 'Tu actividad ya está publicada en el calendario.'
            : 'Enviamos tu actividad a revisión.');
    }

    /** "Jornada X" → "Jornada X (copia)" → "Jornada X (copia 2)" … */
    private function tituloDeCopia(string $titulo): string
    {
        $base = preg_replace('/\s*\(copia(?: \d+)?\)$/u', '', $titulo);
        $nombre = $base.' (copia)';
        $i = 2;

        while (Activity::withTrashed()->where('titulo', $nombre)->exists()) {
            $nombre = $base.' (copia '.$i.')';
            $i++;
        }

        return $nombre;
    }

    /**
     * Las filas del formulario llegan como [['nombre' => …, 'tipo' => …], …].
     * Se rehacen enteras: son pocas y así el orden queda como en pantalla.
     *
     * @param  array<int, array<string, string|null>>  $filas
     */
    private function guardarColaboradores(Activity $activity, array $filas): void
    {
        $activity->collaborators()->delete();

        $orden = 0;

        foreach ($filas as $fila) {
            $nombre = trim((string) ($fila['nombre'] ?? ''));

            if ($nombre === '') {
                continue;
            }

            $activity->collaborators()->create([
                'nombre' => $nombre,
                'tipo' => $fila['tipo'] ?? null,
                'orden' => $orden++,
            ]);
        }
    }

    /** Borra el archivo anterior, pero sólo si lo subimos nosotros. */
    private function borrarImagen(?string $ruta): void
    {
        if ($ruta && str_starts_with($ruta, 'storage/')) {
            Storage::disk('public')->delete(substr($ruta, strlen('storage/')));
        }
    }
}
