<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actividades cerradas (punto 6 del 05/10): las que se publican sólo para
 * difusión, para un público específico. No salen en /actividades ni en el
 * calendario; su ficha sigue abierta por enlace directo.
 *
 * Sólo tiene sentido sin inscripción previa: se pregunta cuando
 * «¿Requiere inscripción previa?» es «No».
 *
 * Aditiva y con `false` por defecto: todas las que ya existen quedan como no
 * cerradas.
 *
 * `abierta_publico` NO se reutiliza: su pregunta («¿Esta actividad es abierta
 * al público?») sale del editor y la columna se queda sin uso, como
 * `activity_evaluations.foto_path`. Reutilizarla habría escondido de golpe las
 * que algún organizador hubiera marcado como «no abierta».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->boolean('cerrada')->default(false)->after('inscripcion_habilitada');
            $table->index('cerrada');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['cerrada']);
            $table->dropColumn('cerrada');
        });
    }
};
