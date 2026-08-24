<?php

namespace Database\Seeders;

use App\Models\TaxonomyTerm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Los catálogos que en el prototipo estaban hardcodeados en el JS de cada
 * página (constantes TEMAS, CARACS, PUBLICO y ACCESOS).
 *
 * CARACS aparecía con dos definiciones distintas: 15 opciones en
 * mi-cuenta.html y 5 en publicar-actividad.html. Se usa la de 15.
 */
class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->data() as $grupo => $terminos) {
            foreach ($terminos as $orden => $nombre) {
                TaxonomyTerm::updateOrCreate(
                    ['grupo' => $grupo, 'slug' => Str::slug($nombre)],
                    ['nombre' => $nombre, 'orden' => $orden + 1, 'activo' => true],
                );
            }
        }
    }

    /** @return array<string, array<int, string>> */
    private function data(): array
    {
        return [
            'tema' => [
                'Educación y formación',
                'Salud',
                'Bienestar',
                'Arte, cultura y oficios',
                'Deporte, recreación y actividad física',
                'Medio ambiente',
                'Animales y biodiversidad',
                'Desarrollo comunitario',
                'Vivienda y hábitat',
                'Empleo, empleabilidad y emprendimiento',
                'Inclusión y derechos',
                'Filantropía y voluntariado',
                'Emergencias y gestión del riesgo',
                'Otro',
            ],
            'caracteristica' => [
                'Para participar en familia',
                'Para jóvenes',
                'Para personas mayores',
                'Intergeneracional',
                'Inclusiva',
                'Bajo techo',
                'Al aire libre',
                'Accesible en transporte público',
                'Cupos limitados',
                'Pet friendly',
                'Actividad breve (hasta 1 hora)',
                'Experiencia participativa',
                'Ideal para quienes quieren conocer organizaciones',
                'Ideal para equipos de empresas',
                'Ideal para establecimientos educacionales',
            ],
            'publico' => [
                'Niñas y niños',
                'Jóvenes',
                'Personas mayores',
                'Familias',
                'Personas con discapacidad',
                'Público general',
                'Organizaciones sin fines de lucro',
                'Empresas',
                'Establecimientos educacionales',
                'Otros',
            ],
            'acceso' => [
                'Acceso universal',
                'Tiene intérprete de Lengua de Señas chilena',
                'Material accesible',
                'Espacio tranquilo',
                'Transcripción en vivo de la presentación',
                'No cuenta con medidas específicas',
            ],
        ];
    }
}
