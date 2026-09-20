<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Las dos cuentas de desarrollo: un administrador y un organizador con su
 * organización.
 *
 * **Este seeder no debe correr en producción**, y `dps:instalar` no lo llama a
 * propósito. Pero estar fuera del despliegue no bastaba, y aquí es donde se
 * arreglan las dos cosas que lo hacían peligroso:
 *
 * 1. **Las contraseñas estaban en el código, en texto plano.** Ahora salen de
 *    `DPS_CLAVE_ADMIN` y `DPS_CLAVE_ORGANIZADOR` del `.env`, y si no están se
 *    genera una al azar y se imprime. Lo que no está escrito en el repositorio
 *    no se puede leer desde el repositorio.
 *
 * 2. **`updateOrCreate` pisaba la contraseña de una cuenta que ya existía.**
 *    Ésta era la de verdad: un `php artisan db:seed --force` en el servidor
 *    —que está en la lista de comandos de despliegue manual— devolvía la
 *    contraseña del administrador de producción a la del repositorio, sin
 *    decir nada y por mucho que alguien la hubiera cambiado antes. Ahora la
 *    contraseña **sólo se escribe al crear la cuenta**; si ya existe, se
 *    respeta la que tenga.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') && ! env('DPS_SEMBRAR_USUARIOS_DE_PRUEBA')) {
            $this->command?->warn(
                'UserSeeder no corre en producción. Si de verdad hace falta, '
                .'pon DPS_SEMBRAR_USUARIOS_DE_PRUEBA=true en el .env.'
            );

            return;
        }

        $this->cuenta(
            'admin@ong-laravel.test',
            'Administración DPS',
            User::ROL_ADMIN,
            'DPS_CLAVE_ADMIN',
        );

        $organizador = $this->cuenta(
            'organizador@ong-laravel.test',
            'Fundación Junto al Barrio',
            User::ROL_ORGANIZER,
            'DPS_CLAVE_ORGANIZADOR',
        );

        Organization::updateOrCreate(
            ['user_id' => $organizador->id],
            [
                'nombre' => 'Fundación Junto al Barrio',
                'slug' => 'fundacion-junto-al-barrio',
                'tipo' => 'Organización sin fines de lucro',
                'descripcion' => 'Trabajamos con comunidades urbanas para fortalecer el tejido social '
                    .'de los barrios a través de proyectos de acompañamiento y formación.',
                'correo_contacto' => 'contacto@juntoalbarrio.cl',
                'enlace_web' => 'https://juntoalbarrio.cl',
                'enlace_red_social' => 'https://instagram.com/juntoalbarrio',
                'verificada' => true,
            ],
        );
    }

    /**
     * Crea la cuenta si no está, y si está no le toca la contraseña.
     *
     * El resto de campos sí se refrescan: nombre y rol son datos del escenario
     * de desarrollo y no hay nada que proteger en ellos.
     */
    private function cuenta(string $correo, string $nombre, string $rol, string $variable): User
    {
        $usuario = User::where('email', $correo)->first();

        if ($usuario) {
            $usuario->forceFill([
                'name' => $nombre,
                'role' => $rol,
                'is_active' => true,
                'email_verified_at' => $usuario->email_verified_at ?? now(),
            ])->save();

            return $usuario;
        }

        $clave = (string) env($variable, '');

        if ($clave === '') {
            $clave = Str::password(16);

            // Se imprime una sola vez, al crearla. No queda en ningún archivo.
            $this->command?->warn("Contraseña generada para {$correo}: {$clave}");
            $this->command?->line("Para fijarla, pon {$variable} en tu .env antes de sembrar.");
        }

        return User::create([
            'email' => $correo,
            'name' => $nombre,
            'password' => $clave,
            'role' => $rol,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
