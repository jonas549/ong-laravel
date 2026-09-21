<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Importa el listado histórico del cliente en el servidor, sin que nadie
 * tenga que entrar a correr el comando.
 *
 * **Es una migración y no un paso de `dps:instalar` a propósito.**
 * `dps:instalar` corre cada cinco minutos con el cron de despliegue; el
 * importador no duplica, pero una organización que la ONG renombre después
 * dejaría de coincidir por nombre y la pasada siguiente la volvería a crear.
 * Una migración corre una sola vez y queda apuntada en `migrations`.
 *
 * Si falla a medias no se apunta y el cron la reintenta: el importador es
 * idempotente, así que retomar no duplica nada.
 *
 * Con la marquesina en modo manual (por defecto) esto no cambia el home: las
 * organizaciones aparecen en el buscador para reclamarlas y en el panel para
 * elegirlas, no en la tira.
 */
return new class extends Migration
{
    public function up(): void
    {
        $carpeta = base_path('database/importacion/organizaciones');

        if (! is_file("{$carpeta}/organizaciones-importar.csv")) {
            return;
        }

        $codigo = Artisan::call('dps:importar-organizaciones', [
            'archivo' => "{$carpeta}/organizaciones-importar.csv",
            '--logos' => "{$carpeta}/logos",
        ]);

        // El resumen queda en el log: en el servidor no hay nadie mirando la
        // salida del cron.
        Log::info('Importación del listado histórico de organizaciones', [
            'resultado' => trim(collect(explode("\n", Artisan::output()))->filter(fn ($l) => str_contains($l, 'Listo'))->implode(' ')),
        ]);

        if ($codigo !== 0) {
            throw new RuntimeException('La importación de organizaciones falló: '.Artisan::output());
        }
    }

    public function down(): void
    {
        // No se deshace: a estas alturas alguna puede estar ya reclamada.
    }
};
