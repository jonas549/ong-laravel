<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Commune;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\TaxonomyTerm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Actividades de ejemplo, con una en cada estado del flujo de moderación
 * para poder probar el panel sin cargar datos a mano.
 */
class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::first();

        if (! $org) {
            $this->command->warn('ActivitySeeder: no hay organizaciones. Corre UserSeeder primero.');

            return;
        }

        foreach ($this->data() as $orden => $d) {
            $comuna = Commune::where('slug', Str::slug($d['comuna']))->first();

            $actividad = Activity::updateOrCreate(
                ['slug' => Str::slug($d['titulo'])],
                [
                    'organization_id' => $org->id,
                    'titulo' => $d['titulo'],
                    'descripcion' => $d['descripcion'],
                    'formato' => $d['formato'] ?? 'Presencial',
                    'fecha_inicio' => $d['fecha'],
                    'hora_inicio' => $d['hora'] ?? '10:00',
                    'hora_termino' => $d['hora_fin'] ?? '13:00',
                    'region_id' => $comuna?->region_id,
                    'commune_id' => $comuna?->id,
                    'direccion' => $d['direccion'],
                    'participantes_estimados' => $d['estimados'] ?? 80,
                    'cupos_totales' => $d['cupos'] ?? 80,
                    'cupos_disponibles' => $d['disponibles'] ?? 56,
                    'abierta_publico' => true,
                    'inscripcion_habilitada' => $d['estado'] === 'publicada',
                    'tiene_accesibilidad' => $d['accesible'] ?? false,
                    'imagen_portada' => $d['imagen'] ?? null,
                    'correo_contacto' => 'contacto@juntoalbarrio.cl',
                    'estado' => $d['estado'],
                    'observaciones_revision' => $d['observaciones'] ?? null,
                    'destacada' => $d['destacada'] ?? false,
                    'orden' => $orden + 1,
                    'published_at' => $d['estado'] === 'publicada' ? now()->subDays(3) : null,
                ],
            );

            $terminos = TaxonomyTerm::whereIn('slug', array_map(
                fn ($n) => Str::slug($n),
                array_merge($d['temas'] ?? [], $d['publicos'] ?? [], $d['caracteristicas'] ?? []),
            ))->pluck('id');

            $actividad->terms()->sync($terminos);

            if ($actividad->estado === 'publicada') {
                $this->inscritos($actividad);
            }
        }
    }

    private function inscritos(Activity $actividad): void
    {
        $gente = [
            ['María González', 'maria@ejemplo.cl', true, 'confirmado'],
            ['Carlos Pérez', 'carlos@ejemplo.cl', true, 'confirmado'],
            ['Ana Martínez', 'ana@ejemplo.cl', false, 'pendiente'],
            ['Rodrigo Silva', 'rodrigo@ejemplo.cl', true, 'confirmado'],
            ['Javiera Rojas', 'javiera@ejemplo.cl', true, 'confirmado'],
            ['Pedro Fuentes', 'pedro@ejemplo.cl', true, 'pendiente'],
            ['Camila Torres', 'camila@ejemplo.cl', true, 'confirmado'],
            ['Tomás Herrera', 'tomas@ejemplo.cl', false, 'pendiente'],
        ];

        foreach ($gente as $i => [$nombre, $correo, $mayor, $estado]) {
            Registration::updateOrCreate(
                ['activity_id' => $actividad->id, 'correo' => $correo],
                [
                    'nombre' => $nombre,
                    'es_mayor_edad' => $mayor,
                    'estado' => $estado,
                    'confirmed_at' => $estado === 'confirmado' ? now()->subDays(10 - $i) : null,
                    'created_at' => now()->subDays(20 - $i),
                ],
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function data(): array
    {
        return [
            [
                'titulo' => 'Jornada de reforestación urbana',
                'descripcion' => 'Recupera un área verde de tu comuna junto a vecinos y voluntarios de '
                    . 'toda la ciudad. Trae ropa cómoda y ganas de trabajar en equipo.',
                'comuna' => 'Ñuñoa',
                'direccion' => 'Parque Juan XXIII, acceso poniente',
                'fecha' => now()->addDays(20)->toDateString(),
                'estado' => 'publicada',
                'destacada' => true,
                'accesible' => true,
                'imagen' => 'img/volunteers-little-kid-planting-tree-covering-hole-ground.jpg',
                'temas' => ['Medio ambiente'],
                'caracteristicas' => ['Al aire libre', 'Para participar en familia'],
                'publicos' => ['Público general', 'Familias'],
            ],
            [
                'titulo' => 'Comedor solidario del puerto',
                'descripcion' => 'Prepara y comparte almuerzos con familias del barrio en una actividad '
                    . 'abierta a todo público.',
                'comuna' => 'Valparaíso',
                'direccion' => 'Sede vecinal Cerro Alegre, Almirante Montt 250',
                'fecha' => now()->addDays(21)->toDateString(),
                'estado' => 'publicada',
                'destacada' => true,
                'imagen' => 'img/arranging-social-hours-recognize-volunteer-effo-generative-ai.jpg',
                'temas' => ['Desarrollo comunitario'],
                'caracteristicas' => ['Intergeneracional', 'Bajo techo'],
                'publicos' => ['Público general'],
            ],
            [
                'titulo' => 'Taller de oficios para jóvenes',
                'descripcion' => 'Comparte tu experiencia y ayuda a jóvenes a dar sus primeros pasos en '
                    . 'un oficio.',
                'comuna' => 'Concepción',
                'direccion' => 'Centro comunitario Barrio Norte, Los Carrera 1180',
                'fecha' => now()->addDays(27)->toDateString(),
                'estado' => 'publicada',
                'destacada' => true,
                'imagen' => 'img/social-quest-game.jpg',
                'temas' => ['Educación y formación', 'Empleo, empleabilidad y emprendimiento'],
                'caracteristicas' => ['Para jóvenes', 'Cupos limitados'],
                'publicos' => ['Jóvenes'],
            ],
            [
                'titulo' => 'Taller de lectura en la plaza',
                'descripcion' => 'Lectura en voz alta y mediación lectora para niñas y niños del barrio.',
                'comuna' => 'Santiago',
                'direccion' => 'Plaza Yungay',
                'fecha' => now()->addDays(30)->toDateString(),
                'estado' => 'revision',
                'temas' => ['Educación y formación'],
                'publicos' => ['Niñas y niños'],
            ],
            [
                'titulo' => 'Ruta patrimonial de oficios',
                'descripcion' => 'Recorrido guiado por talleres de oficios tradicionales del barrio.',
                'comuna' => 'Providencia',
                'direccion' => 'Metro Salvador, salida norte',
                'fecha' => now()->addDays(35)->toDateString(),
                'estado' => 'ajustes',
                'observaciones' => 'Falta indicar el punto de encuentro exacto y confirmar si la ruta '
                    . 'es accesible para personas con movilidad reducida.',
                'temas' => ['Arte, cultura y oficios'],
                'publicos' => ['Personas mayores'],
            ],
            [
                'titulo' => 'Operativo de salud comunitario',
                'descripcion' => 'Controles preventivos gratuitos para vecinas y vecinos del sector.',
                'comuna' => 'Las Condes',
                'direccion' => 'CESFAM Apoquindo',
                'fecha' => now()->addDays(12)->toDateString(),
                'estado' => 'cancelada',
                'temas' => ['Salud'],
                'publicos' => ['Público general'],
            ],
            [
                'titulo' => 'Encuentro de organizaciones del barrio',
                'descripcion' => 'Borrador de una jornada de articulación entre organizaciones locales.',
                'comuna' => 'Recoleta',
                'direccion' => 'Por definir',
                'fecha' => null,
                'estado' => 'borrador',
                'temas' => ['Filantropía y voluntariado'],
                'publicos' => ['Organizaciones sin fines de lucro'],
            ],
        ];
    }
}
