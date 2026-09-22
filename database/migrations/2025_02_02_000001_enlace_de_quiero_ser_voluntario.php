<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * C5 de la sexta tanda: «Quiero ser voluntario» lleva a las oportunidades de
 * Voluntariados Chile.
 *
 * En producción la tarjeta apuntaba a `voluntariadochile.cl` —sin la «s» del
 * dominio de verdad—, escrito a mano desde el panel. El enlace ya se edita en
 * Contenido → Tarjetas de «¿cómo participar?»; esto sólo lo corrige en las
 * bases que ya existen, porque `ContentSeeder` no lo ejecuta nadie en el
 * servidor.
 *
 * **Sólo toca la fila si tiene uno de los enlaces conocidos**: el mal escrito,
 * el que dejó la migración del 2026-09-01 y el muerto del prototipo. Si la ONG
 * la hubiera apuntado a otro sitio a conciencia, no se pisa.
 */
return new class extends Migration
{
    private const NUEVO = 'https://voluntariadoschile.cl/oportunidades';

    private const CONOCIDOS = [
        'https://voluntariadochile.cl/oportunidades',
        'http://voluntariadochile.cl/oportunidades',
        'voluntariadochile.cl/oportunidades',
        '/actividades',
        '#voluntario',
    ];

    public function up(): void
    {
        DB::table('participation_cards')
            ->where('titulo', 'Quiero ser voluntario')
            ->whereIn('href', self::CONOCIDOS)
            ->update(['href' => self::NUEVO, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // No se puede saber cuál de los conocidos tenía: se deja el nuevo.
    }
};
