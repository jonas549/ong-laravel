<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use App\Support\CatalogoHome;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * La biblioteca de medios: guardar lo que se sube, indexar lo que ya estaba y
 * saber dónde se está usando cada archivo.
 *
 * **Las 75 imágenes del diseño no se mueven de `public/img`.** Se indexan
 * donde están. Moverlas obligaría a reescribir la ruta en seis tablas y en el
 * JSON del home, y a que el `git pull` siguiente no las devolviera a su sitio
 * viejo. Como todas las columnas guardan la ruta relativa a `public/`, indexar
 * no toca ni una fila de las otras tablas.
 */
class Biblioteca
{
    /**
     * Las columnas del proyecto que guardan una ruta de imagen.
     *
     * `participation_cards.icono` NO está aquí a propósito: guarda nombres de
     * icono (`user`, `cal`, `plus`), no rutas.
     *
     * @var array<int, array<string, string>>
     */
    public const COLUMNAS = [
        ['tabla' => 'posts', 'columna' => 'imagen', 'rotulo' => 'titulo', 'que' => 'Noticia', 'tipo' => 'noticias'],
        ['tabla' => 'editions', 'columna' => 'imagen', 'rotulo' => 'titulo', 'que' => 'Edición', 'tipo' => 'ediciones'],
        ['tabla' => 'testimonials', 'columna' => 'logo_path', 'rotulo' => 'autor', 'que' => 'Testimonio', 'tipo' => 'testimonios'],
        ['tabla' => 'partners', 'columna' => 'logo_path', 'rotulo' => 'nombre', 'que' => 'Partner', 'tipo' => 'partners'],
        ['tabla' => 'organizations', 'columna' => 'logo_path', 'rotulo' => 'nombre', 'que' => 'Organización', 'tipo' => ''],
        ['tabla' => 'activities', 'columna' => 'imagen_portada', 'rotulo' => 'titulo', 'que' => 'Actividad', 'tipo' => ''],
    ];

    /** Extensiones que la biblioteca reconoce como imagen. */
    public const EXTENSIONES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico'];

    /* ── Subir ──────────────────────────────────────────── */

    /**
     * Guarda un archivo subido y lo deja indexado.
     *
     * Va a `storage/app/public/medios/AAAA/MM`, no a `public/img`: lo subido no
     * se versiona, y repartirlo por año y mes evita el directorio con miles de
     * archivos que acaba siendo lento de listar en un hosting compartido.
     */
    public function guardar(UploadedFile $archivo, ?string $carpeta = null, ?User $usuario = null): Media
    {
        $nombreOriginal = $archivo->getClientOriginalName();
        $extension = strtolower($archivo->getClientOriginalExtension() ?: $archivo->guessExtension() ?: 'bin');

        $subcarpeta = 'medios/'.now()->format('Y/m');
        $nombreArchivo = $this->nombreLibre($subcarpeta, $nombreOriginal, $extension);

        $archivo->storeAs($subcarpeta, $nombreArchivo, 'public');

        $ruta = 'storage/'.$subcarpeta.'/'.$nombreArchivo;
        $absoluta = public_path($ruta);

        [$ancho, $alto] = $this->medidas($absoluta);

        return Media::create([
            'ruta' => $ruta,
            'origen' => Media::ORIGEN_SUBIDO,
            'nombre' => $nombreOriginal,
            'mime' => $this->mime($absoluta, $extension),
            'extension' => $extension,
            'peso' => is_file($absoluta) ? filesize($absoluta) : 0,
            'ancho' => $ancho,
            'alto' => $alto,
            'carpeta' => $carpeta ?: null,
            'subido_por' => $usuario?->id,
        ]);
    }

