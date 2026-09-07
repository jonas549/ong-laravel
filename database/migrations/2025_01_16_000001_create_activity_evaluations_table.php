<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las evaluaciones que dejan los asistentes al escanear el QR de una actividad.
 *
 * Tres decisiones del esquema que conviene conocer antes de tocarlo:
 *
 * 1. **El único índice de duplicados es `(activity_id, correo)`.** Es lo que
 *    evita que la misma persona evalúe dos veces sin poner una barrera delante
 *    de nadie: el correo ya es obligatorio, así que la llave sale gratis y no
 *    hace falta ni login ni captcha. Quien reenvía no ve un error, ve la
 *    pantalla de gracias — eso lo resuelve el controlador.
 * 2. **`ip_hash` y no la IP.** Sirve igual para el anti-spam y no deja un dato
 *    personal en claro en un volcado de la base.
 * 3. **`foto_path` apunta al disco PRIVADO.** Son fotos de asistentes con su
 *    nombre y su correo al lado; en `storage/app/public` cualquiera con la
 *    ruta las vería, autorizadas o no. Se sirven por una ruta del panel que
 *    pide sesión de administrador.
 *
 * `como_se_entero` no es una taxonomía a propósito: son cinco valores fijos del
 * encargo, no un catálogo que la ONG vaya a administrar, y meterlos en
 * `taxonomy_terms` añadiría una pantalla que nadie pidió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_evaluations', function (Blueprint $tabla) {
            $tabla->id();

            $tabla->foreignId('activity_id')->constrained()->cascadeOnDelete();

            $tabla->string('nombre');
            $tabla->string('correo');

            $tabla->unsignedTinyInteger('experiencia')->comment('1 muy mala … 5 excelente');
            $tabla->text('significado')->comment('Qué significa para ti el Patrimonio Social (máx. 300)');
            $tabla->unsignedTinyInteger('motivacion')->comment('1 poco motivado … 5 muy motivado');

            $tabla->string('como_se_entero')->nullable();

            $tabla->string('foto_path')->nullable()->comment('Ruta en el disco PRIVADO, nunca en el público');
            $tabla->boolean('foto_autorizada')->default(false);

            $tabla->char('ip_hash', 64)->nullable()->comment('Hash, no la IP: sirve para el anti-spam sin guardar el dato');

            $tabla->timestamps();

            /*
             * Una evaluación por persona y actividad. Va como índice de base de
             * datos y no como una comprobación en PHP porque dos envíos a la vez
             * pasarían los dos por el `exists()` antes de que ninguno guardara.
             */
            $tabla->unique(['activity_id', 'correo']);

            // El filtro del panel: por actividad y por rango de fechas.
            $tabla->index(['activity_id', 'created_at']);

            // El listado general, que va por fecha descendente.
            $tabla->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_evaluations');
    }
};
