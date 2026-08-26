<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué transporte llevó cada correo.
 *
 * Hasta ahora el log sólo sabía si el mailer había terminado sin excepción, y
 * eso lo escribía como "Enviado". Con `MAIL_MAILER=log` el correo se escribe en
 * un archivo y el mailer termina igual de contento: el panel decía que había
 * salido y no había salido. Guardando el transporte se puede distinguir un
 * envío real de uno que se quedó en el disco, y el estado `no_entregado` lo
 * dice en la cara en vez de fingir éxito.
 *
 * `en_cola` es el otro agujero: todo el correo de este sistema es ShouldQueue,
 * y la fila sólo nacía cuando el worker enviaba. Sin worker no había ni fila:
 * el correo desaparecía sin dejar rastro en ninguna pantalla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->string('transporte', 40)->nullable()->after('status');
            $table->timestamp('encolado_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropColumn(['transporte', 'encolado_at']);
        });
    }
};
