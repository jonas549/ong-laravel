<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * El árbol del panel, en un solo sitio.
 *
 * De aquí salen tres cosas que antes había que mantener a mano y por separado:
 * el menú lateral, la marca de sección activa y las migas de pan. Si un nodo
 * cambia de nombre o de sitio, cambia en los tres a la vez.
 *
 * Cada nodo tiene:
 *   'texto'   → lo que se lee
 *   'ruta'    → nombre de ruta, o null si sólo agrupa
 *   'params'  → parámetros de la ruta
 *   'activo'  → patrón(es) extra para marcarlo activo (routeIs)
 *   'hijos'   → nodos de dentro
 */
class MenuPanel
{
    /**
     * Sub-secciones del home. Son las mismas que pinta la portada, en el
     * mismo orden en que aparecen ahí.
     */

    /** @return array<int, array<string, mixed>> */
    public static function arbol(): array
    {
        return [
            self::nodo('Dashboard', 'admin.dashboard'),

            self::nodo('Actividades', null, [], [
                self::nodo('Todas las actividades', 'admin.activities.index'),
                self::nodo('Pendientes de revisión', 'admin.activities.pendientes'),
                self::nodo('Publicadas', 'admin.activities.publicadas'),
                self::nodo('Canceladas', 'admin.activities.canceladas'),
            ], 'admin.activities.show'),

            self::nodo('Inscripciones', null, [], [
                self::nodo('Todas', 'admin.registrations.index'),
                self::nodo('Exportar', 'admin.registrations.exportar', [], [], 'admin.registrations.descargar'),
            ]),

            self::nodo('Organizaciones', null, [], [
                self::nodo('Listado', 'admin.organizations.index'),
                self::nodo('Verificación', 'admin.organizations.verificacion'),
            ]),

            self::nodo('Páginas', null, [], [
                self::nodo('Home', null, [], self::seccionesHome(), 'admin.home.*'),
                self::nodo('Páginas sueltas', 'admin.content.index', ['tipo' => 'paginas'], [], 'admin.content.*'),
                self::nodo('Política de privacidad', 'admin.paginas.privacidad'),
            ]),

            self::nodo('Contenido', null, [], [
                self::nodo('Noticias', 'admin.content.index', ['tipo' => 'noticias'], [], 'admin.content.*'),
                self::nodo('Ediciones', 'admin.content.index', ['tipo' => 'ediciones'], [], 'admin.content.*'),
                self::nodo('Testimonios', 'admin.content.index', ['tipo' => 'testimonios'], [], 'admin.content.*'),
                self::nodo('Partners', 'admin.content.index', ['tipo' => 'partners'], [], 'admin.content.*'),
                self::nodo('Cifras', 'admin.content.index', ['tipo' => 'cifras'], [], 'admin.content.*'),
                self::nodo('Tarjetas de "¿cómo participar?"', 'admin.content.index', ['tipo' => 'tarjetas'], [], 'admin.content.*'),
            ]),

            self::nodo('Catálogos', null, [], [
                self::nodo('Temas', 'admin.taxonomies.index', ['grupo' => 'tema'], [], 'admin.taxonomies.*'),
                self::nodo('Características', 'admin.taxonomies.index', ['grupo' => 'caracteristica'], [], 'admin.taxonomies.*'),
                self::nodo('Públicos', 'admin.taxonomies.index', ['grupo' => 'publico'], [], 'admin.taxonomies.*'),
                self::nodo('Accesibilidad', 'admin.taxonomies.index', ['grupo' => 'acceso'], [], 'admin.taxonomies.*'),
                self::nodo('Regiones y comunas', 'admin.regiones.index'),
            ]),

            self::nodo('Usuarios', null, [], [
                self::nodo('Administradores', 'admin.users.index', ['rol' => 'admin'], [], 'admin.users.*'),
                self::nodo('Organizadores', 'admin.users.index', ['rol' => 'organizer'], [], 'admin.users.*'),
            ]),

            self::nodo('Configuración', null, [], [
                self::nodo('General', 'admin.settings.general'),
                self::nodo('SMTP', 'admin.settings.smtp'),
                self::nodo('Registro de correos', 'admin.emails.index', [], [], 'admin.emails.*'),
                self::nodo('SEO', 'admin.settings.seo'),

                // Estos dos no están en el árbol del backlog, pero son pantallas
                // que ya existen de los bloques A y B y sin un enlace aquí no
                // habría forma de llegar a ellas.
                self::nodo('Plantillas de correo', 'admin.templates.index', [], [], 'admin.templates.*'),
                self::nodo('Registro de accesos', 'admin.accesos.index', [], [], 'admin.accesos.*'),
            ]),
        ];
    }

