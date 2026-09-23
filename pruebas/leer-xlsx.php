<?php

/*
 * Lee un .xlsx con el mismo OpenSpout que lo escribe y lo imprime en JSON,
 * una lista de filas. Lo usa `exportar-inscripciones.mjs`.
 *
 *   php pruebas/leer-xlsx.php archivo.xlsx
 */

require __DIR__.'/../vendor/autoload.php';

use OpenSpout\Reader\XLSX\Reader;

$reader = new Reader;
$reader->open($argv[1]);

$filas = [];

foreach ($reader->getSheetIterator() as $hoja) {
    foreach ($hoja->getRowIterator() as $fila) {
        $filas[] = $fila->toArray();
    }

    break;
}

$reader->close();

echo json_encode($filas, JSON_UNESCAPED_UNICODE);
