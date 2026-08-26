<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice para contar intentos fallidos por correo, panel e IP.
 *
 * El bloqueo por intentos se apoyaba en `RateLimiter`, que guarda el contador
 * en la caché. En producción el cron de despliegue toca la caché cada cinco
 * minutos, así que el contador podía vaciarse solo y el bloqueo no llegaba a
 * saltar nunca — mientras en local funcionaba perfectamente, porque ahí nadie
 * limpia nada.
 *
 * Ahora se cuenta sobre `access_logs`, que ya registraba cada intento y no lo
 * borra nadie. De paso desaparece una incoherencia: la lista de sospechosos del
 * panel salía de esta tabla y el bloqueo de la caché, así que las dos podían
 * decir cosas distintas sobre la misma cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->index(['email', 'panel', 'ip', 'created_at'], 'access_logs_bloqueo_index');
        });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropIndex('access_logs_bloqueo_index');
        });
    }
};