    /**
     * Reemplaza el archivo conservando la fila —y por tanto la URL— para que
     * todo lo que ya apunta ahí siga apuntando a algo.
     *
     * Sólo tiene sentido con lo subido: un archivo del repositorio lo repone el
     * siguiente `git pull`.
     */
    public function reemplazar(Media $medio, UploadedFile $archivo): bool
    {
        if ($medio->es_del_codigo) {
            return false;
        }

        $absoluta = public_path($medio->ruta);

        // Se escribe encima, con el mismo nombre: es lo que conserva la URL.
        $archivo->storeAs(
            dirname(str_replace('storage/', '', $medio->ruta)),
            basename($medio->ruta),
            'public'
        );

        clearstatcache(true, $absoluta);
        [$ancho, $alto] = $this->medidas($absoluta);

        $medio->update([
            'peso' => is_file($absoluta) ? filesize($absoluta) : 0,
            'ancho' => $ancho,
            'alto' => $alto,
            'mime' => $this->mime($absoluta, $medio->extension),
        ]);

        return true;
    }

    /* ── Indexar lo que ya estaba ───────────────────────── */

    /**
     * Recorre `public/img` y deja una fila por archivo. Idempotente: se puede
     * correr las veces que haga falta.
     *
     * @return array{nuevos: int, actualizados: int, total: int}
     */
    public function indexarCodigo(): array
    {
        $base = public_path('img');
        $nuevos = 0;
        $actualizados = 0;
        $total = 0;

        foreach ($this->archivosDe($base) as $absoluta) {
            $extension = strtolower(pathinfo($absoluta, PATHINFO_EXTENSION));

            if (! in_array($extension, self::EXTENSIONES, true)) {
                continue;
            }

            $total++;
            $ruta = 'img/'.str_replace('\\', '/', ltrim(substr($absoluta, strlen($base)), '\\/'));

            [$ancho, $alto] = $this->medidas($absoluta);

            $datos = [
                'origen' => Media::ORIGEN_CODIGO,
                'nombre' => basename($absoluta),
                'mime' => $this->mime($absoluta, $extension),
                'extension' => $extension,
                'peso' => filesize($absoluta),
                'ancho' => $ancho,
                'alto' => $alto,
            ];

            /*
             * La fecha sale del archivo, no del momento de indexar.
             *
             * Si no, las 75 del diseño nacen todas con la fecha del día en que
             * se corrió esto, y el filtro «subidas hoy» las devuelve todas.
             * `filemtime` es lo más cerca que se puede estar de cuándo entró
             * esa imagen al proyecto.
             */
            $fecha = \Illuminate\Support\Carbon::createFromTimestamp(filemtime($absoluta) ?: time());

            $medio = Media::withTrashed()->firstWhere('ruta', $ruta);

            if ($medio) {
                // El texto alternativo, el título y la carpeta los escribe una
                // persona: no se pisan al reindexar.
                $medio->fill($datos);
                $medio->created_at = $fecha;
                $medio->timestamps = false;
                $medio->updated_at = now();
                $medio->save();
                $actualizados++;
            } else {
                $medio = new Media($datos + ['ruta' => $ruta]);
                $medio->timestamps = false;
                $medio->created_at = $fecha;
                $medio->updated_at = now();
                $medio->save();
                $nuevos++;
            }
        }

        return ['nuevos' => $nuevos, 'actualizados' => $actualizados, 'total' => $total];
    }

    /* ── Dónde se usa ───────────────────────────────────── */

    /**
     * Todo lo que apunta a esta ruta.
     *
     * Se consulta con el `query builder` y no con los modelos para que dé igual
     * el `SoftDeletes` y los ámbitos globales: lo que importa es si **alguna
     * fila** la referencia, incluida una borrada en blando que se puede
     * restaurar.
     *
     * @return array<int, array{que: string, rotulo: string, url: ?string}>
     */
    public function usos(Media $medio): array
    {
        $usos = [];

        foreach (self::COLUMNAS as $sitio) {
            $filas = DB::table($sitio['tabla'])
                ->where($sitio['columna'], $medio->ruta)
                ->limit(25)
                ->get(['id', $sitio['rotulo']]);

            foreach ($filas as $fila) {
                $usos[] = [
                    'que' => $sitio['que'],
                    'rotulo' => (string) ($fila->{$sitio['rotulo']} ?? '#'.$fila->id),
                    'url' => $this->urlDe($sitio, (int) $fila->id),
                ];
            }
        }

        foreach ($this->usosEnHome($medio) as $uso) {
            $usos[] = $uso;
        }

        foreach ($this->usosPorDefecto($medio) as $uso) {
            $usos[] = $uso;
        }

        return $usos;
    }

