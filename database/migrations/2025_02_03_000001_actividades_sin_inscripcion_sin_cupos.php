<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las actividades publicadas sin inscripción previa se quedan sin cupos.
 *
 * Con «¿Requiere inscripción previa? → No», el campo de cupos del wizard se
 * escondía pero seguía viajando con su 80 de ejemplo, y la ficha pública lo
 * enseñaba como «Cupos disponibles 80» junto a «No es necesario inscripción
 * previa». El formulario y el guardado ya no lo hacen; esto limpia lo que
 * quedó escrito en las bases que ya existen.
 *
 * **Sólo toca la firma del fallo:** sin inscripción y con el total en 80, el
 * valor de ejemplo. Las canceladas se dejan como están, porque cancelar apaga
 * la inscripción de una actividad que sí tenía cupos de verdad (Q1), y si se
 * republica los necesita.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('activities')
            ->where('inscripcion_habilitada', false)
            ->where('cupos_totales', 80)
            ->where('estado', '!=', 'cancelada')
            ->update(['cupos_totales' => null, 'cupos_disponibles' => null]);
    }

    public function down(): void
    {
        // No se puede saber cuáles tenían el 80: tampoco servía para nada.
    }
};
