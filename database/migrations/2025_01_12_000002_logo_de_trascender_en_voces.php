<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El testimonio de Fundación Trascender pasa a llevar su propio logo.
 *
 * Iba con el genérico de Comunidad, que es quien organiza el día, no quien
 * habla. Lo pidió el cliente en la reunión del 2026-09-01.
 *
 * Va en una migración por lo mismo que la tarjeta de voluntario: `ContentSeeder`
 * no lo ejecuta nadie en el servidor —`dps:instalar` lo salta a propósito, por
 * ser contenido de ejemplo— y el cron sí corre `migrate`. **Sólo toca la fila
 * si sigue con el logo que dejó el seeder**: si la ONG ya le puso otro, aquí no
 * se pisa nada.
 *
 * El archivo entra como `logo-fundacion-trascender.png`, sin espacios ni
 * tildes: el original venía como «Fundación Trascender.png» y ese nombre
 * sobrevive en Windows pero se rompe al servirlo desde Linux.
 */
return new class extends Migration
{
    private const ANTES = 'img/logo-cos-color.png';

    private const AHORA = 'img/logo-fundacion-trascender.png';

    public function up(): void
    {
        DB::table('testimonials')
            ->where('cargo', 'Fundación Trascender')
            ->where('logo_path', self::ANTES)
            ->update(['logo_path' => self::AHORA]);
    }

    public function down(): void
    {
        DB::table('testimonials')
            ->where('cargo', 'Fundación Trascender')
            ->where('logo_path', self::AHORA)
            ->update(['logo_path' => self::ANTES]);
    }
};
