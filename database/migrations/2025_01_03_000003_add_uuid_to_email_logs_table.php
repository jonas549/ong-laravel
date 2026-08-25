<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identificador propio de cada envío.
 *
 * Antes el log correlacionaba las filas por destinatario y asunto, lo que
 * traía tres problemas: con dos correos iguales en vuelo podía marcar como
 * enviada la fila equivocada, cada reintento creaba una fila nueva, y cuando
 * el envío fallaba no había forma de volver a esa fila para escribir el error
 * real. Con un identificador por envío las tres cosas se resuelven.
 *
 * Y en `registrations`, cuándo se encoló el recordatorio: el log sólo se
 * escribe cuando el worker envía, así que entre encolar y enviar el comando
 * no tenía forma de saber que ya había pasado por ahí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->uuid('mensaje_uuid')->nullable()->unique()->after('id');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->timestamp('recordatorio_encolado_at')->nullable()->after('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropUnique(['mensaje_uuid']);
            $table->dropColumn('mensaje_uuid');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('recordatorio_encolado_at');
        });
    }
};
