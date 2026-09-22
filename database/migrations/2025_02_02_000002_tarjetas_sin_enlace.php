<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * C5, segunda pasada: las tarjetas de «¿cómo participar?» sin enlace.
 *
 * La migración anterior (`2025_02_02_000001`) sólo tocaba la fila si tenía uno
 * de los enlaces conocidos, y por eso no alcanzó a las que estaban **vacías o
 * en `#`**: el prototipo traía anclas muertas y el panel deja guardar el campo
 * vacío. Una tarjeta así se pintaba como enlace y no llevaba a ninguna parte.
 *
 * Aquí se arregla eso y sólo eso:
 *
 * - «Quiero ser voluntario» sin enlace pasa a Voluntariados Chile, que es lo
 *   que pidió el cliente.
 * - Las otras dos vuelven a su destino de siempre dentro del sitio.
 *
 * Lo que tenga un enlace escrito NO se toca: si la ONG lo cambió, manda ella.
 */
return new class extends Migration
{
    private const MUERTOS = ['', '#', '#voluntario', '#panorama', '#publicar'];

    private const DESTINOS = [
        'Quiero ser voluntario' => 'https://voluntariadoschile.cl/oportunidades',
        'Quiero ir a un panorama solidario' => '/actividades',
        'Quiero organizar actividades' => '/publicar-actividad',
    ];

    public function up(): void
    {
        foreach (self::DESTINOS as $titulo => $destino) {
            DB::table('participation_cards')
                ->where('titulo', $titulo)
                ->where(function ($q) {
                    $q->whereNull('href')->orWhereIn('href', self::MUERTOS);
                })
                ->update(['href' => $destino, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // No se puede distinguir lo que puso esta migración de lo que ya
        // estaba bien, así que no se deshace.
    }
};