    /**
     * Una imagen puede estar en uso **sin que ninguna fila la nombre**: los
     * valores por defecto de las secciones del home viven en `CatalogoHome`, en
     * el código, y sólo bajan a la base cuando alguien edita esa sección. El
     * logo de Comunidad y la imagen del hero son exactamente ese caso.
     *
     * Sin esto la biblioteca diría «sin usar» de una imagen que sostiene la
     * portada, y vaciar el campo la devolvería a un archivo que ya no está.
     *
     * @return array<int, array{que: string, rotulo: string, url: ?string}>
     */
    private function usosPorDefecto(Media $medio): array
    {
        $usos = [];

        foreach (CatalogoHome::secciones() as $slug => $meta) {
            foreach ($meta['campos'] ?? [] as $campo => $def) {
                if (($def['tipo'] ?? null) !== 'imagen' || ($def['defecto'] ?? null) !== $medio->ruta) {
                    continue;
                }

                $usos[] = [
                    'que' => 'Diseño original',
                    'rotulo' => ($meta['titulo'] ?? $slug).' — '.($def['label'] ?? $campo),
                    'url' => route('admin.home.editar', ['seccion' => $slug]),
                ];
            }
        }

        return $usos;
    }

    /**
     * Las secciones del home guardan sus campos en JSON, así que no se pueden
     * consultar con un `where` por columna. Son doce filas: se leen y se miran
     * en PHP, que además evita depender de `JSON_SEARCH`.
     *
     * Se mira también el borrador: si una imagen está sólo en el borrador,
     * borrarla rompería la sección en cuanto alguien publique.
     *
     * @return array<int, array{que: string, rotulo: string, url: ?string}>
     */
    private function usosEnHome(Media $medio): array
    {
        $campos = $this->camposImagenDelHome();

        if ($campos === []) {
            return [];
        }

        $usos = [];

        foreach (DB::table('home_sections')->get(['clave', 'contenido', 'borrador']) as $fila) {
            $meta = CatalogoHome::secciones()[$fila->clave] ?? null;

            foreach (['contenido' => '', 'borrador' => ' (en el borrador)'] as $columna => $sufijo) {
                $datos = json_decode((string) $fila->{$columna}, true);

                if (! is_array($datos)) {
                    continue;
                }

                foreach ($campos[$fila->clave] ?? [] as $campo) {
                    if (($datos[$campo] ?? null) !== $medio->ruta) {
                        continue;
                    }

                    $usos[] = [
                        'que' => 'Home',
                        'rotulo' => ($meta['titulo'] ?? $fila->clave).$sufijo,
                        'url' => route('admin.home.editar', ['seccion' => $fila->clave]),
                    ];
                }
            }
        }

        return $usos;
    }

    /**
     * Qué campo de qué sección del home es una imagen, según el catálogo. No se
     * escribe a mano aquí: el catálogo es quien lo sabe, y ya se quedó
     * desincronizada una lista paralela cuando el home pasó de nueve secciones
     * a doce.
     *
     * @return array<string, array<int, string>>
     */
    private function camposImagenDelHome(): array
    {
        $mapa = [];

        foreach (CatalogoHome::secciones() as $slug => $meta) {
            foreach ($meta['campos'] ?? [] as $campo => $def) {
                if (($def['tipo'] ?? null) === 'imagen') {
                    $mapa[$slug][] = $campo;
                }
            }
        }

        return $mapa;
    }

    private function urlDe(array $sitio, int $id): ?string
    {
        if ($sitio['tabla'] === 'activities') {
            return route('admin.activities.show', $id);
        }

        if ($sitio['tabla'] === 'organizations') {
            return route('admin.organizations.edit', $id);
        }

        return $sitio['tipo'] !== ''
            ? route('admin.content.index', ['tipo' => $sitio['tipo']])
            : null;
    }

    /* ── Los límites del servidor ───────────────────────── */

