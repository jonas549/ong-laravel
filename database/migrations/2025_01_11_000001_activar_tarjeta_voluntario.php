<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enciende la tercera tarjeta del home, «Quiero ser voluntario».
 *
 * Venía comentada en el prototipo —«oculta por ahora»— y se sembró apagada
 * para replicarlo. El cliente la pidió encendida en la reunión del 2026-09-01.
 *
 * Va en una migración y no en el seeder porque `ContentSeeder` no lo ejecuta
 * nadie en el servidor: `dps:instalar` lo salta a propósito, por ser contenido
 * de ejemplo. El cron sí corre `migrate`, así que ésta es la única vía para que
 * el cambio llegue a producción sin que alguien tenga que acordarse de pulsar
 * un botón.
 *
 * **Sólo toca la fila si sigue como la dejó el seeder.** Si la ONG ya la
 * encendió, le cambió el enlace o la apagó a conciencia, aquí no se pisa nada:
 * el `where` sobre el enlace muerto es lo que distingue «nadie la ha tocado» de
 * «alguien decidió esto».
 */
return new class extends Migration
{
    /**
     * El enlace que traía el prototipo. No existe ningún `id="voluntario"` en
     * el sitio —ni en el fuente—, así que encenderla tal cual daba un botón
     * que no lleva a ninguna parte.
     */
    private const ENLACE_MUERTO = '#voluntario';

    private const ENLACE_NUEVO = '/actividades';

    public function up(): void
    {
        DB::table('participation_cards')
            ->where('titulo', 'Quiero ser voluntario')
            ->where('href', self::ENLACE_MUERTO)
            ->update(['activo' => true, 'href' => self::ENLACE_NUEVO]);
    }

    public function down(): void
    {
        // Se deshace sólo lo que puso `up()`, y con el mismo criterio: si el
        // enlace ya no es el que dejamos, aquí lo cambió alguien.
        DB::table('participation_cards')
            ->where('titulo', 'Quiero ser voluntario')
            ->where('href', self::ENLACE_NUEVO)
            ->update(['activo' => false, 'href' => self::ENLACE_MUERTO]);
    }
};
