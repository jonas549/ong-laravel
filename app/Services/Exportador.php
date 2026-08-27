<?php

namespace App\Services;

use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Options as OpcionesCsv;
use OpenSpout\Writer\CSV\Writer as EscritorCsv;
use OpenSpout\Writer\XLSX\Writer as EscritorXlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta un listado del panel a Excel o a CSV.
 *
 * Estaba escrito dos veces —en los inscritos del organizador y en los del
 * admin— y el bloque G iba a necesitarlo once más. Aquí es uno.
 *
 * Se envía en streaming y no se arma en memoria: una exportación de todas las
 * inscripciones de una edición puede ser larga, y `streamDownload` deja que el
 * navegador empiece a recibir mientras la consulta sigue leyendo.
 */
class Exportador
{
    /**
     * @param  array<int, string>  $cabeceras
     * @param  iterable<int, array<int, mixed>>  $filas
     */
    public function descargar(string $formato, string $nombre, array $cabeceras, iterable $filas): StreamedResponse
    {
        return $formato === 'csv'
            ? $this->csv($nombre, $cabeceras, $filas)
            : $this->xlsx($nombre, $cabeceras, $filas);
    }

    /**
     * @param  array<int, string>  $cabeceras
     * @param  iterable<int, array<int, mixed>>  $filas
     */
    public function xlsx(string $nombre, array $cabeceras, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($cabeceras, $filas) {
            $escritor = new EscritorXlsx;
            $escritor->openToFile('php://output');

            $escritor->addRow(Row::fromValuesWithStyle($cabeceras, (new Style)->withFontBold(true)));

            foreach ($filas as $fila) {
                $escritor->addRow(Row::fromValues($this->normalizar($fila)));
            }

            $escritor->close();
        }, $this->archivo($nombre, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<int, string>  $cabeceras
     * @param  iterable<int, array<int, mixed>>  $filas
     */
    public function csv(string $nombre, array $cabeceras, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($cabeceras, $filas) {
            /*
             * Punto y coma, no coma. Excel en español interpreta la coma como
             * decimal y mete todas las columnas en la primera celda; con punto
             * y coma se abre bien. Un CSV que hay que importar a mano no sirve
             * para lo que sirve un CSV.
             *
             * Va en el constructor y no por asignación: `Options` es `readonly`,
             * así que tocarlo después lanza un error y la descarga acababa
             * siendo una página de error de 800 KB con extensión .csv.
             *
             * `SHOULD_ADD_BOM` se queda en true, que es el defecto: sin la
             * marca de orden de bytes, Excel abre el archivo en ANSI y las
             * tildes salen rotas.
             */
            $escritor = new EscritorCsv(new OpcionesCsv(FIELD_DELIMITER: ';'));
            $escritor->openToFile('php://output');

            $escritor->addRow(Row::fromValues($cabeceras));

            foreach ($filas as $fila) {
                $escritor->addRow(Row::fromValues($this->normalizar($fila)));
            }

            $escritor->close();
        }, $this->archivo($nombre, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Deja cada celda como texto plano y neutraliza las fórmulas.
     *
     * **Esto es lo importante de este método.** Una celda que empieza por `=`,
     * `+`, `-` o `@` la ejecuta Excel al abrir el archivo, así que un nombre
     * escrito como `=HYPERLINK(...)` en un formulario público acaba
     * ejecutándose en el ordenador de quien abre la exportación. Se le antepone
     * un apóstrofo, que Excel entiende como «esto es texto» y no muestra.
     *
     * @param  array<int, mixed>  $fila
     * @return array<int, string>
     */
    private function normalizar(array $fila): array
    {
        return array_map(function ($valor) {
            if ($valor === null || $valor === false) {
                return '';
            }

            if ($valor === true) {
                return 'Sí';
            }

            $texto = (string) $valor;

            return Str::startsWith($texto, ['=', '+', '-', '@', "\t", "\r"]) ? "'".$texto : $texto;
        }, $fila);
    }

    private function archivo(string $nombre, string $extension): string
    {
        return Str::slug($nombre).'-'.now()->format('Y-m-d').'.'.$extension;
    }
}
