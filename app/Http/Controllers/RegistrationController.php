<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrationRequest;
use App\Models\Activity;
use App\Models\Registration;
use App\Models\Setting;
use App\Services\CorreoTransaccional;
use Illuminate\Support\Facades\DB;

class RegistrationController extends Controller
{
    public function store(RegistrationRequest $request, Activity $activity)
    {
        if (! Setting::get('inscripciones_abiertas', true)) {
            return back()->with('error', 'Las inscripciones están cerradas por ahora.');
        }

        if (! $activity->puedeRecibirInscripciones()) {
            return back()->with('error', 'Esta actividad no está recibiendo inscripciones.');
        }

        $datos = $request->validated();

        $yaInscrito = $activity->registrations()
            ->where('correo', $datos['correo'])
            ->where('estado', '!=', 'cancelado')
            ->exists();

        if ($yaInscrito) {
            return back()->with('error', 'Ese correo ya está inscrito en esta actividad.');
        }

        $inscripcion = DB::transaction(function () use ($activity, $datos, $request) {
            // Bloqueo de fila: sin esto, dos inscripciones simultáneas podrían
            // pasar del cupo cuando quede solo uno disponible.
            $bloqueada = Activity::whereKey($activity->id)->lockForUpdate()->first();

            if ($bloqueada->cupos_disponibles !== null && $bloqueada->cupos_disponibles <= 0) {
                abort(409, 'Se acabaron los cupos.');
            }

            $inscripcion = Registration::create([
                'activity_id' => $bloqueada->id,
                'nombre' => $datos['nombre'],
                'correo' => $datos['correo'],
                'telefono' => $datos['telefono'] ?? null,
                'es_mayor_edad' => $request->boolean('es_mayor_edad'),
                'estado' => 'pendiente',
            ]);

            if ($bloqueada->cupos_disponibles !== null) {
                $bloqueada->decrement('cupos_disponibles');
            }

            return $inscripcion;
        });

        // Fuera de la transacción: la inscripción ya está guardada, y un fallo
        // de correo no debe deshacerla.
        $inscripcion->load('activity.organization.user', 'activity.commune', 'activity.region');

        $correos = app(CorreoTransaccional::class);
        $correos->inscripcionConfirmada($inscripcion);
        $correos->nuevaInscripcion($inscripcion);

        /*
         * Punto 7 del 30/09: antes volvía a la misma ficha con un aviso arriba
         * y el formulario debajo, como si no hubiera pasado nada. Ahora va a
         * una pantalla propia que lo confirma y ofrece compartir que se es
         * parte del Día del Patrimonio Social.
         */
        return redirect()
            ->route('registrations.soy-parte', $activity)
            ->with('inscrito', ['nombre' => $inscripcion->nombre, 'correo' => $inscripcion->correo]);
    }

    /**
     * «Soy parte del DPS»: la imagen fija de la edición para descargar o
     * compartir, el texto del post y el kit de difusión.
     *
     * Recién inscrito (`inscrito` en la sesión) lleva arriba la confirmación.
     * Sin eso —un enlace guardado, o desde un correo— enseña sólo la parte de
     * compartir: no hay nada de nadie que esconder.
     */
    public function soyParte(Activity $activity)
    {
        abort_unless($activity->estado === 'publicada', 404);

        $web = rtrim((string) config('app.url'), '/');

        return view('public.activities.soy-parte', [
            'activity' => $activity,
            'inscrito' => session('inscrito'),
            'texto' => 'Me sumo a la celebración nacional del Día del Patrimonio Social. ¡Súmate tú también! '.$web,
            'kit' => Setting::get('kit_difusion_url'),
        ]);
    }

    public function cancel(string $token)
    {
        $inscripcion = Registration::where('token', $token)->firstOrFail();

        if ($inscripcion->estado === 'cancelado') {
            return redirect()
                ->route('activities.show', $inscripcion->activity)
                ->with('ok', 'Tu inscripción ya estaba cancelada.');
        }

        DB::transaction(function () use ($inscripcion) {
            $inscripcion->update(['estado' => 'cancelado']);

            $actividad = $inscripcion->activity;

            if ($actividad && $actividad->cupos_disponibles !== null) {
                $actividad->increment('cupos_disponibles');
            }
        });

        return redirect()
            ->route('activities.show', $inscripcion->activity)
            ->with('ok', 'Cancelamos tu inscripción.');
    }
}
