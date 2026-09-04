<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los dos bloques de logos nuevos y el tamaño de «Participan».
 *
 * Segunda tanda del cliente, 2026-09-04. Dos cosas que una base ya sembrada no
 * recoge sola —el error que costó la migración `2025_01_13_000001`, anotado
 * ahí— y por eso vuelven a ir por migración.
 *
 * 1. **«Participan» pasa a grande.** El cliente pidió el 2026-09-01 que fuera
 *    mediano y ahora que se vea igual que «Auspician». Se toca sólo lo que siga
 *    en el tamaño que dejamos nosotros: si la ONG lo cambió desde el CRUD, ése
 *    es el suyo.
 * 2. **La sección «Somos parte de» se coloca la última.** El home la pinta bien
 *    sin fila —de eso se encarga `CatalogoHome`— pero dejarla sin fila la
 *    apoyaría en un empate de `orden` con la marquesina. Aquí se le da un
 *    número propio, por detrás de todas.
 *
 * Los grupos nuevos de `partners` (`alianzas` y `somos-parte`) no necesitan
 * nada: son valores de una columna que ya existe, y hasta que se carguen logos
 * no hay filas que migrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('partners')
            ->where('grupo', 'participan')
            ->whereIn('tamano', ['mediano', 'chico'])
            ->update(['tamano' => 'grande']);

        $this->colocarSomosParte();
    }

    public function down(): void
    {
        // Vuelve a mediano, que es de donde venía en la tanda anterior.
        DB::table('partners')
            ->where('grupo', 'participan')
            ->where('tamano', 'grande')
            ->update(['tamano' => 'mediano']);

        DB::table('home_sections')->where('clave', 'somos-parte')->delete();
    }

    /**
     * La fila de la sección nueva, al final del orden.
     *
     * Sólo si la tabla ya tiene secciones guardadas: con la tabla vacía manda
     * el catálogo y `sembrarLasQueFalten()` las creará todas de una vez y
     * numeradas de corrido, que es mejor que dejar una suelta con el 13.
     */
    private function colocarSomosParte(): void
    {
        $hay = DB::table('home_sections')->count();

        if ($hay === 0 || DB::table('home_sections')->where('clave', 'somos-parte')->exists()) {
            return;
        }

        DB::table('home_sections')->insert([
            'clave' => 'somos-parte',
            'orden' => ((int) DB::table('home_sections')->max('orden')) + 1,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
