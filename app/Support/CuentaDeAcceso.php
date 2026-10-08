<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Crear una cuenta de acceso desde el panel.
 *
 * La usan las dos pantallas que crean cuentas a mano: Panel → Usuarios y, desde
 * el punto 1 del 08/10, Panel → Organizaciones → Nueva, que puede crear la
 * cuenta de la organización en el mismo paso. Las reglas y el alta viven aquí
 * para que las dos no se separen: si una dejara pasar un correo repetido o una
 * contraseña de 100 caracteres, sería la puerta de atrás de la otra.
 */
class CuentaDeAcceso
{
    public const CORREO_REPETIDO = 'Ya existe una cuenta con ese correo. Búscala en Usuarios en vez de crear otra.';

    /** @return array<string, array<int, mixed>> */
    public static function reglas(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            // El tope de 72 no es capricho: bcrypt ignora lo que pase de ahí, y
            // sin él se puede elegir una de 100 y entrar luego con los primeros 72.
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ];
    }

    /** @return array<string, string> */
    public static function mensajes(): array
    {
        return ['email.unique' => self::CORREO_REPETIDO];
    }

    /** @return array<string, string> */
    public static function atributos(): array
    {
        return ['name' => 'el nombre', 'email' => 'el correo', 'password' => 'la contraseña'];
    }

    /**
     * Activa y con el correo verificado: la crea un administrador, que ya sabe
     * de quién es.
     *
     * `email_verified_at` no está en el `$fillable` de User, así que con
     * `create()` se descartaba sin avisar; por eso `forceFill`.
     *
     * @param  array{name: string, email: string, password: string}  $datos
     */
    public static function crear(array $datos, string $rol): User
    {
        $usuario = new User;

        $usuario->forceFill([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => $datos['password'],
            'role' => $rol,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        return $usuario;
    }
}
