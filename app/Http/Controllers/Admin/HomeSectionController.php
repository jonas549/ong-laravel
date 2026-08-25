<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\ParticipationCard;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Stat;
use App\Models\Testimonial;
use App\Support\MenuPanel;

/**
 * Las secciones del home, una por una.
 *
 * El editor de contenido es el bloque F. Aquí sólo existe la pantalla para que
 * el árbol del panel esté completo y navegable: cada sección dice de dónde
 * saca hoy sus datos y, cuando ya hay un CRUD que los gobierna, lleva a él.
 */
class HomeSectionController extends Controller
{
    /**
     * De dónde sale hoy cada sección del home. Lo que no tiene CRUD todavía
     * está escrito directamente en la plantilla del home.
     */
    private const ORIGEN = [
        'hero' => [
            'explicacion' => 'Título, bajada, fechas y botones de la portada.',
            'origen' => 'Escrito en la plantilla del home.',
        ],
        'como-participar' => [
            'explicacion' => 'Las tarjetas con las formas de sumarse.',
            'origen' => 'Tabla de tarjetas.',
            'tipo' => 'tarjetas',
            'modelo' => ParticipationCard::class,
        ],
        'actividades-destacadas' => [
            'explicacion' => 'El carrusel de actividades de la portada.',
            'origen' => 'Se llena solo con las actividades publicadas y marcadas como destacadas.',
            'ruta' => 'admin.activities.publicadas',
            'enlace' => 'Ir a las actividades publicadas',
        ],
        'que-es' => [
            'explicacion' => 'El bloque explicativo del Patrimonio Social.',
            'origen' => 'Escrito en la plantilla del home.',
        ],
        'ediciones' => [
            'explicacion' => 'Las ediciones anteriores del evento.',
            'origen' => 'Tabla de ediciones.',
            'tipo' => 'ediciones',
            'modelo' => Edition::class,
        ],
        'voces' => [
            'explicacion' => 'El carrusel de testimonios.',
            'origen' => 'Tabla de testimonios.',
            'tipo' => 'testimonios',
            'modelo' => Testimonial::class,
        ],
        'cifras' => [
            'explicacion' => 'Los números que se animan al hacer scroll.',
            'origen' => 'Tabla de cifras.',
            'tipo' => 'cifras',
            'modelo' => Stat::class,
        ],
        'noticias' => [
            'explicacion' => 'Las últimas noticias que se muestran en la portada.',
            'origen' => 'Tabla de noticias.',
            'tipo' => 'noticias',
            'modelo' => Post::class,
        ],
        'partners' => [
            'explicacion' => 'Auspiciadores y organizaciones participantes.',
            'origen' => 'Tabla de partners.',
            'tipo' => 'partners',
            'modelo' => Partner::class,
        ],
    ];

    public function show(string $seccion)
    {
        abort_unless(isset(MenuPanel::SECCIONES_HOME[$seccion]), 404);

        $datos = self::ORIGEN[$seccion] ?? [];
        $modelo = $datos['modelo'] ?? null;

        return view('admin.home.seccion', [
            'seccion' => $seccion,
            'titulo' => MenuPanel::SECCIONES_HOME[$seccion],
            'explicacion' => $datos['explicacion'] ?? '',
            'origen' => $datos['origen'] ?? '',
            'tipo' => $datos['tipo'] ?? null,
            'ruta' => $datos['ruta'] ?? null,
            'enlace' => $datos['enlace'] ?? null,
            'cuantos' => $modelo ? $modelo::query()->count() : null,
        ]);
    }
}
