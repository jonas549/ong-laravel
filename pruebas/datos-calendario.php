<?php

/*
 * El escenario del calendario: lo que la base sembrada no trae.
 *
 * Las siete actividades del seeder son todas de un solo día, todas en
 * septiembre y ninguna sin fecha estando publicada, así que con ellas no se
 * puede comprobar nada de lo que este bloque vino a resolver. Aquí se añade lo
 * que falta:
 *
 *   - una actividad de varios días **a caballo entre dos meses**, que es el
 *     caso que rompe cualquier calendario mal montado;
 *   - un día con cinco, para el «+N más»;
 *   - un fin de semana de dos días;
 *   - una en el mes siguiente, para que la flecha lleve a algo;
 *   - dos permanentes y una «por definir», **publicadas**, que son las que
 *     alimentan la franja de arriba.
 *
 * Se corre desde calendario-actividades.mjs con:
 *   php artisan tinker --execute="require base_path('pruebas/datos-calendario.php');"
 *
 * Y se limpia con el mismo archivo pasando `$limpiar = true` antes. Todas
 * llevan el slug con prefijo `zz-cal-`, que es por lo que se reconocen.
 *
 * Idempotente: se puede repetir sin ensuciar la base.
 */

use App\Models\Activity;
use App\Models\Organization;
use App\Models\TaxonomyTerm;

if (isset($limpiar) && $limpiar) {
    // Borrado de verdad y no en blando: son de usar y tirar.
    $fuera = Activity::withTrashed()->where('slug', 'like', 'zz-cal-%')->get();
    foreach ($fuera as $a) {
        $a->terms()->detach();
        $a->forceDelete();
    }
    echo 'CALENDARIO-LIMPIO '.$fuera->count()."\n";

    return;
}

$org = Organization::firstOrFail();
$temas = TaxonomyTerm::grupo('tema')->activos()->ordered()->take(4)->get();

/*
 * Las fechas cuelgan del mes de hoy y no van escritas a mano: si fueran fijas,
 * la prueba pasaría hasta que el calendario del mundo real las dejara atrás y
 * entonces fallaría sin que nadie hubiera tocado nada.
 */
$mes = now(config('app.zona_horaria', 'America/Santiago'))->startOfMonth();
$dia = fn (int $n) => $mes->copy()->addDays($n - 1)->toDateString();
$otroMes = fn (int $n) => $mes->copy()->addMonthNoOverflow()->addDays($n - 1)->toDateString();
$finDeMes = (int) $mes->copy()->endOfMonth()->format('j');

$conFecha = [
    // Varios días, cruzando al mes siguiente.
    ['zz-cal-feria', 'Feria patrimonial de barrio', $dia($finDeMes - 2), $otroMes(2), '10:00', '18:00'],
    // Cinco el mismo día, para el «+2 más».
    ['zz-cal-a', 'Limpieza del cerro', $dia(17), null, '09:00', '12:00'],
    ['zz-cal-b', 'Taller de compostaje', $dia(17), null, '10:30', null],
    ['zz-cal-c', 'Colecta de abrigo', $dia(17), null, '14:00', '17:00'],
    ['zz-cal-d', 'Cine comunitario al aire libre', $dia(17), null, '19:00', null],
    ['zz-cal-e', 'Ronda de cuentacuentos', $dia(17), null, null, null],
    // Dos días seguidos.
    ['zz-cal-finde', 'Mingako de pintura mural', $dia(5), $dia(6), '11:00', '16:00'],
    // El mes siguiente, para que la flecha lleve a algo.
    ['zz-cal-otro', 'Huerto vecinal de primavera', $otroMes(10), null, '09:30', null],
];

foreach ($conFecha as $i => [$slug, $titulo, $inicio, $termino, $desde, $hasta]) {
    $a = Activity::updateOrCreate(['slug' => $slug], [
        'organization_id' => $org->id,
        'titulo' => $titulo,
        'descripcion' => 'Actividad de prueba del calendario.',
        'formato' => 'Presencial',
        'fecha_inicio' => $inicio,
        'fecha_termino' => $termino,
        'hora_inicio' => $desde,
        'hora_termino' => $hasta,
        'sin_fecha_definida' => false,
        'estado' => 'publicada',
        'published_at' => now(),
        'region_id' => 1,
        'commune_id' => 1,
    ]);
    $a->terms()->sync([$temas[$i % $temas->count()]->id]);
}

$sinFecha = [
    ['zz-cal-perm-1', 'Biblioteca abierta del barrio', true],
    ['zz-cal-perm-2', 'Banco de alimentos permanente', true],
    ['zz-cal-def-1', 'Gran encuentro de voluntariado', false],
];

foreach ($sinFecha as $i => [$slug, $titulo, $permanente]) {
    $a = Activity::updateOrCreate(['slug' => $slug], [
        'organization_id' => $org->id,
        'titulo' => $titulo,
        'descripcion' => 'Actividad de prueba del calendario.',
        'formato' => 'Presencial',
        'fecha_inicio' => null,
        'fecha_termino' => null,
        'hora_inicio' => null,
        'hora_termino' => null,
        'sin_fecha_definida' => $permanente,
        'estado' => 'publicada',
        'published_at' => now(),
        'region_id' => 1,
        'commune_id' => 1,
    ]);
    $a->terms()->sync([$temas[$i % $temas->count()]->id]);
}

echo 'CALENDARIO-LISTO '.$mes->format('Y-m')."\n";
