<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que necesita el listado histórico del cliente para entrar tal cual.
 *
 * **`tipo` admite nulos.** El cliente clasifica a sus organizaciones por su
 * relación con la red —«Socia», «No socia», «Exsocia»— y eso no dice si son
 * una fundación, una empresa o un colegio. Meterlas a todas como «Otra» habría
 * sido inventar, y además habría roto el filtro por tipo del panel. Nulo es
 * «todavía no lo sabemos»: quien la reclame lo elige en el wizard, que ya lo
 * pide cuando falta (`Organization::datosQueFaltan`).
 *
 * **`anios_participacion`** es de qué ediciones viene («2024, 2025»). Además
 * de dato, es lo que la mete en la marquesina del home: hasta aquí sólo salían
 * las que habían publicado en el sitio, y ninguna del listado lo ha hecho.
 *
 * Sólo añade y relaja: no toca ninguna fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $tabla) {
            $tabla->string('tipo', 40)->nullable()->change();
            $tabla->string('anios_participacion', 100)->nullable()->after('enlace_red_social');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $tabla) {
            $tabla->dropColumn('anios_participacion');
        });

        /*
         * `tipo` no vuelve a ser obligatorio: con filas a nulo el ALTER
         * fallaría, y rellenarlas con un tipo inventado es justo lo que esta
         * migración vino a evitar.
         */
    }
};
