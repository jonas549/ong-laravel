<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@ong-laravel.test'],
            [
                'name' => 'Administración DPS',
                'password' => 'admin1234',
                'role' => User::ROL_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $organizador = User::updateOrCreate(
            ['email' => 'organizador@ong-laravel.test'],
            [
                'name' => 'Fundación Junto al Barrio',
                'password' => 'organizador1234',
                'role' => User::ROL_ORGANIZER,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        Organization::updateOrCreate(
            ['user_id' => $organizador->id],
            [
                'nombre' => 'Fundación Junto al Barrio',
                'slug' => 'fundacion-junto-al-barrio',
                'tipo' => 'Organización sin fines de lucro',
                'descripcion' => 'Trabajamos con comunidades urbanas para fortalecer el tejido social '
                    . 'de los barrios a través de proyectos de acompañamiento y formación.',
                'correo_contacto' => 'contacto@juntoalbarrio.cl',
                'enlace_web' => 'https://juntoalbarrio.cl',
                'enlace_red_social' => 'https://instagram.com/juntoalbarrio',
                'verificada' => true,
            ],
        );
    }
}
