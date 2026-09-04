<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El sitio web y la red social pasan a vivir sólo en la organización.
 *
 * Estaban en las dos tablas y cada pantalla escribía en una: el wizard en las
 * dos a la vez, el editor de mi-cuenta sólo en la actividad y la ficha del
 * panel sólo en la organización. Podían decir cosas distintas del mismo
 * organizador y la ficha pública tenía que elegir. Decisión de Jonas el
 * 2026-09-04: **es el dato de la entidad, no de cada actividad**, así que se
 * queda en `organizations` y se va de `activities`.
 *
 * **Antes de borrar nada se sube lo que sólo existiera abajo.** Una
 * organización cuyo enlace se hubiera cambiado desde mi-cuenta lo tiene en su
 * actividad y no en su ficha, y borrar la columna sin más se lo llevaría por
 * delante. Sólo se sube donde arriba está vacío: si la organización ya tiene
 * algo, ése es el bueno y no se pisa.
 *
 * El `down()` devuelve las columnas y las rellena con las de la organización.
 * No recupera lo que cada actividad tuviera por su cuenta —eso lo borra `up()`
 * a conciencia—, pero deja el esquema y los datos coherentes.
 */
return new class extends Migration
{
    private const CAMPOS = ['enlace_web', 'enlace_red_social'];

    public function up(): void
    {
        DB::table('organizations')
            ->select(['id', ...self::CAMPOS])
            ->orderBy('id')
            ->chunk(200, function ($organizaciones) {
                foreach ($organizaciones as $org) {
                    $subir = [];

                    foreach (self::CAMPOS as $campo) {
                        if (filled($org->$campo)) {
                            continue;
                        }

                        // La más reciente de sus actividades que tenga algo:
                        // si hubo varios cambios, el último es el que vale.
                        $valor = DB::table('activities')
                            ->where('organization_id', $org->id)
                            ->whereNotNull($campo)
                            ->where($campo, '<>', '')
                            ->orderByDesc('updated_at')
                            ->orderByDesc('id')
                            ->value($campo);

                        if (filled($valor)) {
                            $subir[$campo] = $valor;
                        }
                    }

                    if ($subir) {
                        DB::table('organizations')->where('id', $org->id)->update($subir);
                    }
                }
            });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['enlace_red_social', 'enlace_web']);
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('enlace_red_social')->nullable()->after('correo_contacto');
            $table->string('enlace_web')->nullable()->after('enlace_red_social');
        });

        // Fila a fila y no con un JOIN: el UPDATE con JOIN es de MySQL y las
        // pruebas corren sobre SQLite.
        DB::table('organizations')
            ->select(['id', ...self::CAMPOS])
            ->orderBy('id')
            ->chunk(200, function ($organizaciones) {
                foreach ($organizaciones as $org) {
                    DB::table('activities')
                        ->where('organization_id', $org->id)
                        ->update([
                            'enlace_web' => $org->enlace_web,
                            'enlace_red_social' => $org->enlace_red_social,
                        ]);
                }
            });
    }
};
