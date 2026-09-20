<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de cuándo se encoló la invitación a evaluar (P20).
 *
 * El mismo patrón que `recordatorio_encolado_at`, y por el mismo motivo: el
 * registro de correos sólo se escribe cuando el worker despacha, así que entre
 * que se encola y sale hay una ventana en la que otra pasada del comando
 * volvería a encolar el mismo correo. Esto se marca al encolar.
 *
 * Sólo añade una columna que admite nulos. No toca ninguna fila existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $tabla) {
            $tabla->timestamp('invitacion_evaluacion_encolada_at')->nullable()->after('recordatorio_encolado_at');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $tabla) {
            $tabla->dropColumn('invitacion_evaluacion_encolada_at');
        });
    }
};
