<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de accesos: quién entró, cuándo, desde dónde y con qué resultado.
 *
 * Guarda también los intentos fallidos, que son los que interesan para
 * detectar que alguien está probando contraseñas. El correo se guarda tal como
 * se escribió aunque la cuenta no exista, porque saber qué direcciones se están
 * probando es parte del dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('panel', 20);            // admin | organizador
            $table->string('resultado', 20);        // exito | credenciales | bloqueado | rol
            $table->string('ip', 45)->nullable();   // 45 caracteres: cabe una IPv6
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['email', 'created_at']);
            $table->index(['resultado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
