<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aprobación automática desde la segunda actividad.
 *
 * Dos columnas, cada una para una de las dos preguntas que hay que poder
 * contestar:
 *
 * - `organizations.requiere_revision` — el interruptor por organización. Es el
 *   que sirve contra el spam: apagar a quien haga falta sin quitarle la
 *   comodidad a las demás.
 * - `activities.publicada_automaticamente` — para que la ONG pueda repasar
 *   después lo que se publicó sin que nadie lo mirara. Sin esto, una actividad
 *   auto-publicada no aparece en «pendientes» y no hay forma de encontrarla:
 *   se pierde de vista justo lo que conviene poder revisar.
 *
 * El dato también queda en `activity_status_logs`, con el motivo escrito. La
 * columna existe además del registro porque un filtro de listado necesita algo
 * que se pueda indexar y no una consulta al historial por cada fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $tabla) {
            $tabla->boolean('requiere_revision')
                ->default(false)
                ->after('activo')
                ->comment('Fuerza revisión manual aunque la aprobación automática esté activa');
        });

        Schema::table('activities', function (Blueprint $tabla) {
            $tabla->boolean('publicada_automaticamente')
                ->default(false)
                ->after('published_at')
                ->comment('Se publicó sin pasar por revisión');

            $tabla->index('publicada_automaticamente');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $tabla) {
            $tabla->dropColumn('requiere_revision');
        });

        Schema::table('activities', function (Blueprint $tabla) {
            $tabla->dropIndex(['publicada_automaticamente']);
            $tabla->dropColumn('publicada_automaticamente');
        });
    }
};
