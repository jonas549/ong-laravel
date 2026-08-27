<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las secciones del home, editables desde el panel.
 *
 * Sólo guarda **lo que se ha cambiado**. Los valores de partida viven en
 * `App\Support\CatalogoHome`, sacados uno a uno del HTML fuente, y una sección
 * sin fila aquí se pinta con ellos: el home nunca se queda en blanco por que
 * falte una fila, que es lo que pasaría si la base fuera la única fuente.
 *
 * `contenido` y `borrador` son JSON porque cada sección tiene sus propios
 * campos —el hero no se parece a las cifras— y una columna por campo obligaría
 * a migrar cada vez que el diseño cambie de opinión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->unique();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);

            // Lo publicado: es lo que ve el público.
            $table->json('contenido')->nullable();

            // El borrador del autoguardado. Vive aparte a propósito: si
            // compartiera columna con lo publicado, escribir mientras alguien
            // mira el sitio cambiaría el sitio a media frase.
            $table->json('borrador')->nullable();
            $table->timestamp('borrador_at')->nullable();
            $table->foreignId('borrador_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('home_section_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->constrained()->cascadeOnDelete();

            // Quién publicó esto. Anulable porque un usuario se puede borrar y
            // la versión tiene que sobrevivir: es justo el registro que sirve
            // para saber qué decía el sitio antes.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('autor', 255)->nullable();

            $table->json('contenido')->nullable();
            $table->string('nota', 255)->nullable();

            $table->timestamps();

            $table->index(['home_section_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_versions');
        Schema::dropIfExists('home_sections');
    }
};
