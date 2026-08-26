<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién provocó el evento, cuando no fue el titular de la cuenta.
 *
 * `user_id` es de quién es la cuenta afectada. Hasta ahora eso bastaba porque
 * todo lo que se registraba eran intentos de entrar, y ahí las dos cosas
 * coinciden. Con las acciones que un administrador hace sobre cuentas ajenas
 * —levantar un bloqueo, cambiar una contraseña— dejan de coincidir, y sin esta
 * columna el registro no puede responder a «quién lo hizo», que es justo lo que
 * se le pide a un log de accesos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->foreignId('actor_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actor_id');
        });
    }
};
