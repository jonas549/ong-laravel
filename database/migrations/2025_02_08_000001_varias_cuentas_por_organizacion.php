<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Varias cuentas por organización.
 *
 * Hasta aquí la organización colgaba de la cuenta (`organizations.user_id`) y
 * una organización tenía como mucho una. Una organización real puede tener
 * muchas personas publicando, y lo que pasaba era que compartían la clave o
 * creaban un duplicado con una variante del nombre.
 *
 * Se da la vuelta a la relación:
 *
 * - `users.organization_id`: de qué organización es cada cuenta. Es lo que
 *   manda a partir de ahora.
 * - `organizations.user_id` se queda, y pasa a significar **la cuenta
 *   principal**: la que recibe el aviso cuando alguien se suma y la única que
 *   edita la ficha. Se conserva la columna para no reescribir todo lo que ya
 *   la lee.
 * - `activities.user_id`: quién creó cada actividad. Cada persona ve y edita
 *   sólo las suyas, y los correos de moderación le llegan a ella.
 * - `users.organizacion_desde`: cuándo se enlazó la cuenta a la
 *   organización, para que el panel diga desde cuándo está cada una.
 *
 * **Con los datos de hoy no cambia nada de comportamiento**: cada cuenta queda
 * en la organización de la que era dueña, como principal, y es autora de
 * todas las actividades de esa organización.
 *
 * Antes de tocar nada comprueba que ninguna cuenta sea dueña de dos
 * organizaciones vivas: el código no lo permitía, pero la base sí, y en ese
 * caso no hay forma correcta de elegir por nadie. Se para y dice cuáles son.
 *
 * Cada paso mira si ya está hecho: en MySQL los cambios de esquema no van
 * dentro de una transacción, y el despliegue vuelve a correr `migrate` cada
 * cinco minutos. Si se corta a medias, el siguiente intento termina el
 * trabajo en vez de chocar con lo que ya hizo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $repetidas = DB::table('organizations')
            ->whereNull('deleted_at')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        if ($repetidas->isNotEmpty()) {
            throw new RuntimeException(
                'No se migra nada: estas cuentas son dueñas de más de una organización y hay que decidir a mano '
                .'con cuál se quedan (ids de usuario): '.$repetidas->implode(', ')
            );
        }

        if (! Schema::hasColumn('users', 'organization_id')) {
            Schema::table('users', function (Blueprint $tabla) {
                $tabla->foreignId('organization_id')->nullable()->after('role')
                    ->constrained()->nullOnDelete();
                $tabla->timestamp('organizacion_desde')->nullable()->after('organization_id');
            });
        }

        if (! Schema::hasColumn('activities', 'user_id')) {
            Schema::table('activities', function (Blueprint $tabla) {
                $tabla->foreignId('user_id')->nullable()->after('organization_id')
                    ->constrained()->nullOnDelete();
            });
        }

        /*
         * La cuenta principal ya no se lleva por delante la organización si
         * algún día se borra de verdad: se queda sin principal y el panel deja
         * elegir otra. Hoy las cuentas se borran en blando y esto no llegaba a
         * dispararse, pero estaba declarado así desde el principio.
         */
        Schema::table('organizations', function (Blueprint $tabla) {
            $tabla->dropForeign(['user_id']);
        });

        Schema::table('organizations', function (Blueprint $tabla) {
            $tabla->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        /*
         * Cada cuenta, a la organización de la que es dueña. Primero las
         * vivas y luego las borradas, y sólo donde aún no hay nada: si una
         * cuenta tiene una de cada, se queda con la viva.
         */
        foreach ([false, true] as $borradas) {
            DB::table('organizations')
                ->whereNotNull('user_id')
                ->when($borradas, fn ($q) => $q->whereNotNull('deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->orderBy('id')
                ->get(['id', 'user_id', 'created_at'])
                ->each(function ($org) {
                    DB::table('users')
                        ->where('id', $org->user_id)
                        ->whereNull('organization_id')
                        ->update([
                            'organization_id' => $org->id,
                            'organizacion_desde' => $org->created_at,
                        ]);
                });
        }

        // Y cada actividad, a la cuenta dueña de su organización.
        DB::statement(
            'UPDATE activities a JOIN organizations o ON o.id = a.organization_id '
            .'SET a.user_id = o.user_id WHERE a.user_id IS NULL AND o.user_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $tabla) {
            $tabla->dropForeign(['user_id']);
        });

        Schema::table('organizations', function (Blueprint $tabla) {
            $tabla->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('activities', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('user_id');
        });

        Schema::table('users', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('organization_id');
            $tabla->dropColumn('organizacion_desde');
        });
    }
};
