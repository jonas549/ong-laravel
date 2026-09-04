<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tres logos que el cliente mandó mejores, el 2026-09-04.
 *
 * **Las categorías no se tocan**: cada uno se queda donde está, y desde el CRUD
 * se pueden mover cuando llegue la asignación del Excel. Aquí sólo cambia el
 * archivo.
 *
 * Qué tenía cada uno de malo:
 *
 * - **Anglo American** iba en un SVG llamado `anglo-american-color.svg` que
 *   **se pintaba en negro**: sus trazos usan las clases `.st0` y `.st1` y el
 *   archivo no trae el bloque `<style>` que las define, así que el navegador
 *   las pintaba con el color por defecto. Se perdió al exportarlo. El nuevo
 *   lleva el azul y el naranja de la marca.
 * - **Sodimac** y **La Araucana** eran recortes de pantalla de 600 y 400 px de
 *   ancho —con un píxel casi negro y otro azulado en las esquinas, de lo que
 *   quedó del recorte— y sus tarjetas pintan el logo hasta 300 y 200 px, así
 *   que en una pantalla 2× se veían blandos.
 *
 * Como siempre en este proyecto: **sólo se toca lo que siga con el archivo que
 * dejó el seeder.** Si la ONG ya cambió alguno desde el panel, ése es el suyo.
 * Y los archivos viejos no se borran: la biblioteca de medios los tiene
 * indexados, y borrarlos dejaría filas apuntando a la nada.
 */
return new class extends Migration
{
    /** nombre del partner => [archivo de antes, archivo de ahora] */
    private const CAMBIOS = [
        'Anglo American' => ['img/anglo-american-color.svg', 'img/logo-anglo-american.png'],
        'Sodimac' => ['img/sodimac-horizontalalta.jpg', 'img/logo-sodimac.png'],
        'La Araucana' => ['img/photo-2025-07-15-16-52-34-26218c16.jpg', 'img/logo-la-araucana.png'],
    ];

    public function up(): void
    {
        foreach (self::CAMBIOS as $nombre => [$antes, $ahora]) {
            $this->cambiar($nombre, $antes, $ahora);
        }
    }

    public function down(): void
    {
        foreach (self::CAMBIOS as $nombre => [$antes, $ahora]) {
            $this->cambiar($nombre, $ahora, $antes);
        }
    }

    private function cambiar(string $nombre, string $de, string $a): void
    {
        DB::table('partners')
            ->where('nombre', $nombre)
            ->where('logo_path', $de)
            ->update(['logo_path' => $a]);
    }
};
