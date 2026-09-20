<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una organización puede existir antes que su cuenta.
 *
 * Hasta aquí `organizations.user_id` era obligatorio: una organización nacía
 * siempre junto al usuario que la creaba en el wizard. Eso impide lo que el
 * cliente va a hacer, que es **cargar de golpe el listado histórico de
 * organizaciones participantes** para que cada una llegue después, se
 * encuentre en el buscador del wizard y se ponga su propia contraseña.
 *
 * Con la columna obligatoria habría que inventarle un usuario a cada una de
 * las ~180 importadas, con un correo falso y una contraseña que alguien
 * tendría que repartir. Justo lo que el cliente dijo que no quiere.
 *
 * Nula significa **«sin reclamar»**: está en el listado, se puede elegir en el
 * wizard, y la primera persona que la elija y cree su contraseña se queda con
 * ella. A partir de ahí deja de ofrecerse.
 *
 * No se borra nada y no se toca ninguna fila existente: las que ya tienen
 * dueño lo conservan. Sólo se relaja la restricción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $tabla) {
            /*
             * `change()` sobre una columna con clave foránea necesita que la
             * clave se quite y se vuelva a poner: MySQL no deja alterar una
             * columna referenciada. Se rehace igual que estaba, con borrado en
             * cascada, para no cambiar de paso un comportamiento que nadie
             * pidió cambiar.
             */
            $tabla->dropForeign(['user_id']);
        });

        Schema::table('organizations', function (Blueprint $tabla) {
            $tabla->foreignId('user_id')->nullable()->change();
        });

        Schema::table('organizations', function (Blueprint $tabla) {
            $tabla->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        /*
         * Volver atrás exige que no queden organizaciones sin reclamar: la
         * columna no puede volver a ser obligatoria con filas a nulo. Se
         * borran, que es lo correcto —una organización sin dueño no es de
         * nadie— pero por eso esta migración no se revierte a la ligera.
         */
        \Illuminate\Support\Facades\DB::table('organizations')->whereNull('user_id')->delete();

        Schema::table('organizations', function (Blueprint $tabla) {
            $tabla->dropForeign(['user_id']);
        });

        Schema::table('organizations', function (Blueprint $tabla) {
            $tabla->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('organizations', function (Blueprint $tabla) {
            $tabla->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