    /**
     * Lo que de verdad deja subir esta máquina, en bytes.
     *
     * Manda el menor de los tres, y hay que enseñarlo: pasarse de
     * `post_max_size` no da un error de validación, **llega una petición vacía**
     * —sin `$_FILES` y sin `$_POST`, así que sin token CSRF— y el usuario ve un
     * fallo de sesión que no tiene nada que ver con lo que hizo.
     *
     * @return array{subida: int, post: int, configurado: int, efectivo: int, archivos: int}
     */
    public function limites(): array
    {
        $subida = $this->aBytes(ini_get('upload_max_filesize'));
        $post = $this->aBytes(ini_get('post_max_size'));
        $configurado = (int) \App\Models\Setting::get('medios_peso_max_kb', 4096) * 1024;

        return [
            'subida' => $subida,
            'post' => $post,
            'configurado' => $configurado,
            'efectivo' => min(array_filter([$subida, $post, $configurado])),
            'archivos' => (int) ini_get('max_file_uploads') ?: 20,
        ];
    }

    private function aBytes(string|false $valor): int
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return 0;
        }

        $numero = (int) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g' => $numero * 1024 ** 3,
            'm' => $numero * 1024 ** 2,
            'k' => $numero * 1024,
            default => $numero,
        };
    }

    /* ── Cosas de archivos ──────────────────────────────── */

    /** @return array{0: ?int, 1: ?int} */
    private function medidas(string $absoluta): array
    {
        if (! is_file($absoluta)) {
            return [null, null];
        }

        // `getimagesize` no entiende SVG: es texto, no un mapa de bits.
        if (strtolower(pathinfo($absoluta, PATHINFO_EXTENSION)) === 'svg') {
            return $this->medidasSvg($absoluta);
        }

        $medidas = @getimagesize($absoluta);

        return $medidas ? [(int) $medidas[0], (int) $medidas[1]] : [null, null];
    }

    /**
     * Del SVG se saca `width`/`height` y, si no los trae, el `viewBox`. Muchos
     * logos sólo declaran el segundo.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function medidasSvg(string $absoluta): array
    {
        $cabecera = (string) @file_get_contents($absoluta, false, null, 0, 2048);

        if (preg_match('/\bwidth="([\d.]+)/', $cabecera, $w)
            && preg_match('/\bheight="([\d.]+)/', $cabecera, $h)) {
            return [(int) round((float) $w[1]), (int) round((float) $h[1])];
        }

        if (preg_match('/viewBox="[\d.\s-]*?([\d.]+)[,\s]+([\d.]+)"/', $cabecera, $v)) {
            return [(int) round((float) $v[1]), (int) round((float) $v[2])];
        }

        return [null, null];
    }

    /**
     * El tipo real, mirando el archivo y no la extensión, que es lo que dice
     * quien sube. Con SVG se fija a mano: `finfo` lo da como `text/plain` o
     * `image/svg` según la versión.
     */
    private function mime(string $absoluta, ?string $extension): ?string
    {
        if (strtolower((string) $extension) === 'svg') {
            return 'image/svg+xml';
        }

        if (! is_file($absoluta) || ! function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $absoluta) ?: null;
        finfo_close($finfo);

        return $mime;
    }

    /**
     * Un nombre que no pise a otro y que no dé problemas en una URL:
     * minúsculas, sin espacios ni acentos.
     */
    private function nombreLibre(string $subcarpeta, string $original, string $extension): string
    {
        $base = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'archivo';
        $base = Str::limit($base, 80, '');

        $nombre = $base.'.'.$extension;
        $intento = 1;

        while (is_file(storage_path('app/public/'.$subcarpeta.'/'.$nombre))) {
            $nombre = $base.'-'.(++$intento).'.'.$extension;
        }

        return $nombre;
    }

    /** @return \Generator<string> */
    private function archivosDe(string $base): \Generator
    {
        if (! is_dir($base)) {
            return;
        }

        $iterador = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterador as $archivo) {
            if ($archivo->isFile()) {
                yield $archivo->getPathname();
            }
        }
    }
}
