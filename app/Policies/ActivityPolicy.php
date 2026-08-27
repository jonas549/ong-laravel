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
 * La regla, en una línea: una actividad es de la organización que la publicó, y
 * una organización es de su usuario. El administrador pasa por encima de todo
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
     * El mensaje importa: "no es de tu organización" le dice a un organizador
     * que se equivocó de ficha, y un 403 pelado no le dice nada.
     */
    private function respuesta(?User $user, Activity $activity): Response
    {
        return $this->esSuya($user, $activity)
            ? Response::allow()
            : Response::deny('Esta actividad no es de tu organización.');
    }

    /**
     * Se comprueba contra el `organization_id`, no contra el `user_id` de la
     * organización: si algún día una organización tiene más de una persona,
     * esta línea sigue siendo la correcta.
     *
     * El `?->` de la izquierda cubre a quien no tiene organización todavía; sin
     * el `!== null` explícito, dos nulos se darían por iguales y una actividad
     * huérfana quedaría abierta a cualquiera.
     */
    private function esSuya(?User $user, Activity $activity): bool
    {
        $suya = $user?->organization?->id;

        return $suya !== null && $activity->organization_id === $suya;
    }
}
