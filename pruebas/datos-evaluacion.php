<?php

/**
 * Escenario para las pruebas de la encuesta de evaluación.
 *
 * Deja tres actividades con las tres situaciones que hay que poder probar, y
 * borra lo que hubiera sembrado una pasada anterior. Se corre con:
 *
 *     php artisan tinker --execute="require base_path('pruebas/datos-evaluacion.php');"
 *
 * Y se limpia con el mismo archivo pasando `$limpiar = true` antes, igual que
 * el del calendario. Todas llevan el slug con prefijo `prueba-eval-`, que es
 * por lo que se reconocen.
 *
 * **Las fechas cuelgan de hoy**, no son fijas: una fecha escrita a mano caduca
 * y la prueba empieza a fallar por un motivo que no tiene nada que ver con lo
 * que mide.
 *
 * ── Este escenario y el del calendario NO pueden convivir ──
 *
 * Los dos siembran actividades **publicadas y con fecha del mes en curso**, y
 * `calendario-actividades.mjs` cuenta cuántas caen en cada casilla: dos de
 * éstas aterrizando en el mes le hacen contar seis donde espera cinco. No es un
 * fallo del calendario ni de la encuesta, es que los dos escenarios se pisan.
 * Por eso `calendario-actividades.mjs` limpia éste antes de sembrar el suyo.
 */

use App\Models\Activity;
use App\Models\ActivityEvaluation;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$prefijo = 'prueba-eval-';

/**
 * Se lleva por delante las actividades del escenario y todo lo que cuelga de
 * ellas, incluidas las fotos del disco privado, que no se van solas con la fila.
 */
$borrarEscenario = function () use ($prefijo) {
    $viejas = Activity::withTrashed()->where('slug', 'like', $prefijo.'%')->get();

    foreach ($viejas as $vieja) {
        foreach (ActivityEvaluation::where('activity_id', $vieja->id)->whereNotNull('foto_path')->get() as $conFoto) {
            Storage::disk('local')->delete($conFoto->foto_path);
        }

        ActivityEvaluation::where('activity_id', $vieja->id)->delete();
        $vieja->forceDelete();
    }

    return $viejas->count();
};

if (isset($limpiar) && $limpiar) {
    echo 'EVALUACION-LIMPIA '.$borrarEscenario().PHP_EOL;

    return;
}

// ── Limpiar lo de la pasada anterior ────────────────────────

$borrarEscenario();

$organizacion = Organization::first();

if (! $organizacion) {
    echo 'EVALUACION-ERROR no hay ninguna organización sembrada'.PHP_EOL;

    return;
}

// ── Las tres actividades ────────────────────────────────────

$crear = function (string $sufijo, string $titulo, array $extra) use ($prefijo, $organizacion) {
    /*
     * `$extra` va a la IZQUIERDA del `+`, y eso no es un detalle de estilo.
     *
     * El `+` entre arrays conserva el valor de la izquierda cuando la clave se
     * repite, al revés que `array_merge`. Con los valores por defecto delante,
     * el `estado => borrador` de la actividad sin publicar se perdía en
     * silencio y la sembraba publicada: la prueba de «el QR no aparece sin
     * publicar» fallaba sin que hubiera nada roto en la aplicación.
     */
    return Activity::create($extra + [
        'organization_id' => $organizacion->id,
        'titulo' => $titulo,
        'slug' => $prefijo.$sufijo,
        'descripcion' => 'Actividad sembrada por pruebas/datos-evaluacion.php.',
        'formato' => 'Presencial',
        'lugar' => 'Parque Municipal',
        'direccion' => 'Avenida Siempreviva 742',
        'estado' => 'publicada',
        'published_at' => now()->subDays(40),
    ]);
};

// 1. Abierta: terminó anteayer, dentro de los 30 días de plazo.
$abierta = $crear('abierta', 'Encuesta abierta', [
    'fecha_inicio' => now()->subDays(3)->toDateString(),
    'fecha_termino' => now()->subDays(2)->toDateString(),
]);

// 2. Cerrada: terminó hace 60 días, muy fuera del plazo de 30.
$cerrada = $crear('cerrada', 'Encuesta cerrada por plazo', [
    'fecha_inicio' => now()->subDays(61)->toDateString(),
    'fecha_termino' => now()->subDays(60)->toDateString(),
]);

// 3. Futura: empieza dentro de diez días. Sólo se nota con el ajuste puesto en
//    «desde el día de la actividad»; con el valor por defecto sale abierta.
$futura = $crear('futura', 'Encuesta de actividad futura', [
    'fecha_inicio' => now()->addDays(10)->toDateString(),
    'fecha_termino' => now()->addDays(10)->toDateString(),
]);

// 4. Sin publicar: tiene que dar 404 a quien no sea de la casa.
$borrador = $crear('borrador', 'Actividad sin publicar', [
    'fecha_inicio' => now()->subDays(3)->toDateString(),
    'estado' => 'borrador',
    'published_at' => null,
]);

// ── Unas cuantas respuestas, para los promedios del panel ───
//
// Las notas están elegidas a mano y no al azar: el promedio de experiencia
// tiene que dar 3,00 exacto y el de motivación 4,00, para poder comprobar el
// número que pinta la pantalla sin recalcularlo en la prueba.

$notas = [
    ['Ana Riquelme', 'ana.prueba@ejemplo.cl', 1, 5],
    ['Bruno Cifuentes', 'bruno.prueba@ejemplo.cl', 2, 3],
    ['Carla Ñuñez', 'carla.prueba@ejemplo.cl', 3, 4],
    ['Diego Álvarez', 'diego.prueba@ejemplo.cl', 4, 4],
    ['Elena Wörner', 'elena.prueba@ejemplo.cl', 5, 4],
];

foreach ($notas as [$nombre, $correo, $experiencia, $motivacion]) {
    ActivityEvaluation::create([
        'activity_id' => $abierta->id,
        'nombre' => $nombre,
        'correo' => $correo,
        'experiencia' => $experiencia,
        'motivacion' => $motivacion,
        'significado' => 'Respuesta de prueba de '.$nombre.'.',
        'como_se_entero' => 'redes',
    ]);
}

$promedios = DB::table('activity_evaluations')
    ->where('activity_id', $abierta->id)
    ->selectRaw('AVG(experiencia) e, AVG(motivacion) m')
    ->first();

/*
 * Los ids van en la misma línea que los slugs.
 *
 * Las pruebas los necesitan para las rutas del panel y de la cuenta, y sacarlos
 * con un `tinker` desde JavaScript obliga a escribir el nombre completo de la
 * clase dentro de una cadena de JS dentro de otra de shell: tres capas de
 * barras invertidas, y basta con que una se pierda por el camino para que la
 * consulta devuelva vacío sin decir por qué. Aquí ya están.
 */
echo 'EVALUACION-LISTA '
    .$abierta->slug.' '
    .$cerrada->slug.' '
    .$futura->slug.' '
    .$borrador->slug.' '
    .'ids='.$abierta->id.','.$cerrada->id.','.$futura->id.','.$borrador->id.' '
    .'exp='.number_format((float) $promedios->e, 2, '.', '').' '
    .'mot='.number_format((float) $promedios->m, 2, '.', '')
    .PHP_EOL;
