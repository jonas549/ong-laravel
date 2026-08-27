<?php

namespace App\Support;

/**
 * Qué se puede editar de cada sección del home, y qué dice si nadie la ha
 * tocado nunca.
 *
 * **Los valores por defecto son el HTML fuente, copiados uno a uno.** No es
 * documentación: es lo que se pinta de verdad mientras no haya nada guardado
 * (regla 5 del bloque). Por eso el home sigue viéndose igual con la base recién
 * migrada, sin sembrar nada — que es justo lo que hace el cron del servidor,
 * que migra pero no siembra.
 *
 * Los títulos vienen partidos en «antes / destacado / después» y no como un
 * campo de texto rico. En el fuente la palabra naranja es un `<span>` con un
 * color exacto dentro de un `<h1>` con su tamaño y su interletraje: si eso
 * fuera un editor libre, la primera negrita que alguien pusiera cambiaría el
 * peso del titular. Partido, la ONG cambia las palabras y el diseño no se mueve.
 *
 * Tipos de campo:
 *   texto      una línea
 *   parrafo    varias líneas, sin formato
 *   rico       editor con negrita, cursiva, enlaces, listas y encabezados
 *   numero     entero
 *   enlace     URL o ancla
 *   imagen     ruta dentro de public/
 *   youtube    id o URL de un video; se guarda sólo el id
 *   opciones   desplegable
 */
