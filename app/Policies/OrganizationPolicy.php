<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Quién puede ver y editar una organización.
 *
 * **Todavía no hay pantalla que la use.** Hoy la ficha de la organización se
 * rellena desde el wizard y desde la primera actividad, y no existe una ruta
 * `/mi-cuenta/organizacion`; cuando exista, el guardián ya está puesto y no
 * habrá que acordarse de escribirlo.
 *
 * Se deja escrita a propósito y no "cuando haga falta": el hueco de fuga que
 * apareció en esta misma auditoría —la pantalla de "actividad enviada", que
 * era pública— nació exactamente así, de una pantalla añadida sin que hubiera
 * un sitio evidente donde preguntar de quién es esto.
 */
class OrganizationPolicy
{
    public function before(?User $user, string $ability): ?bool
    {
        return $user?->esAdmin() ? true : null;
    }

    /** Verla: cualquier cuenta de la organización. */
    public function view(?User $user, Organization $organization): bool
    {
        return $user !== null && $user->organization_id === $organization->id;
    }

    /**
     * Cambiar la ficha: sólo la cuenta principal. Con varias personas en la
     * organización, la ficha es de todas, y que cualquiera pudiera renombrarla
     * o cambiarle el logo sería abrírsela a quien se acaba de sumar.
     */
    public function update(?User $user, Organization $organization): Response
    {
        if (! $this->view($user, $organization)) {
            return Response::deny('Esa organización no es la tuya.');
        }

        return $organization->user_id === $user->id
            ? Response::allow()
            : Response::deny('Los datos de la organización los cambia su cuenta principal.');
    }
}
