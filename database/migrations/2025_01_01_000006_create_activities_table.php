<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('titulo');
            $table->string('slug')->unique();
            $table->text('descripcion');
            $table->string('formato', 20)->default('Presencial');

            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_termino')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_termino')->nullable();
            $table->boolean('sin_fecha_definida')->default(false);

            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direccion')->nullable();

            $table->unsignedInteger('participantes_estimados')->nullable();
            $table->unsignedInteger('cupos_totales')->nullable();
            $table->unsignedInteger('cupos_disponibles')->nullable();

            $table->boolean('abierta_publico')->default(true);
            $table->boolean('inscripcion_habilitada')->default(true);
            $table->boolean('tiene_accesibilidad')->default(false);
            $table->text('info_previa')->nullable();

            $table->string('imagen_portada')->nullable();
            $table->string('correo_contacto')->nullable();
            $table->string('enlace_red_social')->nullable();
            $table->string('enlace_web')->nullable();

            $table->string('estado', 20)->default('borrador');
            $table->text('observaciones_revision')->nullable();
            $table->boolean('destacada')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'published_at']);
            $table->index(['region_id', 'commune_id']);
            $table->index('fecha_inicio');
            $table->index('destacada');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
