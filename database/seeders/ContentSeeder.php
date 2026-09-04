<?php

namespace Database\Seeders;

use App\Models\Edition;
use App\Models\Page;
use App\Models\ParticipationCard;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Stat;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

/**
 * Contenido editable del home. Los datos vienen del renderVals() de
 * index.html, que en el prototipo estaban hardcodeados en el JS.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->tarjetas();
        $this->testimonios();
        $this->cifras();
        $this->partners();
        $this->ediciones();
        $this->noticias();
        $this->paginas();
    }

    private function tarjetas(): void
    {
        $cards = [
            [
                'titulo' => 'Quiero ser voluntario',
                'descripcion' => 'Encuentra actividades solidarias cerca de ti e inscríbete para ser voluntario.',
                'cta' => 'Inscríbete',
                // El prototipo traía '#voluntario', que no existe como ancla ni
                // aquí ni en el fuente. Mismo caso que '#panorama' de la
                // siguiente tarjeta: se cambia por el destino de verdad.
                'href' => '/actividades',
                'color' => 'var(--naranjo)',
                'icono' => 'user',
                'mask_path' => 'img/tarjeta-01-crop.png',
                'art_path' => 'img/dps-elementos-1080x1080-010726-limpieza.png',
                // Venía comentada en el prototipo y se sembró apagada. El
                // cliente la pidió encendida el 2026-09-01; en las bases que ya
                // existen lo hace la migración 2025_01_11_000001.
                'activo' => true,
            ],
            [
                'titulo' => 'Quiero ir a un panorama solidario',
                'descripcion' => 'Conoce actividades solidarias cerca de ti para visitar y participar.',
                'cta' => 'Explora panoramas',
                'href' => '/actividades',
                'color' => 'var(--teal)',
                'icono' => 'cal',
                'mask_path' => 'img/tarjeta-02-crop.png',
                'art_path' => 'img/dps-elementos-1080x1080-010726-bicicletada.png',
                'activo' => true,
            ],
            [
                'titulo' => 'Quiero organizar actividades',
                'descripcion' => 'Si eres organización o empresa, publica una actividad para que otras '
                    . 'personas puedan conocerla o sumarse.',
                'nota' => '¡También puedes convocar participantes si es abierta al público!',
                'cta' => 'Publica tu actividad',
                'href' => '/publicar-actividad',
                'color' => 'var(--rosa)',
                'icono' => 'plus',
                'mask_path' => 'img/tarjeta-03-crop.png',
                'art_path' => 'img/dps-elementos-1080x1080-010726-ronda.png',
                'activo' => true,
            ],
        ];

        foreach ($cards as $orden => $c) {
            ParticipationCard::updateOrCreate(
                ['titulo' => $c['titulo']],
                $c + ['orden' => $orden + 1],
            );
        }
    }

    private function testimonios(): void
    {
        $items = [
            [
                'texto' => 'Solos no podemos. Desde lo que tú sabes, desde lo que tú eres, desde lo que '
                    . 'tú puedes destinar, podemos construir una sociedad más justa y equitativa. ¡Súmate!',
                'autor' => 'Claudia Castañeda',
                'cargo' => 'Fundación Trascender',
                // Llevaba el logo genérico de Comunidad; el cliente pidió el de
                // su fundación el 2026-09-01. En las bases que ya existen lo
                // cambia la migración 2025_01_12_000002.
                'logo_path' => 'img/logo-fundacion-trascender.png',
                'color' => 'var(--naranjo)',
                'bleed' => false,
            ],
            [
                'texto' => 'El patrimonio social de Chile también se construye a través de las personas '
                    . 'que deciden involucrarse y aportar a sus comunidades. El voluntariado refleja ese '
                    . 'compromiso y demuestra que las acciones concretas pueden generar un impacto que '
                    . 'perdura en el tiempo.',
                'autor' => 'Jessica Rivas',
                'cargo' => 'Scotiabank',
                'logo_path' => 'img/logo-scotiabank-red.svg',
                'color' => 'var(--turquesa)',
                'bleed' => false,
            ],
            [
                'texto' => 'Qué dicha ser parte de estos momentos que marcan el alma y el corazón 🫶 '
                    . 'Qué bella labor la que hacen día a día desde el corazón y con la gracia de ayudar '
                    . 'al prójimo. Feliz de ser parte.',
                'autor' => 'Marleyn Torrealba',
                'cargo' => 'Participante',
                'logo_path' => 'img/tarjeta-01-20fb4d48.png',
                'color' => 'var(--rosa)',
                'bleed' => true,
            ],
        ];

        foreach ($items as $orden => $t) {
            Testimonial::updateOrCreate(
                ['autor' => $t['autor']],
                $t + ['orden' => $orden + 1, 'activo' => true],
            );
        }
    }

    private function cifras(): void
    {
        $items = [
            ['numero' => '200+', 'etiqueta' => 'Organizaciones e instituciones participantes', 'color' => 'var(--naranjo)'],
            ['numero' => '70+', 'etiqueta' => 'Empresas comprometidas', 'color' => 'var(--teal)'],
            ['numero' => '50.000+', 'etiqueta' => 'Personas', 'color' => 'var(--rosa)'],
            ['numero' => '500', 'etiqueta' => 'Actividades', 'color' => 'var(--turquesa)'],
        ];

        foreach ($items as $orden => $s) {
            Stat::updateOrCreate(
                ['etiqueta' => $s['etiqueta']],
                $s + ['orden' => $orden + 1, 'activo' => true],
            );
        }
    }

    private function partners(): void
    {
        $items = [
            ['nombre' => 'Scotiabank', 'logo_path' => 'img/logo-scotiabank-red.svg', 'grupo' => 'auspician', 'tamano' => 'grande'],
            ['nombre' => 'Sodimac', 'logo_path' => 'img/sodimac-horizontalalta.jpg', 'grupo' => 'auspician', 'tamano' => 'grande'],

            // «Participan» va en el tamaño intermedio por decisión del cliente
            // (reunión del 2026-09-01): auspician grande, participan mediano,
            // colaboran pequeño.
            ['nombre' => 'Reale Seguros', 'logo_path' => 'img/logoreale.png', 'grupo' => 'participan', 'tamano' => 'mediano'],
            ['nombre' => 'Anglo American', 'logo_path' => 'img/anglo-american-color.svg', 'grupo' => 'participan', 'tamano' => 'mediano'],

            ['nombre' => 'La Araucana', 'logo_path' => 'img/photo-2025-07-15-16-52-34-26218c16.jpg', 'grupo' => 'colaboran', 'tamano' => 'chico'],
            ['nombre' => 'Mundo', 'logo_path' => 'img/logo-mundo.svg', 'grupo' => 'colaboran', 'tamano' => 'chico'],
        ];

        $sponsors = [
            ['Andes Vital', 'var(--naranjo)'], ['BancoSur', 'var(--teal)'],
            ['Fundación Raíz', 'var(--rosa)'], ['Nortec', 'var(--turquesa)'],
            ['Radio Común', 'var(--amarillo)'], ['Prensa Viva', 'var(--gris)'],
            ['EnergíaMás', 'var(--naranjo)'], ['Cumbre TV', 'var(--teal)'],
        ];

        $participantes = [
            ['Hogar de Cristo', 'var(--naranjo)'], ['Techo', 'var(--teal)'],
            ['Coaniquem', 'var(--rosa)'], ['Red Solidaria', 'var(--turquesa)'],
            ['Junto al Barrio', 'var(--amarillo)'], ['Fundación Luz', 'var(--gris)'],
            ['América Solidaria', 'var(--naranjo)'], ['Corp. Esperanza', 'var(--teal)'],
            ['Casa de Todos', 'var(--rosa)'], ['Manos Unidas', 'var(--turquesa)'],
            ['Fundación Mar', 'var(--amarillo)'],
        ];

        foreach ($sponsors as [$nombre, $color]) {
            $items[] = ['nombre' => $nombre, 'grupo' => 'sponsor', 'color' => $color, 'tamano' => 'normal'];
        }

        foreach ($participantes as [$nombre, $color]) {
            $items[] = ['nombre' => $nombre, 'grupo' => 'participante', 'color' => $color, 'tamano' => 'normal'];
        }

        foreach ($items as $orden => $p) {
            Partner::updateOrCreate(
                ['nombre' => $p['nombre'], 'grupo' => $p['grupo']],
                $p + ['orden' => $orden + 1, 'activo' => true],
            );
        }
    }

    private function ediciones(): void
    {
        $items = [
            [
                'anio' => 2024,
                'titulo' => 'Primera edición',
                'descripcion' => 'Chile celebra por primera vez su Patrimonio Social, con cientos de '
                    . 'organizaciones abriendo sus puertas a la comunidad.',
                'imagen' => 'img/construyamos-crop.png',
            ],
            [
                'anio' => 2025,
                'titulo' => 'Segunda edición',
                'descripcion' => 'El movimiento se expande a todas las regiones del país y suma a las '
                    . 'primeras empresas comprometidas.',
                'imagen' => 'img/por-que-celebramos-crop.png',
            ],
            [
                'anio' => 2026,
                'titulo' => 'Tercera edición',
                'descripcion' => 'Más de 500 actividades solidarias a lo largo de Chile durante todo un '
                    . 'fin de semana.',
                'imagen' => 'img/que-es-el-patrimonio-crop.png',
            ],
        ];

        foreach ($items as $e) {
            Edition::updateOrCreate(['anio' => $e['anio']], $e + ['activo' => true]);
        }
    }

    private function noticias(): void
    {
        $items = [
            [
                'titulo' => 'Se abren las inscripciones para la edición 2026',
                'extracto' => 'Las organizaciones ya pueden publicar sus actividades para el Día del '
                    . 'Patrimonio Social de este año.',
                'contenido' => 'Desde hoy y hasta fin de mes, cualquier organización sin fines de lucro, '
                    . 'empresa, institución educativa o municipalidad puede publicar su actividad en el '
                    . 'sitio. El equipo organizador revisa cada propuesta antes de publicarla en el '
                    . 'calendario nacional.',
                'imagen' => 'img/actividades-destacadas.png',
                'published_at' => now()->subDays(4),
            ],
            [
                'titulo' => 'Más de 200 organizaciones ya son parte del movimiento',
                'extracto' => 'El catastro de organizaciones participantes creció un 40% respecto de la '
                    . 'edición anterior.',
                'contenido' => 'La cifra confirma la consolidación del Día del Patrimonio Social como '
                    . 'una fecha del calendario cívico. Las regiones con mayor crecimiento fueron '
                    . 'Valparaíso, Biobío y La Araucanía.',
                'imagen' => 'img/manos.png',
                'published_at' => now()->subDays(12),
            ],
            [
                'titulo' => 'Cómo preparar tu actividad para recibir visitas',
                'extracto' => 'Una guía breve con recomendaciones de accesibilidad, señalética y '
                    . 'registro de asistentes.',
                'contenido' => 'Recibir personas que no conocen tu organización requiere preparación. '
                    . 'Reunimos las recomendaciones de quienes participaron en ediciones anteriores.',
                'imagen' => 'img/voces-crop.png',
                'published_at' => now()->subDays(21),
            ],
        ];

        foreach ($items as $p) {
            Post::updateOrCreate(['slug' => \Illuminate\Support\Str::slug($p['titulo'])], $p + ['activo' => true]);
        }
    }

    private function paginas(): void
    {
        Page::updateOrCreate(
            ['slug' => 'privacidad'],
            [
                'titulo' => 'Política de privacidad',
                'meta_descripcion' => 'Cómo tratamos los datos de quienes participan en el Día del Patrimonio Social.',
                'contenido' => '<p>Esta página es un marcador de posición. El contenido definitivo se '
                    . 'carga desde el panel administrativo.</p>',
                'activo' => true,
            ],
        );
    }
}
