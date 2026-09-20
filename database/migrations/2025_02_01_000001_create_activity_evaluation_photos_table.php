<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Varias fotografías por evaluación, en vez de una sola columna.
 *
 * Hasta aquí una evaluación tenía `foto_path`: una foto y ninguna más. El
 * cliente pidió varias, con el máximo configurable, así que la foto deja de
 * ser una columna y pasa a ser una fila.
 *
 * **La autorización NO se mueve aquí, y es deliberado.** El consentimiento que
 * firma quien responde es uno solo —«autorizo el uso de mi fotografía»— y va
 * sobre el envío entero, no foto a foto. Partirlo en una casilla por archivo
 * inventaría un permiso que nadie dio. `activity_evaluations.foto_autorizada`
 * se queda donde está y sigue mandando.
 *
 * **`foto_path` no se borra.** Se copia a la tabla nueva y la columna vieja se
 * queda ahí, sin que nadie la lea. Quitarla sería una migración destructiva
 * sobre datos de producción que ya existen, y el único motivo para hacerlo
 * sería la limpieza. Se puede borrar más adelante, en una tanda que empiece
 * por un respaldo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_evaluation_photos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('activity_evaluation_id')->constrained()->cascadeOnDelete();
            $tabla->string('ruta')->comment('Ruta en el disco PRIVADO, nunca en el público');
            $tabla->unsignedSmallInteger('orden')->default(0);
            $tabla->timestamps();

            $tabla->index(['activity_evaluation_id', 'orden']);
        });

        /*
         * Las que ya estaban. Se copian con el id de la evaluación como orden 0
         * para que la primera foto de cada una siga siendo la misma de antes.
         *
         * Va por consulta y no por modelo: una migración que dependa de cómo
         * esté el modelo hoy se rompe el día que el modelo cambie.
         */
        DB::table('activity_evaluations')
            ->whereNotNull('foto_path')
            ->where('foto_path', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($evaluaciones) {
                $filas = [];

                foreach ($evaluaciones as $e) {
                    $filas[] = [
                        'activity_evaluation_id' => $e->id,
                        'ruta' => $e->foto_path,
                        'orden' => 0,
                        'created_at' => $e->created_at,
                        'updated_at' => $e->updated_at,
                    ];
                }

                if ($filas) {
                    DB::table('activity_evaluation_photos')->insert($filas);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_evaluation_photos');
    }
};
