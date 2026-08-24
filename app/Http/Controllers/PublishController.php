<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublishActivityRequest;
use App\Models\Activity;
use App\Models\Commune;
use App\Models\Organization;
use App\Models\Region;
use App\Models\Setting;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Services\ActivityModerationService;
use Illuminate\Support\Facades\DB;

/**
 * El wizard público de cinco pasos.
 *
 * Los pasos se navegan en el cliente con Alpine (no hay una request por
 * paso) y todo se envía junto al final. Publicar y crear la cuenta ocurren
 * en la misma transacción, como en el prototipo.
 */
class PublishController extends Controller
{
    public function create()
    {
        abort_unless(Setting::get('publicacion_abierta', true), 403, 'La publicación de actividades está cerrada por ahora.');

        return view('public.publish.wizard', $this->catalogos());
    }

    public function store(PublishActivityRequest $request, ActivityModerationService $moderacion)
    {
        abort_unless(Setting::get('publicacion_abierta', true), 403);

        $datos = $request->validated();

        $actividad = DB::transaction(function () use ($datos, $request) {
            $usuario = User::create([
                'name' => $datos['org_nombre'],
                'email' => $datos['email'],
                'password' => $datos['password'],
                'role' => User::ROL_ORGANIZER,
                'is_active' => true,
            ]);

            $organizacion = Organization::create([
                'user_id' => $usuario->id,
                'nombre' => $datos['org_nombre'],
                'tipo' => $datos['org_tipo'],
                'tipo_otro' => $datos['org_tipo_otro'] ?? null,
                'descripcion' => $datos['org_descripcion'] ?? null,
                'num_voluntarios' => $datos['org_num_voluntarios'] ?? null,
                'unidad_educativa' => $datos['org_unidad_educativa'] ?? null,
                'correo_contacto' => $datos['correo_contacto'] ?? $datos['email'],
                'enlace_web' => $datos['enlace_web'] ?? null,
                'enlace_red_social' => $datos['enlace_red_social'] ?? null,
            ]);

            $comuna = Commune::find($datos['commune_id'] ?? null);

            $actividad = Activity::create([
                'organization_id' => $organizacion->id,
                'titulo' => $datos['titulo'],
                'descripcion' => $datos['descripcion'],
                'formato' => $datos['formato'],
                'fecha_inicio' => $datos['fecha_inicio'] ?? null,
                'hora_inicio' => $datos['hora_inicio'] ?? null,
                'hora_termino' => $datos['hora_termino'] ?? null,
                'sin_fecha_definida' => $request->boolean('sin_fecha_definida'),
                'region_id' => $comuna?->region_id,
                'commune_id' => $comuna?->id,
                'direccion' => $datos['direccion'] ?? null,
                'participantes_estimados' => $datos['participantes_estimados'] ?? null,
                'cupos_totales' => $datos['cupos_totales'] ?? null,
                'cupos_disponibles' => $datos['cupos_totales'] ?? null,
                'abierta_publico' => $request->boolean('abierta_publico'),
                'inscripcion_habilitada' => $request->boolean('inscripcion_habilitada'),
                'tiene_accesibilidad' => $request->boolean('tiene_accesibilidad'),
                'correo_contacto' => $datos['correo_contacto'] ?? $datos['email'],
                'enlace_red_social' => $datos['enlace_red_social'] ?? null,
                'enlace_web' => $datos['enlace_web'] ?? null,
                'estado' => 'borrador',
            ]);

            $terminos = collect($datos['temas'] ?? [])
                ->merge($datos['publicos'] ?? [])
                ->merge($datos['caracteristicas'] ?? [])
                ->merge($datos['accesos'] ?? [])
                ->filter()
                ->unique();

            $actividad->terms()->sync($terminos);

            foreach (array_filter($datos['colaboradores'] ?? []) as $i => $nombre) {
                $actividad->collaborators()->create(['nombre' => $nombre, 'orden' => $i]);
            }

            return $actividad;
        });

        // Fuera de la transacción: si el correo falla, la actividad ya existe.
        $moderacion->cambiar($actividad, 'revision', null);

        return redirect()
            ->route('publish.done', $actividad)
            ->with('ok', 'Recibimos tu actividad. Te avisaremos por correo cuando esté revisada.');
    }

    public function done(Activity $activity)
    {
        return view('public.publish.done', compact('activity'));
    }

    /** @return array<string, mixed> */
    private function catalogos(): array
    {
        return [
            'tiposOrg' => Organization::TIPOS,
            'formatos' => Activity::FORMATOS,
            'regiones' => Region::ordered()->with('communes')->get(),
            'temas' => TaxonomyTerm::grupo('tema')->activos()->ordered()->get(),
            'caracteristicas' => TaxonomyTerm::grupo('caracteristica')->activos()->ordered()->get(),
            'publicos' => TaxonomyTerm::grupo('publico')->activos()->ordered()->get(),
            'accesos' => TaxonomyTerm::grupo('acceso')->activos()->ordered()->get(),
            'limites' => TaxonomyTerm::LIMITES,
        ];
    }
}
