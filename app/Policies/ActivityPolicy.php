<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Quién puede hacer qué con una actividad.
 *
 * Antes esto era un `autorizar()` privado copiado en dos controladores. Estaba
 * bien escrito, pero repetido: cualquier pantalla nueva que recibiera una
 * actividad por la URL nacía sin comprobación y nada lo señalaba. Aquí la regla
 * es una sola y los controladores la piden por su nombre.
 *
 * La regla, en una línea: una actividad es de la cuenta que la creó, dentro de
 * su organización (o de la cuenta principal, si se quedó sin autor). El administrador pasa por encima de todo
 * —para eso modera—, y eso lo resuelve `before()`.
 */
class ActivityPolicy
{
    /**
     * El panel de administración modera todas las actividades del sitio, así
     * que no tiene sentido preguntarle a cada permiso si un admin puede.
     *
     * Devuelve `null` —y no `false`— cuando no es admin: `null` significa
     * "sigue preguntando", que es lo que deja pasar al resto de los métodos.
     */
    public function before(?User $user, string $ability): ?bool
    {
        return $user?->esAdmin() ? true : null;
    }

    /**
     * Ver la ficha pública.
     *
     * El usuario es opcional porque `/actividades/{slug}` es una ruta abierta:
     * aquí llega tanto quien tiene sesión como quien no.
     */
    public function view(?User $user, Activity $activity): bool
    {
        return $activity->estado === 'publicada' || $this->esSuya($user, $activity);
    }

    public function update(?User $user, Activity $activity): Response
    {
        return $this->respuesta($user, $activity);
    }

    /** Enviar a revisión, cancelar y duplicar: son cambios de estado suyos. */
    public function submit(?User $user, Activity $activity): Response
    {
        return $this->respuesta($user, $activity);
    }

    public function cancel(?User $user, Activity $activity): Response
    {
        return $this->respuesta($user, $activity);
    }

    public function duplicate(?User $user, Activity $activity): Response
    {
        return $this->respuesta($user, $activity);
    }

    /**
     * Descargar el QR de la encuesta para imprimirlo.
     *
     * Va con permiso aunque lo que codifica sea una dirección pública. No es
     * por secreto: es porque el QR es material de trabajo del organizador —lo
     * imprime y lo pega en su actividad— y una descarga abierta invita a que
     * cualquiera fabrique carteles de una actividad ajena.
     */
    public function qr(?User $user, Activity $activity): Response
    {
        return $this->respuesta($user, $activity);
    }

    /**
     * La lista de inscritos, su exportación y los cupos.
     *
     * Las inscripciones no tienen permiso propio porque no se llegan a tocar
     * nunca por su cuenta: siempre se piden a través de su actividad
     * (`$activity->registrations()`), así que quien manda es la actividad. Un
     * permiso aparte sería una segunda verdad que mantener.
     */
    public function manageParticipants(?User $user, Activity $activity): Response
    {
        return $this->respuesta($user, $activity);
    }

    /**
     * Las evaluaciones que dejaron los asistentes, y sus fotografías.
     *
     * Hasta el 2026-09-20 esto era sólo del administrador. Lo abrió el cliente
     * con una condición que aquí es la línea entera: **el organizador ve las de
     * SUS actividades y ninguna más**. Es la misma regla que los inscritos, así
     * que se apoya en la misma comprobación y no en una segunda parecida.
     *
     * Las fotos siguen en el disco privado y se sirven por una ruta que pide
     * este permiso: abrir la pantalla no abre los archivos.
     */
    public function viewEvaluations(?User $user, Activity $activity): Response
    {
        return $this->respuesta($user, $activity);
    }

    /**
     * El mensaje importa: "no es de tu organización" le dice a un organizador
     * que se equivocó de ficha, y un 403 pelado no le dice nada.
     */
    private function respuesta(?User $user, Activity $activity): Response
    {
        if ($this->esSuya($user, $activity)) {
            return Response::allow();
        }

        // De su organización pero de otra persona: decirle eso, no que es ajena.
        return $user?->organization_id !== null && $activity->organization_id === $user?->organization_id
            ? Response::deny('Esta actividad la publicó otra cuenta de tu organización. Cada cuenta gestiona sus propias actividades.')
            : Response::deny('Esta actividad no es de tu organización.');
    }

    /**
     * Es suya si es la cuenta a la que le toca (`Activity::responsable()`):
     * su autora mientras siga en la organización, o la cuenta principal si la
     * actividad se quedó sin autor. **Ya no basta con ser de la misma
     * organización**: con varias cuentas por organización, cada persona ve y
     * edita sólo lo suyo.
     *
     * La organización se compara aparte y antes, como hasta ahora: sin el
     * `!== null` explícito, dos nulos se darían por iguales y una actividad
     * huérfana quedaría abierta a cualquiera.
     */
    private function esSuya(?User $user, Activity $activity): bool
    {
        $suya = $user?->organization?->id;

        if ($suya === null || $activity->organization_id !== $suya) {
            return false;
        }

        return $activity->responsable()?->id === $user->id;
    }
}
