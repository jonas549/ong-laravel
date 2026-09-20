<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El punto exacto de la actividad, cuando se elige una sugerencia (P16).
 *
 * La dirección sigue siendo texto libre y sigue siendo la que manda en
 * pantalla: esto es lo que permite que el enlace al mapa lleve al sitio de
 * verdad en vez de a lo que el buscador adivine de una cadena. «Metro
 * Salvador, salida norte» es una dirección perfectamente útil para una persona
 * y un mal término de búsqueda para un mapa.
 *
 * Nulas cuando nadie eligió una sugerencia, que es la mayoría de las de hoy.
 * Sólo se añaden dos columnas; no se toca ninguna fila existente.
 *
 * `decimal(10, 7)` da unos 11 mm de resolución, de sobra para un punto de
 * encuentro, y evita los sustos de coma flotante al comparar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $tabla) {
            $tabla->decimal('latitud', 10, 7)->nullable()->after('direccion');
            $tabla->decimal('longitud', 10, 7)->nullable()->after('latitud');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $tabla) {
            $tabla->dropColumn(['latitud', 'longitud']);
        });
    }
};
