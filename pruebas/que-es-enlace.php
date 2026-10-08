<?php

/*
 * Punto 9 del 08/10 — la migración del enlace de «¿Qué es el Patrimonio
 * Social?» sólo toca lo que sigue en `/actividades`.
 *
 * Prepara la fila con cada estado, corre el `up()` de la migración y repone
 * la fila como estaba. Escribe en la base: no correr contra producción.
 *
 *   php artisan tinker --execute="require base_path('pruebas/que-es-enlace.php');"
 */

use Illuminate\Support\Facades\DB;

$ok = 0;
$mal = 0;
$di = function (string $q, bool $bien, string $extra = '') use (&$ok, &$mal) {
    $bien ? $ok++ : $mal++;
    echo '  '.str_pad($q, 64).' '.($bien ? 'OK' : '*** MAL ***').' '.$extra.PHP_EOL;
};

$migracion = require base_path('database/migrations/2025_02_07_000001_que_es_lleva_al_sitio_del_dps.php');
$nuevo = 'https://diadelpatrimoniosocial.cl/que-es/';

$fila = DB::table('home_sections')->where('clave', 'que-es')->first();
$antes = ['contenido' => $fila->contenido, 'borrador' => $fila->borrador];

$poner = fn (?array $contenido, ?array $borrador) => DB::table('home_sections')->where('id', $fila->id)->update([
    'contenido' => $contenido === null ? null : json_encode($contenido, JSON_UNESCAPED_UNICODE),
    'borrador' => $borrador === null ? null : json_encode($borrador, JSON_UNESCAPED_UNICODE),
]);
$leer = fn () => DB::table('home_sections')->where('id', $fila->id)->first();

try {
    // 1. Guardado con el destino de siempre, en lo publicado y en el borrador.
    $poner(['titulo_antes' => 'Hola', 'cta_enlace' => '/actividades'], ['cta_enlace' => '/actividades']);
    $migracion->up();
    $f = $leer();
    $c = json_decode($f->contenido, true);
    $di('Lo publicado en /actividades pasa al sitio del DPS', ($c['cta_enlace'] ?? null) === $nuevo, $c['cta_enlace'] ?? '');
    $di('Sin tocar los demás campos', ($c['titulo_antes'] ?? null) === 'Hola');
    $di('El borrador también', (json_decode($f->borrador, true)['cta_enlace'] ?? null) === $nuevo);

    // 2. Un destino puesto a mano por la ONG no se pisa.
    $poner(['cta_enlace' => 'https://otro.cl/'], null);
    $migracion->up();
    $di('Un enlace elegido por la ONG no se toca', (json_decode($leer()->contenido, true)['cta_enlace'] ?? null) === 'https://otro.cl/');
    $di('Y el borrador vacío sigue vacío', $leer()->borrador === null);

    // 3. Sin nada guardado: lo resuelve el valor por defecto del catálogo.
    $poner(null, null);
    $migracion->up();
    $di('Sin fila guardada no escribe nada', $leer()->contenido === null);
    $seccion = App\Models\HomeSection::where('clave', 'que-es')->first();
    $di('Y el botón lleva al sitio del DPS por defecto', $seccion->enlace('cta_enlace') === $nuevo, $seccion->enlace('cta_enlace'));
} finally {
    DB::table('home_sections')->where('id', $fila->id)->update($antes);
}

echo PHP_EOL."{$ok} OK, {$mal} MAL".PHP_EOL;
