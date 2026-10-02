<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Las filas nuevas del listado del cliente (30/09): siete organizaciones
 * nuevas y el logo de una que ya estaba sin él (Ciudad Emergente).
 *
 * Mismo camino que `2025_02_01_000007`, y por lo mismo es una migración y no
 * un paso de `dps:instalar`: corre una sola vez. El importador no pisa nada
 * —a una organización existente sin cuenta sólo le completa lo vacío, y una
 * con cuenta no se toca—, así que reintentarla tras un fallo no duplica.
 */
return new class extends Migration
{
    public function up(): void
    {
        $carpeta = base_path('database/importacion/organizaciones');
        $archivo = "{$carpeta}/organizaciones-nuevas-30-09.csv";

        if (! is_file($archivo)) {
            return;
        }

        $codigo = Artisan::call('dps:importar-organizaciones', [
            'archivo' => $archivo,
            '--logos' => "{$carpeta}/logos",
        ]);

        Log::info('Importación de las organizaciones nuevas del 30/09', [
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