    /**
     * Camino desde la raíz hasta el nodo activo, para las migas de pan.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function camino(): array
    {
        foreach (self::arbol() as $nodo) {
            if ($encontrado = self::buscarActivo($nodo)) {
                return $encontrado;
            }
        }

        return [];
    }

    /** ¿Este nodo, o algo de dentro, es la pantalla en la que estamos? */
    public static function activo(array $nodo): bool
    {
        foreach ($nodo['activo'] as $patron) {
            if (request()->routeIs($patron) && self::parametrosCoinciden($nodo['params'])) {
                return true;
            }
        }

        if ($nodo['ruta'] && request()->routeIs($nodo['ruta'])) {
            // Con parámetros no basta el nombre de la ruta: "Temas" y
            // "Públicos" son la misma ruta con distinto grupo.
            return self::parametrosCoinciden($nodo['params']);
        }

        foreach ($nodo['hijos'] as $hijo) {
            if (self::activo($hijo)) {
                return true;
            }
        }

        return false;
    }

    /** La URL del nodo, o null si sólo agrupa. */
    public static function url(array $nodo): ?string
    {
        if (! $nodo['ruta'] || ! Route::has($nodo['ruta'])) {
            return null;
        }

        return route($nodo['ruta'], $nodo['params']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $hijos
     * @param  string|array<int, string>  $activo
     * @return array<string, mixed>
     */
    private static function nodo(string $texto, ?string $ruta, array $params = [], array $hijos = [], string|array $activo = []): array
    {
        return [
            'texto' => $texto,
            'ruta' => $ruta,
            'params' => $params,
            'hijos' => $hijos,
            'activo' => (array) $activo,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function seccionesHome(): array
    {
        /*
         * La lista sale de CatalogoHome, que es quien sabe qué secciones
         * existen y cómo se llaman. Antes había aquí una constante propia con
         * los mismos nombres; dos listas que hay que mantener de acuerdo acaban
         * desincronizadas, y ésta ya se quedó corta cuando el bloque F pasó de
         * nueve secciones a doce.
         */
        $nodos = [self::nodo('Todas las secciones', 'admin.home.index')];

        foreach (\App\Support\CatalogoHome::secciones() as $slug => $meta) {
            $nodos[] = self::nodo($meta['titulo'], 'admin.home.editar', ['seccion' => $slug]);
        }

        return $nodos;
    }

    /**
     * Los parámetros del nodo tienen que estar en la petición con el mismo
     * valor. Los que no vienen en la URL se comparan contra el valor por
     * defecto del propio nodo, para que "Todas las actividades" no se marque
     * cuando la URL trae `?estado=cancelada`.
     */
    private static function parametrosCoinciden(array $params): bool
    {
        foreach ($params as $clave => $valor) {
            $actual = request()->route($clave) ?? request()->query($clave);

            if ((string) $actual !== (string) $valor) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private static function buscarActivo(array $nodo, array $camino = []): ?array
    {
        $camino[] = $nodo;

        foreach ($nodo['hijos'] as $hijo) {
            if ($encontrado = self::buscarActivo($hijo, $camino)) {
                return $encontrado;
            }
        }

        // Sin hijos activos: sólo cuenta si lo es este mismo, y no por herencia.
        if ($nodo['ruta'] && request()->routeIs($nodo['ruta']) && self::parametrosCoinciden($nodo['params'])) {
            return $camino;
        }

        foreach ($nodo['activo'] as $patron) {
            if (request()->routeIs($patron) && self::parametrosCoinciden($nodo['params'])) {
                return $camino;
            }
        }

        return null;
    }
}
