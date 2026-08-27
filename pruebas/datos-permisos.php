<?php

/*
 * Dos organizadores de distinta organización, cada uno con una actividad sin
 * publicar. Es el escenario mínimo para la pregunta que importa: ¿puede el
 * segundo abrir la ficha del primero cambiando el número de la URL?
 *
 * Se corre desde permisos.mjs con:
 *   php artisan tinker --execute="require base_path('pruebas/datos-permisos.php');"
 *
 * Idempotente: se puede repetir sin ensuciar la base.
 */

use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;

$hacer = function (string $letra, string $estado): array {
    $usuario = User::updateOrCreate(
        ['email' => "org-{$letra}@prueba.test"],
        [
            'name' => "Organizador {$letra}",
            'password' => 'prueba1234',
            'role' => User::ROL_ORGANIZER,
            'is_active' => true,
            'email_verified_at' => now(),
        ],
    );

    $organizacion = Organization::updateOrCreate(
        ['user_id' => $usuario->id],
        [
            'nombre' => "Organización {$letra}",
            'slug' => "organizacion-prueba-{$letra}",
            'tipo' => Organization::TIPOS[0],
        ],
    );

    $actividad = Activity::updateOrCreate(
        ['slug' => "actividad-de-prueba-{$letra}"],
        [
            'organization_id' => $organizacion->id,
            'titulo' => "Actividad de prueba {$letra}",
            'descripcion' => 'Creada por pruebas/permisos.mjs.',
            'formato' => Activity::FORMATOS[0],
            'sin_fecha_definida' => true,
            'estado' => $estado,
            'cupos_disponibles' => 10,
        ],
    );

    return ['usuario' => $usuario->id, 'actividad' => $actividad->id, 'slug' => $actividad->slug];
};

// A queda en revisión (es la que sale del wizard) y B en borrador.
$datos = ['a' => $hacer('a', 'revision'), 'b' => $hacer('b', 'borrador')];

// La lee permisos.mjs por stdout.
echo 'DATOS='.json_encode($datos).PHP_EOL;
