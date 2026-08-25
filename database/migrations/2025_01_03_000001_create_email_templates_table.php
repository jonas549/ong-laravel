<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas de correo editables desde el panel.
 *
 * El cuerpo se guarda como HTML con marcadores tipo {{ nombre }}. No se
 * renderiza con Blade: se sustituyen los marcadores contra una lista blanca,
 * porque el contenido lo escribe una persona desde el panel y Blade ejecutaría
 * PHP arbitrario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->string('asunto');
            $table->longText('cuerpo_html');
            // Qué marcadores admite esta plantilla, para listarlos en el editor
            // y para validar que no se cuele ninguno que no sepamos resolver.
            $table->json('variables')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
