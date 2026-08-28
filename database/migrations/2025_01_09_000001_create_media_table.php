<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La biblioteca de medios.
 *
 * `ruta` es lo que ya guardan todas las columnas de imagen del proyecto: una
 * ruta **relativa a `public/`**, que se resuelve con `asset()`. `img/manos.png`
 * y `storage/medios/2026/08/x.jpg` son la misma forma con distinta carpeta, y
 * por eso indexar lo que ya existe no obliga a tocar ni una fila de las otras
 * tablas.
 *
 * `origen` distingue las dos procedencias, y no es cosmético:
 *
 * - `codigo`  → vive en `public/img`, entró por el repositorio y se versiona en
 *               git. Son las 75 del diseño original. **No se borran ni se
 *               reemplazan desde el panel**: el siguiente `git pull` las
 *               devolvería, y el borrado sería una mentira.
 * - `subido`  → vive en `storage/app/public`, lo subió alguien desde el panel y
 *               no está en git. Esas sí se pueden reemplazar y borrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->string('ruta')->unique();
            $tabla->string('origen', 10)->default('subido');

            $tabla->string('nombre');
            $tabla->string('titulo')->nullable();
            $tabla->string('alt', 500)->nullable();

            $tabla->string('mime', 100)->nullable();
            $tabla->string('extension', 10)->nullable();
            $tabla->unsignedBigInteger('peso')->default(0);

            // Nulos a propósito: un SVG no siempre declara medidas, y un
            // archivo que se perdió del disco tampoco las tiene.
            $tabla->unsignedInteger('ancho')->nullable();
            $tabla->unsignedInteger('alto')->nullable();

            $tabla->string('carpeta', 100)->nullable();

            $tabla->foreignId('subido_por')->nullable()->constrained('users')->nullOnDelete();

            $tabla->timestamps();
            $tabla->softDeletes();

            $tabla->index('origen');
            $tabla->index('carpeta');
            $tabla->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
