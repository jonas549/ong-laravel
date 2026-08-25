<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublishActivityRequest;
use App\Models\AccessLog;
use App\Models\Activity;
use App\Models\Commune;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityCatalogService;
use App\Services\ActivityModerationService;
use App\Services\ControlDeAcceso;
use App\Services\CorreoTransaccional;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El wizard público de cinco pasos.
 *
 * Los pasos se navegan en el cliente con Alpine (no hay una request por
 * paso) y todo se envía junto al final. Las dos rutas llevan el middleware
 * `invitado`: el wizard crea siempre una cuenta nueva, así que a quien ya
 * tiene sesión abierta se le manda a sumar la actividad desde su cuenta. Publicar y crear la cuenta ocurren
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

        // El paso 4 ofrece reusar el correo de la cuenta como contacto público.
        $correoPublico = $request->boolean('usar_correo_cuenta')
            ? $datos['email']
            : ($datos['correo_contacto'] ?? $datos['email']);

        try {
            $actividad = DB::transaction(function () use ($datos, $request, $correoPublico) {
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
                    'logo_path' => ($logo = $request->file('org_logo'))
                        ? 'storage/'.$logo->store('organizaciones', 'public')
                        : null,
                    'correo_contacto' => $correoPublico,
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
                    'region_id' => $comuna?->region_id ?? ($datos['region_id'] ?? null),
                    'commune_id' => $comuna?->id,
                    'direccion' => $datos['direccion'] ?? null,
                    'participantes_estimados' => $datos['participantes_estimados'] ?? null,
                    'cupos_totales' => $datos['cupos_totales'] ?? null,
                    'cupos_disponibles' => $datos['cupos_totales'] ?? null,
                    // El paso 4 no lo pregunta: si pide inscripción, es abierta.
                    'abierta_publico' => true,
                    'inscripcion_habilitada' => $request->boolean('inscripcion_habilitada'),
                    'tiene_accesibilidad' => $request->boolean('tiene_accesibilidad'),
                    'accesibilidad_detalle' => $request->boolean('tiene_accesibilidad')
                        ? ($datos['accesibilidad_detalle'] ?? null)
                        : null,
                    'publico_otro' => $datos['publico_otro'] ?? null,
                    'imagen_portada' => ($portada = $request->file('imagen'))
                        ? 'storage/'.$portada->store('actividades', 'public')
                        : null,
                    'correo_contacto' => $correoPublico,
                    'enlace_red_social' => $datos['enlace_red_social'] ?? null,
                    'enlace_web' => $datos['enlace_web'] ?? null,
                    'estado' => 'borrador',
                ]);

                $terminos = collect($datos['temas'] ?? [])
                    ->merge($datos['publicos'] ?? [])
                    ->merge($datos['caracteristicas'] ?? [])
                    ->filter()
                    ->unique();

                $actividad->terms()->sync($terminos);

                foreach (array_filter($datos['colaboradores'] ?? []) as $i => $nombre) {
                    $actividad->collaborators()->create(['nombre' => $nombre, 'orden' => $i]);
                }

                return $actividad;
            });
        } catch (UniqueConstraintViolationException) {
            // Doble clic en "Enviar actividad", o el mismo correo enviado a la
            // vez por el wizard y por el registro. La transacción revierte, así
            // que no queda basura, pero antes se perdían los cinco pasos
            // rellenados detrás de un 500 con la excepción de MySQL en pantalla.
            throw ValidationException::withMessages([
                'email' => 'Ya existe una cuenta con ese correo. Entra con ella y suma la actividad desde tu cuenta.',
            ]);
        }

        // Fuera de la transacción: si el correo falla, la actividad ya existe.
        $moderacion->cambiar($actividad, 'revision', null);

        $nuevoUsuario = $actividad->organization->user;

        // La cuenta se acaba de crear con la contraseña que eligió aquí mismo:
        // dejarla fuera obligaba a volver a escribirla para ver su actividad, y
        // el enlace del correo de verificación rebotaba al login.
        Auth::login($nuevoUsuario);
        $request->session()->regenerate();

        $nuevoUsuario->forceFill(['last_login_at' => now()])->save();
        app(ControlDeAcceso::class)->exito($request, AccessLog::PANEL_ORGANIZADOR, $nuevoUsuario);

        app(CorreoTransaccional::class)->bienvenida($nuevoUsuario);

        // La cuenta nace aquí, así que aquí sale también el correo de
        // verificación. No bloquea nada: es para confirmar la dirección.
        event(new Registered($nuevoUsuario));

        return redirect()
            ->route('publish.done', $actividad)
            ->with('ok', 'Recibimos tu actividad. Te avisaremos por correo cuando esté revisada.');
    }

    public function done(Activity $activity)
    {
        $activity->load(['organization', 'commune']);

        return view('public.publish.done', compact('activity'));
    }

    /**
     * Los mismos catálogos que usa la edicion en "Mi cuenta", más el tipo de
     * organización, que sólo se pregunta al publicar por primera vez.
     *
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return app(ActivityCatalogService::class)->todos()
            + ['tiposOrg' => Organization::TIPOS];
    }
}
