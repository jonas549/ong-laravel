<?php

namespace App\Models;

use App\Support\Filtro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    /** Los cinco tipos del paso 2 del wizard. */
    public const TIPOS = [
        'Organización sin fines de lucro',
        'Empresa o institución privada',
        'Institución educativa',
        'Municipalidad u organismo público',
        'Otra',
    ];

    protected $fillable = [
        'user_id', 'nombre', 'slug', 'tipo', 'tipo_otro', 'descripcion', 'logo_path',
        'num_voluntarios', 'unidad_educativa', 'correo_contacto', 'enlace_web',
        'enlace_red_social', 'anios_participacion', 'marquesina_orden', 'verificada', 'activo', 'requiere_revision',
    ];

    protected function casts(): array
    {
        return [
            'verificada' => 'boolean',
            'activo' => 'boolean',
            'requiere_revision' => 'boolean',
            'num_voluntarios' => 'integer',
            'marquesina_orden' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $org) {
            if (blank($org->slug)) {
                $org->slug = static::slugUnico($org->nombre);
            }
        });
    }

    /**
     * Sin reclamar: está en el listado pero todavía no tiene cuenta.
     *
     * Es lo que deja el importador del listado histórico. La primera persona
     * que la elija en el wizard y ponga su contraseña se queda con ella.
     */
    public function scopeSinReclamar(Builder $q): Builder
    {
        return $q->whereNull('user_id');
    }

    public function estaSinReclamar(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Busca organizaciones por nombre para el autocompletado del wizard.
     *
     * Devuelve las dos clases y las distingue, en vez de esconder las que ya
     * tienen cuenta: quien escribe el nombre de su organización y no la ve
     * vuelve a crearla con una variante del nombre, que es exactamente cómo
     * aparecieron los duplicados que hay hoy en producción. Verla y que le
     * digan «ésta ya tiene cuenta, inicia sesión» le lleva a donde tiene que ir.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function buscarPorNombre(string $texto, int $tope = 8)
    {
        $texto = trim($texto);

        if (mb_strlen($texto) < 2) {
            return collect();
        }

        // `Filtro::like` es el que ya usa el resto del panel para esto.
        $como = '%'.Filtro::like($texto).'%';

        return static::query()
            ->where('activo', true)
            ->where('nombre', 'like', $como)
            /*
             * Las que empiezan por lo escrito, primero. Sin esto, escribir
             * «del» ofrece antes «Fundación Aldea del Sur» que «Delta», que es
             * lo que casi seguro se estaba buscando.
             */
            ->orderByRaw('CASE WHEN nombre LIKE ? THEN 0 ELSE 1 END', [Filtro::like($texto).'%'])
            ->orderBy('nombre')
            ->limit($tope)
            ->get(['id', 'nombre', 'tipo', 'tipo_otro', 'user_id'])
            ->map(fn (self $o) => [
                'id' => $o->id,
                'nombre' => $o->nombre,
                'tipo' => $o->tipo,
                'tipo_otro' => $o->tipo_otro,
                'libre' => $o->estaSinReclamar(),
            ]);
    }

    /**
     * Qué le falta a esta organización para no tener que volver a preguntarle
     * nada en el paso 3 del wizard (C4).
     *
     * **Incluye el logo**, y es una decisión del cliente, no un descuido: una
     * organización sin logo sale en el sitio con sus iniciales, y el paso 3
     * es el único sitio donde se le puede pedir sin interrumpirla después.
     *
     * Los dos condicionales son los mismos que exige el formulario: «Otra»
     * pide describirse, e «Institución educativa» pide la unidad. Si algún día
     * se añade otro campo obligatorio al paso 3, va aquí — si no, el wizard lo
     * saltaría dando por completo lo que no lo está.
     *
     * @return array<int, string> las claves de los campos que faltan
     */
    public function datosQueFaltan(): array
    {
        $faltan = [];

        if (blank($this->nombre)) {
            $faltan[] = 'org_nombre';
        }

        if (blank($this->tipo)) {
            $faltan[] = 'org_tipo';
        }

        if ($this->tipo === 'Otra' && blank($this->tipo_otro)) {
            $faltan[] = 'org_tipo_otro';
        }

        if ($this->tipo === 'Institución educativa' && blank($this->unidad_educativa)) {
            $faltan[] = 'org_unidad_educativa';
        }

        if (blank($this->logo_path)) {
            $faltan[] = 'org_logo';
        }

        return $faltan;
    }

    /** Si no hay nada que preguntarle: el wizard puede saltarse el paso 3. */
    public function fichaCompleta(): bool
    {
        return $this->datosQueFaltan() === [];
    }

    public static function slugUnico(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'organizacion';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * Las que se ven. Una organizacion apagada deja de salir en los listados
     * publicos, pero no se lleva por delante sus actividades ni las
     * inscripciones de esas actividades: por eso se apaga en vez de borrarse.
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Las inscripciones de todas sus actividades.
     *
     * Existe para poder decir en pantalla que se llevaria por delante un
     * borrado: «7 actividades y 24 inscripciones» se entiende, y «no se puede
     * eliminar» a secas, no.
     */
    public function registrations(): HasManyThrough
    {
        return $this->hasManyThrough(Registration::class, Activity::class);
    }

    /** El logo listo para pintar, o null si esta organización no subió ninguno. */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset($this->logo_path) : null;
    }

    /**
     * Las iniciales, para la ficha pública cuando no hay logo.
     *
     * No todas las organizaciones suben uno —hoy ninguna de las tres de local—
     * y dejar el hueco vacío se lee como una imagen rota. Dos letras dan algo
     * reconocible sin inventar un logo que no existe.
     *
     * Las palabras de enlace no cuentan: «Fundación de la Casa» da «FC» y no
     * «FD», que no diría nada.
     */
    public function getInicialesAttribute(): string
    {
        $vacias = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'e', 'para', 'por', 'en', 'a'];

        $palabras = preg_split('/\s+/u', trim((string) $this->nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $utiles = array_values(array_filter(
            $palabras,
            fn ($p) => ! in_array(mb_strtolower($p), $vacias, true),
        ));

        $fuente = $utiles ?: $palabras;

        $letras = mb_substr($fuente[0] ?? '', 0, 1)
            .(count($fuente) > 1 ? mb_substr($fuente[1], 0, 1) : '');

        return mb_strtoupper($letras) ?: '·';
    }

    /** El nombre del tipo, resolviendo el campo libre cuando es "Otra". */
    public function getTipoLabelAttribute(): string
    {
        return $this->tipo === 'Otra' && filled($this->tipo_otro)
            ? $this->tipo_otro
            : (string) $this->tipo;
    }

    public function esEmpresa(): bool
    {
        return $this->tipo === 'Empresa o institución privada';
    }

    public function esEducativa(): bool
    {
        return $this->tipo === 'Institución educativa';
    }
}
