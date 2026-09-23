<?php

/**
 * Escenario de `difusion.mjs`: actividades de la organización sembrada con
 * los casos límite de la imagen de difusión. Se reconocen por el slug
 * `prueba-difusion-`. Se limpia pasando `$limpiar = true` antes.
 *
 *     php artisan tinker --execute="require base_path('pruebas/datos-difusion.php');"
 *     php artisan tinker --execute="$limpiar = true; require base_path('pruebas/datos-difusion.php');"
 *
 * - `sin-foto`: sin portada, con horas y de varios días.
 * - `larga`: titular y descripción al máximo, la región más larga, dirección
 *   larga y los cupos agotados.
 * - `online`: en línea, sin fecha y sin inscripción previa.
 * - `revision`: en revisión, para comprobar que no deja generarla.
 */

use App\Models\Activity;
use App\Models\Commune;
use App\Models\Organization;
use App\Models\Region;

Activity::withTrashed()->where('slug', 'like', 'prueba-difusion-%')->get()->each(fn ($a) => $a->forceDelete());

if (! empty($limpiar)) {
    echo 'DIFUSION-LIMPIA';

    return;
}

$org = Organization::whereHas('user', fn ($q) => $q->where('email', env('DPS_ORG', 'organizador@ong-laravel.test')))->firstOrFail();
$region = Region::orderByRaw('CHAR_LENGTH(nombre) DESC')->first();
$comunaLarga = Commune::where('region_id', $region->id)->orderByRaw('CHAR_LENGTH(nombre) DESC')->first();
$rm = Commune::whereHas('region', fn ($q) => $q->where('nombre', 'like', '%Metropolitana%'))->orderBy('nombre')->first() ?? Commune::first();

$base = [
    'organization_id' => $org->id,
    'estado' => 'publicada',
    'published_at' => now(),
    'formato' => 'Presencial',
];

$crear = function (string $slug, array $datos) use ($base) {
    $a = new Activity;
    // `$datos` primero: en una unión de arrays gana el de la izquierda.
    $a->forceFill($datos + $base + ['slug' => 'prueba-difusion-'.$slug])->save();

    return $a;
};

$ids = [];

$ids['sin-foto'] = $crear('sin-foto', [
    'titulo' => 'Taller de huertos comunitarios',
    'descripcion' => "Aprende a armar un huerto en tu barrio.\n\nTrae semillas y ganas de compartir.",
    'fecha_inicio' => now()->addDays(10)->toDateString(),
    'fecha_termino' => now()->addDays(12)->toDateString(),
    'hora_inicio' => '10:00',
    'hora_termino' => '13:00',
    'region_id' => $rm->region_id,
    'commune_id' => $rm->id,
    'direccion' => 'Sede vecinal, calle Los Aromos 123',
    'inscripcion_habilitada' => true,
    'cupos_totales' => 55,
    'cupos_disponibles' => 55,
    'imagen_portada' => null,
])->id;

$ids['larga'] = $crear('larga', [
    'titulo' => mb_substr(str_repeat('Gran jornada solidaria de recuperación del patrimonio comunitario del barrio ', 4), 0, 240),
    'descripcion' => mb_substr(str_repeat('Durante toda la mañana recorreremos el barrio junto a vecinas y vecinos para recuperar espacios públicos, pintar murales y compartir una once comunitaria. ', 8), 0, 1000),
    'fecha_inicio' => now()->addDays(20)->toDateString(),
    'fecha_termino' => now()->addDays(50)->toDateString(),
    'hora_inicio' => '09:00',
    'region_id' => $region->id,
    'commune_id' => $comunaLarga?->id,
    'direccion' => 'Junta de vecinos número 14, pasaje interior sin número, a un costado de la cancha techada y frente a la plaza principal del sector',
    'inscripcion_habilitada' => true,
    'cupos_totales' => 30,
    'cupos_disponibles' => 0,
])->id;

$ids['online'] = $crear('online', [
    'titulo' => 'Conversatorio sobre voluntariado',
    'descripcion' => 'Una conversación abierta y en línea.',
    'formato' => 'Online',
    'sin_fecha_definida' => true,
    'inscripcion_habilitada' => false,
])->id;

$ids['revision'] = $crear('revision', [
    'titulo' => 'Actividad en revisión',
    'descripcion' => 'Todavía no publicada.',
    'estado' => 'revision',
    'published_at' => null,
    'region_id' => $rm->region_id,
    'commune_id' => $rm->id,
])->id;

echo 'DIFUSION '.json_encode($ids + ['region' => $region->nombre, 'comuna' => $comunaLarga?->nombre]);
