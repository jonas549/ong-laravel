<?php

namespace App\Models;

use App\Services\SanitizadorHtml;
use App\Support\CatalogoHome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Una sección del home.
 *
 * **Guarda sólo lo que se ha cambiado.** Lo que no está aquí se lee de
 * `CatalogoHome`, que tiene los textos del HTML fuente copiados uno a uno. Eso
 * es la regla 5 del bloque y además una red de seguridad concreta para este
 * proyecto: el cron del servidor migra pero nunca siembra, así que si la base
 * mandara, el home quedaría en blanco tras el primer despliegue con la tabla
 * recién creada.
 *
 * `contenido` es lo publicado; `borrador` es lo que va escribiendo el
 * autoguardado. Están separados a propósito: si compartieran columna, escribir
 * en el panel cambiaría el sitio público a media frase.
 */
class HomeSection extends Model
{
    protected $fillable = ['clave', 'orden', 'activo', 'contenido', 'borrador', 'borrador_at', 'borrador_por'];

    protected function casts(): array
    {
        return [
            'contenido' => 'array',
            'borrador' => 'array',
            'activo' => 'boolean',
            'borrador_at' => 'datetime',
        ];
    }

    /** @var Collection<string, self>|null */
    private static ?Collection $cache = null;

    public function versions(): HasMany
    {
        return $this->hasMany(HomeSectionVersion::class)->latest('id');
    }

    public function getRouteKeyName(): string
    {
        return 'clave';
    }

    /* ------------------------------------------------------------ lectura */

    /**
     * Todas las secciones tal como hay que pintarlas, en su orden y sin las
     * apagadas.
     *
     * Si la tabla todavía no existe —una petición entre el `git pull` y el
     * `migrate` del cron, que dura minutos— se devuelve el orden del catálogo.
     * El home se pinta igual; lo que no puede es reventar por eso.
     *
     * @return Collection<int, self>
     */
    public static function visibles(): Collection
    {
        $guardadas = static::todas();

        return collect(CatalogoHome::orden())
            ->map(fn (string $clave) => $guardadas->get($clave) ?? static::sinGuardar($clave))
            ->filter(fn (self $s) => $s->activo)
            ->sortBy(fn (self $s) => $s->orden)
            ->values();
    }

    /**
     * Una sección por su clave, exista o no en la base.
     */
    public static function de(string $clave): self
    {
        return static::todas()->get($clave) ?? static::sinGuardar($clave);
    }

    /**
     * Las filas que hay, indexadas por clave y cacheadas por petición.
     *
     * La caché es de proceso, no de `Cache::`: el cron de despliegue vacía la
     * caché cada cinco minutos, así que nada que deba sobrevivir puede vivir
     * ahí. Aquí sólo se evita repetir la misma consulta doce veces al pintar
     * el home.
     *
     * @return Collection<string, self>
     */
    public static function todas(): Collection
    {
        if (static::$cache !== null) {
            return static::$cache;
        }

        if (! Schema::hasTable('home_sections')) {
            return static::$cache = collect();
        }

        return static::$cache = static::query()->get()->keyBy('clave');
    }

    public static function olvidarCache(): void
    {
        static::$cache = null;
    }

    /**
     * Todas las secciones para el panel, en su orden y con las apagadas.
     *
     * @return Collection<int, self>
     */
    public static function ordenadas(): Collection
    {
        return static::query()->orderBy('orden')->orderBy('id')->get();
    }

    /**
     * Crea las filas que falten, con el orden del catálogo.
     *
     * Se llama al abrir el panel y no sólo desde `dps:instalar`, porque el cron
     * del servidor migra pero **nunca siembra**: sin esto, la primera vez que
     * alguien entrara al editor tras el despliegue no habría nada que ordenar
     * ni que apagar. El sitio público funciona igual sin filas —de eso se
     * encarga el catálogo— pero arrastrar y apagar necesitan una fila donde
     * escribir.
     *
     * Idempotente: no toca las que ya existen.
     */
    public static function sembrarLasQueFalten(): int
    {
        $existentes = static::query()->pluck('clave')->all();
        $creadas = 0;

        foreach (CatalogoHome::orden() as $posicion => $clave) {
            if (in_array($clave, $existentes, true)) {
                continue;
            }

            static::create(['clave' => $clave, 'orden' => $posicion + 1, 'activo' => true]);
            $creadas++;
        }

        if ($creadas) {
            static::olvidarCache();
        }

        return $creadas;
    }

