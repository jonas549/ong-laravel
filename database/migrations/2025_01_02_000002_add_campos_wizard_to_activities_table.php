<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos campos del paso 4 de publicar-actividad.html que no tenían dónde
 * guardarse: el detalle libre de accesibilidad ("Cuéntanos brevemente
 * cuáles") y el "¿Cuál?" que aparece al marcar "Otros" como público
 * beneficiado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->text('accesibilidad_detalle')->nullable()->after('tiene_accesibilidad');
            $table->string('publico_otro')->nullable()->after('accesibilidad_detalle');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['accesibilidad_detalle', 'publico_otro']);
        });
    }
};
