<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->string('tipo', 40);
            $table->string('tipo_otro')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('logo_path')->nullable();
            $table->unsignedInteger('num_voluntarios')->nullable();
            $table->string('unidad_educativa')->nullable();
            $table->string('correo_contacto')->nullable();
            $table->string('enlace_web')->nullable();
            $table->string('enlace_red_social')->nullable();
            $table->boolean('verificada')->default(false);
            $table->timestamps();

            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