    /** Una sección que todavía no tiene fila: todo por defecto y encendida. */
    private static function sinGuardar(string $clave): self
    {
        $seccion = new self([
            'clave' => $clave,
            'orden' => array_search($clave, CatalogoHome::orden(), true) ?: 0,
            'activo' => true,
        ]);

        $seccion->exists = false;

        return $seccion;
    }

    /* ------------------------------------------------------------- valores */

    /**
     * El valor de un campo: lo guardado si lo hay, y si no el del HTML fuente.
     *
     * Se comprueba con `blank()` y no con `isset()` a propósito: un campo que
     * alguien vació entero tiene que volver al texto original, no dejar un
     * hueco en el home. Vaciar un campo es «devuélvelo a como estaba», que es
     * lo que espera quien lo borra sin querer.
     */
    public function valor(string $campo, bool $borrador = false): mixed
    {
        $fuente = $borrador ? ($this->borrador ?? $this->contenido) : $this->contenido;
        $guardado = $fuente[$campo] ?? null;

        if (blank($guardado) && $guardado !== 0 && $guardado !== '0') {
            return CatalogoHome::campos($this->clave)[$campo]['defecto'] ?? null;
        }

        return $guardado;
    }

    /** Texto plano, escapado por Blade en la plantilla. */
    public function texto(string $campo, bool $borrador = false): string
    {
        return (string) $this->valor($campo, $borrador);
    }

    /**
     * HTML de un campo rico, ya limpio.
     *
     * Se vuelve a limpiar **al pintar** y no sólo al guardar. Guardar limpio es
     * lo correcto, pero si algún día entra HTML por otra vía —una importación,
     * una fila tocada a mano en la base, un cambio en la lista blanca— esta
     * segunda pasada es la que impide que llegue al navegador. Cuesta poco y es
     * la diferencia entre confiar en el pasado y no tener que hacerlo.
     */
    public function rico(string $campo, bool $borrador = false): string
    {
        return app(SanitizadorHtml::class)->limpiar((string) $this->valor($campo, $borrador));
    }

    public function numero(string $campo, bool $borrador = false): int
    {
        return (int) $this->valor($campo, $borrador);
    }

    /** Una ruta de `public/` lista para `asset()`, o cadena vacía. */
    public function imagen(string $campo, bool $borrador = false): string
    {
        return app(SanitizadorHtml::class)->rutaImagen((string) $this->valor($campo, $borrador));
    }

    public function enlace(string $campo, bool $borrador = false): string
    {
        return app(SanitizadorHtml::class)->enlace((string) $this->valor($campo, $borrador));
    }

    public function video(string $campo, bool $borrador = false): ?string
    {
        return app(SanitizadorHtml::class)->idDeYoutube((string) $this->valor($campo, $borrador));
    }

    /* -------------------------------------------------------------- estado */

    public function tieneBorrador(): bool
    {
        return filled($this->borrador) && $this->borrador !== ($this->contenido ?? []);
    }

    public function esFija(): bool
    {
        return CatalogoHome::esFija($this->clave);
    }

    public function tituloAdmin(): string
    {
        return CatalogoHome::seccion($this->clave)['titulo'] ?? $this->clave;
    }

    /** Cuántos campos se han cambiado respecto del HTML fuente. */
    public function camposCambiados(): int
    {
        $defectos = CatalogoHome::defectos($this->clave);

        return collect($this->contenido ?? [])
            ->filter(fn ($v, $k) => filled($v) && ($defectos[$k] ?? null) !== $v)
            ->count();
    }
}
