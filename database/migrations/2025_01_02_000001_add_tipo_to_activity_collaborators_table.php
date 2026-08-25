<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El formulario de edición de mi-cuenta.html pide dos datos por colaborador,
 * nombre y tipo de organización, pero la tabla sólo guardaba el nombre: el
 * wizard, que es de donde salió, únicamente captura el nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_collaborators', function (Blueprint $table) {
            $table->string('tipo')->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('activity_collaborators', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
