<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Quién puede tocar la cuenta de quién.
 *
 * **No lleva `before()` de administrador, y es a propósito.** Los otros dos
 * permisos se lo saltan todo cuando quien pregunta es admin; aquí las tres
 * reglas que importan son precisamente límites *a* un administrador sobre su
 * propia cuenta. Un `before()` que devolviera `true` para admin las borraría
 * las tres de un plumazo, que es justo el error que este archivo evita.
 *
 * La ficha de usuario del panel (`/admin/usuarios/{id}/editar`) ya vive detrás
 * de `role:admin`, así que aquí no se vuelve a preguntar si quien entra es
 * administrador: eso ya está decidido antes de llegar.
 */
class UserPolicy
{
    /**
     * El perfil propio, que es la única cuenta que alguien edita de sí mismo.
     *
     * `/mi-cuenta/perfil` y `/admin/perfil` no reciben ningún id por la URL:
     * trabajan siempre sobre `$request->user()`, así que la fuga clásica de
     * "cambio el número y edito a otro" no existe ahí. Este permiso está para
     * que siga sin existir el día que alguien añada un id a esa ruta.
     */
    public function updateSelf(User $user, User $objetivo): bool
    {
        return $user->id === $objetivo->id;
    }

    /**
     * Cambiarle el rol a alguien desde el panel.
     *
     * Quitarse a uno mismo la administración es quedarse fuera del panel sin
     * forma de volver a entrar por la interfaz.
     */
    public function changeRole(User $user, User $objetivo, string $rolNuevo): bool
    {
        return $user->id !== $objetivo->id || $rolNuevo === User::ROL_ADMIN;
    }

    /**
     * Asignarle una contraseña a otra persona.
     *
     * La propia no: para eso está el perfil, que pide la contraseña actual.
     * Permitirlo aquí reabriría el agujero que se cerró allí, porque una sesión
     * de admin robada podría cambiarla sin conocer la anterior.
     */
    public function changePassword(User $user, User $objetivo): Response
    {
        return $user->id === $objetivo->id
            ? Response::deny('Tu propia contraseña se cambia desde tu perfil, con la actual delante.')
            : Response::allow();
    }

    /** Desactivar la cuenta propia es cerrarse la puerta desde dentro. */
    public function toggleActive(User $user, User $objetivo): Response
    {
        return $user->id === $objetivo->id
            ? Response::deny('No puedes desactivar tu propia cuenta.')
            : Response::allow();
    }
}
