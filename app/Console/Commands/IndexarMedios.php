<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\Biblioteca;
use Illuminate\Console\Command;

/**
 * Mete en la biblioteca las imágenes que ya venían con el proyecto.
 *
 * Es idempotente y **no mueve ni un archivo**: `public/img` se queda donde
 * está. Sólo deja una fila por archivo para que la biblioteca las pueda
 * enseñar y el selector las pueda elegir.
 *
 * Va en el arranque de `dps:instalar` para que una base limpia nazca con la
 * biblioteca llena, igual que nace con las plantillas de correo.
 */
class IndexarMedios extends Command
{
    protected $signature = 'dps:indexar-medios {--limpiar : Quita las filas cuyo archivo ya no está en el disco}';

    protected $description = 'Indexa en la biblioteca las imágenes de public/img, sin moverlas';

    public function handle(Biblioteca $biblioteca): int
    {
        $this->info('Indexando public/img …');

        $r = $biblioteca->indexarCodigo();

        $this->line("  archivos encontrados : {$r['total']}");
        $this->line("  nuevos               : {$r['nuevos']}");
        $this->line("  actualizados         : {$r['actualizados']}");

        if ($this->option('limpiar')) {
            $huerfanos = 0;

            foreach (Media::delCodigo()->get() as $medio) {
                if (! $medio->existe) {
                    $medio->forceDelete();
                    $huerfanos++;
                }
            }

            $this->line("  huérfanos quitados   : {$huerfanos}");
        }

        $total = Media::count();
        $peso = Media::sum('peso');

        $this->newLine();
        $this->info("Biblioteca: {$total} archivos, ".Media::pesoLegible((int) $peso).'.');

        return self::SUCCESS;
    }
}