class CatalogoHome
{
    /**
     * Las dos primeras van ancladas: «¿Cómo participar?» sube 96 píxeles por
     * encima del hero para que las tarjetas se monten sobre la imagen, así que
     * moverlas de sitio rompe el diseño. Se pueden editar; no se pueden
     * arrastrar ni apagar.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function secciones(): array
    {
        return [
            'hero' => [
                'titulo' => 'Hero',
                'resumen' => 'La portada: píldora de fechas, titular, bajada, botón e imagen de fondo.',
                'fija' => true,
                'campos' => [
                    'pildora_antes' => ['label' => 'Píldora — antes de la fecha', 'tipo' => 'texto', 'defecto' => 'Día del Patrimonio Social'],
                    'pildora_fechas' => ['label' => 'Píldora — las fechas', 'tipo' => 'texto', 'defecto' => '4 y 5 de diciembre', 'ayuda' => 'Se pinta en negrita, como en el diseño original.'],
                    'pildora_despues' => ['label' => 'Píldora — después de la fecha', 'tipo' => 'texto', 'defecto' => 'Chile 2026'],
                    'titulo' => ['label' => 'Titular', 'tipo' => 'texto', 'defecto' => 'Miles de personas ya son parte de este'],
                    'titulo_destacado' => ['label' => 'Titular — parte en naranja', 'tipo' => 'texto', 'defecto' => 'movimiento solidario', 'ayuda' => 'Se añade al final del titular, en naranja. Déjalo vacío si no quieres destacar nada.'],
                    'bajada' => ['label' => 'Bajada', 'tipo' => 'parrafo', 'defecto' => 'Participa como voluntario, visita actividades solidarias o comparte la tuya.'],
                    'cta_texto' => ['label' => 'Botón sobre el logo', 'tipo' => 'texto', 'defecto' => '¿Cómo quieres participar hoy?'],
                    'cta_enlace' => ['label' => 'A dónde lleva el botón', 'tipo' => 'enlace', 'defecto' => '#voluntariado'],
                    'imagen_fondo' => ['label' => 'Imagen de fondo', 'tipo' => 'imagen', 'defecto' => 'img/dps-banner-2560x1080-010726.jpg'],
                ],
            ],

            'participar' => [
                'titulo' => '¿Cómo participar?',
                'resumen' => 'Las tarjetas con las formas de sumarse, montadas sobre el hero.',
                'fija' => true,
                'campos' => [],
                'crud' => ['ruta' => 'admin.content.index', 'parametros' => ['tipo' => 'tarjetas'], 'texto' => 'Editar las tarjetas', 'nota' => 'Las tarjetas —incluida la que está desactivada— se editan en su propia pantalla. Ahí se cambian textos, colores, ilustraciones y cuáles se ven.'],
            ],

            'meta' => [
                'titulo' => 'Súmate a la meta',
                'resumen' => 'Las dos barras de progreso de la campaña y el kit de difusión.',
                'campos' => [
                    'antetitulo' => ['label' => 'Antetítulo', 'tipo' => 'texto', 'defecto' => 'Construyamos juntos'],
                    'titulo' => ['label' => 'Titular', 'tipo' => 'texto', 'defecto' => '¡Súmate y juntos llegaremos más lejos!'],
                    'bajada' => ['label' => 'Bajada', 'tipo' => 'texto', 'defecto' => 'Cada actividad y cada persona cuentan'],
                    'barra1_label' => ['label' => 'Primera barra — nombre', 'tipo' => 'texto', 'defecto' => 'Actividades solidarias'],
                    'barra1_actual' => ['label' => 'Primera barra — va por', 'tipo' => 'texto', 'defecto' => '500'],
                    'barra1_meta' => ['label' => 'Primera barra — meta', 'tipo' => 'texto', 'defecto' => '1.000'],
                    'barra2_label' => ['label' => 'Segunda barra — nombre', 'tipo' => 'texto', 'defecto' => 'Personas participando'],
                    'barra2_actual' => ['label' => 'Segunda barra — va por', 'tipo' => 'texto', 'defecto' => '50.000'],
                    'barra2_meta' => ['label' => 'Segunda barra — meta', 'tipo' => 'texto', 'defecto' => '100.000'],
                    'pregunta' => ['label' => 'Pregunta antes del botón', 'tipo' => 'texto', 'defecto' => '¿Quieres ayudar a llegar a la meta?'],
                    'cta_texto' => ['label' => 'Botón', 'tipo' => 'texto', 'defecto' => 'Descarga el kit de difusión'],
                    'cta_enlace' => ['label' => 'A dónde lleva el botón', 'tipo' => 'enlace', 'defecto' => '#kit'],
                    'nota' => ['label' => 'Nota bajo el botón', 'tipo' => 'texto', 'defecto' => 'Imágenes, stickers y textos para compartir en tus redes'],
                ],
                'ayuda' => 'El largo de cada barra se calcula solo a partir de los dos números. Se admiten puntos de miles: «1.000» cuenta como mil.',
            ],

            'actividades' => [
                'titulo' => 'Actividades destacadas',
                'resumen' => 'El carrusel de actividades de la portada.',
                'campos' => [
                    'antetitulo' => ['label' => 'Antetítulo', 'tipo' => 'texto', 'defecto' => 'Actividades destacadas'],
                    'titulo' => ['label' => 'Titular', 'tipo' => 'texto', 'defecto' => 'En cada región, múltiples actividades solidarias donde participar'],
                    'bajada' => ['label' => 'Bajada', 'tipo' => 'parrafo', 'defecto' => 'Conoce iniciativas abiertas a todo público, encuentra las más cercanas a ti y sé parte del movimiento.'],
                    'seleccion' => ['label' => 'Qué actividades salen', 'tipo' => 'opciones', 'defecto' => 'destacadas', 'opciones' => [
                        'destacadas' => 'Sólo las marcadas como destacadas (y si no hay, las próximas)',
                        'proximas' => 'Siempre las próximas publicadas, sin mirar si están destacadas',
                    ]],
                    'cuantas' => ['label' => 'Cuántas mostrar', 'tipo' => 'numero', 'defecto' => 9, 'min' => 1, 'max' => 24],
                    'cta_texto' => ['label' => 'Botón del final', 'tipo' => 'texto', 'defecto' => 'Conoce todas las actividades →'],
                    'vacio' => ['label' => 'Qué decir si no hay ninguna', 'tipo' => 'texto', 'defecto' => 'Todavía no hay actividades publicadas. Vuelve pronto.'],
                ],
                'crud' => ['ruta' => 'admin.activities.publicadas', 'texto' => 'Elegir cuáles se destacan', 'nota' => 'Una actividad se destaca desde su ficha, con el botón «Destacar».'],
            ],

            'que-es' => [
                'titulo' => '¿Qué es el Patrimonio Social?',
                'resumen' => 'El bloque explicativo, con su imagen.',
                'campos' => [
                    'titulo_antes' => ['label' => 'Titular — antes', 'tipo' => 'texto', 'defecto' => '¿Qué es el'],
                    'titulo_destacado' => ['label' => 'Titular — parte en naranja', 'tipo' => 'texto', 'defecto' => 'Patrimonio Social'],
                    'titulo_despues' => ['label' => 'Titular — después', 'tipo' => 'texto', 'defecto' => '?'],
                    'cuerpo' => ['label' => 'Texto', 'tipo' => 'rico', 'defecto' => '<p>Es todo aquello que construimos cuando nos unimos para cuidar, compartir y colaborar con otras personas.</p><p>Es un patrimonio vivo que se fortalece con cada acción solidaria y que nos pertenece a todas y todos.</p>'],
                    'remate' => ['label' => 'Frase destacada del final', 'tipo' => 'texto', 'defecto' => 'Nuestro mayor Patrimonio Social es la solidaridad.'],
                    'cta_texto' => ['label' => 'Botón', 'tipo' => 'texto', 'defecto' => 'Conoce más'],
                    'cta_enlace' => ['label' => 'A dónde lleva el botón', 'tipo' => 'enlace', 'defecto' => '/actividades'],
                    'imagen' => ['label' => 'Imagen', 'tipo' => 'imagen', 'defecto' => 'img/group-people-shaking-hands-with-one-that-says-h-it.jpg'],
                    'imagen_alt' => ['label' => 'Descripción de la imagen', 'tipo' => 'texto', 'defecto' => 'Personas dándose la mano en una jornada solidaria'],
                ],
            ],

            'por-que' => [
                'titulo' => '¿Por qué celebramos este día?',
                'resumen' => 'El texto del porqué, con el video de la campaña al lado.',
                'campos' => [
                    'video' => ['label' => 'Video de YouTube', 'tipo' => 'youtube', 'defecto' => 'e8iqqzO3s7k', 'ayuda' => 'Pega el enlace del video o sólo su identificador. Déjalo vacío para que el video no aparezca.'],
                    'antetitulo' => ['label' => 'Antetítulo', 'tipo' => 'texto', 'defecto' => '¿Por qué celebramos este día?'],
                    'titulo' => ['label' => 'Titular', 'tipo' => 'texto', 'defecto' => 'Un movimiento para celebrar y fortalecer la solidaridad'],
                    'cuerpo' => ['label' => 'Texto', 'tipo' => 'rico', 'defecto' => '<p>Celebramos este día porque creemos en la fuerza de lo que construimos cuando actuamos juntos. El Día del Patrimonio Social invita a organizaciones, empresas, comunidades y personas a realizar acciones concretas que generen bienestar colectivo y motiven a otros a sumarse. ¡Todas y todos tenemos algo que aportar!</p>'],
                    'remate' => ['label' => 'Frase destacada del final', 'tipo' => 'texto', 'defecto' => 'Dar está en nuestra naturaleza.'],
                ],
            ],

            'voces' => [
                'titulo' => 'Voces del movimiento',
                'resumen' => 'La cabecera del carrusel de testimonios.',
                'campos' => [
                    'antetitulo' => ['label' => 'Antetítulo', 'tipo' => 'texto', 'defecto' => 'Participantes del Día del Patrimonio Social'],
                    'titulo' => ['label' => 'Titular', 'tipo' => 'texto', 'defecto' => 'Voces del movimiento'],
                ],
                'crud' => ['ruta' => 'admin.content.index', 'parametros' => ['tipo' => 'testimonios'], 'texto' => 'Editar los testimonios'],
            ],

            'cifras' => [
                'titulo' => 'Cifras y ediciones',
                'resumen' => 'Los contadores que se animan al bajar, y las ediciones anteriores.',
                'campos' => [
                    'titulo' => ['label' => 'Titular', 'tipo' => 'texto', 'defecto' => 'Chile está construyendo su Patrimonio Social y lo celebra desde 2024'],
                    'bajada' => ['label' => 'Bajada', 'tipo' => 'texto', 'defecto' => 'Ya somos:'],
                ],
                'crud' => ['ruta' => 'admin.content.index', 'parametros' => ['tipo' => 'cifras'], 'texto' => 'Editar las cifras', 'nota' => 'Las ediciones anteriores se editan en Contenido → Ediciones.'],
            ],

            'noticias' => [
                'titulo' => 'Noticias',
                'resumen' => 'Las últimas noticias de la portada.',
                'campos' => [
                    'antetitulo' => ['label' => 'Antetítulo', 'tipo' => 'texto', 'defecto' => 'Al día'],
                    'titulo' => ['label' => 'Titular', 'tipo' => 'texto', 'defecto' => 'Noticias'],
                    'cta_texto' => ['label' => 'Botón', 'tipo' => 'texto', 'defecto' => 'Ver todas'],
                    'cuantas' => ['label' => 'Cuántas mostrar', 'tipo' => 'numero', 'defecto' => 3, 'min' => 1, 'max' => 12],
                    'vacio' => ['label' => 'Qué decir si no hay ninguna', 'tipo' => 'texto', 'defecto' => 'Todavía no hay noticias publicadas.'],
                ],
                'crud' => ['ruta' => 'admin.content.index', 'parametros' => ['tipo' => 'noticias'], 'texto' => 'Editar las noticias'],
            ],

            'iniciativa' => [
                'titulo' => 'Es una iniciativa de',
                'resumen' => 'El crédito de la Comunidad de Organizaciones Solidarias.',
                'campos' => [
                    'texto_antes' => ['label' => 'Antes del nombre', 'tipo' => 'texto', 'defecto' => 'El'],
                    'texto_destacado' => ['label' => 'Nombre en naranja', 'tipo' => 'texto', 'defecto' => 'Día del Patrimonio Social'],
                    'texto_despues' => ['label' => 'Después del nombre', 'tipo' => 'texto', 'defecto' => 'es una iniciativa de:'],
                    'logo' => ['label' => 'Logo', 'tipo' => 'imagen', 'defecto' => 'img/logo-cos.png'],
                    'logo_alt' => ['label' => 'Descripción del logo', 'tipo' => 'texto', 'defecto' => 'Comunidad de Organizaciones Solidarias'],
                    'texto_final' => ['label' => 'Frase del final', 'tipo' => 'texto', 'defecto' => 'y sus organizaciones socias'],
                ],
            ],

            'partners' => [
                'titulo' => 'Partners — grilla',
                'resumen' => 'Auspician, participan y colaboran, en grilla de logos.',
                'campos' => [
                    'label_auspician' => ['label' => 'Título del primer grupo', 'tipo' => 'texto', 'defecto' => 'Auspician'],
                    'label_participan' => ['label' => 'Título del segundo grupo', 'tipo' => 'texto', 'defecto' => 'Participan'],
                    'label_colaboran' => ['label' => 'Título del tercer grupo', 'tipo' => 'texto', 'defecto' => 'Colaboran'],
                ],
                'crud' => ['ruta' => 'admin.content.index', 'parametros' => ['tipo' => 'partners'], 'texto' => 'Editar los logos'],
            ],

            'participantes' => [
                'titulo' => 'Partners — marquesina',
                'resumen' => 'La tira de logos que se desplaza sola.',
                'campos' => [
                    'antetitulo' => ['label' => 'Título de la tira', 'tipo' => 'texto', 'defecto' => 'Organizaciones e instituciones participantes'],
                ],
                'crud' => ['ruta' => 'admin.content.index', 'parametros' => ['tipo' => 'partners'], 'texto' => 'Editar los logos', 'nota' => 'La marquesina muestra los logos del grupo «participante».'],
            ],
        ];
    }

    /** El orden en el que se pinta el home cuando nadie lo ha cambiado. */
    public static function orden(): array
    {
        return array_keys(static::secciones());
    }

    /** @return array<string, mixed>|null */
    public static function seccion(string $clave): ?array
    {
        return static::secciones()[$clave] ?? null;
    }

    /** @return array<string, mixed> */
    public static function campos(string $clave): array
    {
        return static::secciones()[$clave]['campos'] ?? [];
    }

    /**
     * Todos los valores de partida de una sección.
     *
     * @return array<string, mixed>
     */
    public static function defectos(string $clave): array
    {
        return collect(static::campos($clave))->map(fn ($c) => $c['defecto'] ?? null)->all();
    }

    public static function existe(string $clave): bool
    {
        return isset(static::secciones()[$clave]);
    }

    public static function esFija(string $clave): bool
    {
        return (bool) (static::secciones()[$clave]['fija'] ?? false);
    }
}
