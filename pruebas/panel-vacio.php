<?php

/*
 * Con la base vacía, ¿la portada enseña ceros o se saca datos de la manga?
 *
 * Es la comprobación que delata un valor de ejemplo: mientras haya filas, un
 * número escrito a mano puede parecer correcto por casualidad. Sin filas no hay
 * casualidad que valga.
 *
 * Corre dentro de una transacción que SIEMPRE se deshace, así que borra de
 * verdad —para que las consultas vean una base vacía— sin perder nada.
 *
 *   php artisan tinker --execute="require base_path('pruebas/panel-vacio.php');"
 */

use App\Services\ResumenPanel;
use Illuminate\Support\Facades\DB;

$fallos = 0;

$comprobar = function (string $que, bool $bien) use (&$fallos) {
    $fallos += $bien ? 0 : 1;
    echo '  '.str_pad($que, 52).($bien ? 'OK' : '*** MAL ***').PHP_EOL;
};

DB::beginTransaction();

try {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    foreach (['registrations', 'activity_status_logs', 'activities', 'organizations', 'email_logs'] as $tabla) {
        DB::table($tabla)->delete();
    }
    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    $resumen = app(ResumenPanel::class);
    $kpis = $resumen->kpis();

    echo PHP_EOL.'=== Base vacía: todo tiene que dar cero ==='.PHP_EOL.PHP_EOL;

    foreach (['actividades', 'pendientes', 'publicadas', 'inscritos', 'organizaciones', 'organizacionesActivas'] as $clave) {
        $comprobar("{$clave} = 0", $kpis[$clave] === 0);
    }

    $comprobar('los cinco estados siguen listados, en cero', $kpis['porEstado']->count() === 5 && $kpis['porEstado']->sum() === 0);

    $evolucion = $resumen->evolucion();
    $comprobar('el gráfico trae 12 semanas, todas vacías', count($evolucion['puntos']) === 12 && $evolucion['totalInscripciones'] === 0);
    $comprobar('el techo del eje nunca es cero (no se divide por 0)', $evolucion['techo'] >= 1);

    $comprobar('sin nada esperando revisión', $resumen->pendientesDeRevision()->isEmpty());
    $comprobar('sin inscripciones que listar', $resumen->ultimasInscripciones()->isEmpty());

    // Las alertas de actividades atrasadas y correos fallidos tienen que
    // callarse; la de organizaciones sin verificar también, porque no hay.
    $comprobar('ninguna alerta que dar', $resumen->alertas() === []);

    echo PHP_EOL.($fallos === 0 ? "  Base vacía: correcto, todo en cero.\n" : "  {$fallos} MAL\n");
} finally {
    DB::rollBack();
    echo '  (transacción deshecha: la base queda como estaba)'.PHP_EOL;
}

echo 'VACIO_FALLOS='.$fallos.PHP_EOL;
