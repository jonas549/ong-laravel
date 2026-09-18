<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Activity extends Model
{
    use HasFactory, SoftDeletes;

    public const FORMATOS = ['Presencial', 'Online', 'Híbrido'];

    /**
     * Los cinco estados del flujo de moderación, con el copy que ve el
     * organizador. Viene del objeto ESTADOS del prototipo mi-cuenta.html.
     */
    public const ESTADOS = [
        'borrador' => [
            'txt' => 'Guardada sin enviar a revisión',
            'filtro' => 'Borradores',
            'bg' => '#f1f2f3', 'ink' => '#63666A', 'borde' => '#e4e6e8', 'tono' => '#c3c6ca',
        ],
        'revision' => [
            'txt' => 'Estamos revisando tu actividad',
            'filtro' => 'Estamos revisando',
            'bg' => '#fff8e6', 'ink' => '#8a6a00', 'borde' => '#f6e0c6', 'tono' => '#FAB600',
        ],
        'ajustes' => [
            'txt' => 'Necesitamos algunos ajustes',
            'filtro' => 'Necesita ajustes',
            'bg' => '#fdeaf0', 'ink' => '#a82249', 'borde' => '#f0cdd8', 'tono' => '#C63663',
        ],
        'publicada' => [
            'txt' => 'Tu actividad ya es parte del Día del Patrimonio Social',
            'filtro' => 'Publicadas',
            'bg' => '#eaf6f5', 'ink' => '#0d6b64', 'borde' => '#cbe7e5', 'tono' => '#5CB8B2',
        ],
        'cancelada' => [
            'txt' => 'Cancelada',
            'filtro' => 'Canceladas',
            'bg' => '#f1f2f3', 'ink' => '#63666A', 'borde' => '#e4e6e8', 'tono' => '#c3c6ca',
        ],
    ];

    protected $fillable = [
        'organization_id', 'titulo', 'slug', 'descripcion', 'formato',
        'fecha_inicio', 'fecha_termino', 'hora_inicio', 'hora_termino', 'sin_fecha_definida',
        'region_id', 'commune_id', 'direccion',
        'participantes_estimados', 'cupos_totales', 'cupos_disponibles',
        'abierta_publico', 'inscripcion_habilitada', 'tiene_accesibilidad',
        'accesibilidad_detalle', 'publico_otro', 'info_previa',
        'imagen_portada', 'correo_contacto',
        'estado', 'observaciones_revision', 'destacada', 'orden', 'published_at',
        'publicada_automaticamente',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_termino' => 'date',
            'published_at' => 'datetime',
            'publicada_automaticamente' => 'boolean',
            'sin_fecha_definida' => 'boolean',
            'abierta_publico' => 'boolean',
            'inscripcion_habilitada' => 'boolean',
            'tiene_accesibilidad' => 'boolean',
            'destacada' => 'boolean',
            'participantes_estimados' => 'integer',
            'cupos_totales' => 'integer',
            'cupos_disponibles' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $a) {
            if (blank($a->slug)) {
                $a->slug = static::slugUnico($a->titulo);
            }
        });
    }

    public static function slugUnico(string $titulo): string
    {
        $base = Str::slug($titulo) ?: 'actividad';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    // ── Relaciones ───────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function collaborators(): HasMany
    {
        return $this->hasMany(ActivityCollaborator::class)->orderBy('orden');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ActivityStatusLog::class)->latest();
    }

    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(TaxonomyTerm::class);
    }

    /** Términos ya cargados de un solo grupo (tema, caracteristica, publico, acceso). */
    public function termsDe(string $grupo)
    {
        return $this->terms->where('grupo', $grupo);
    }

    // ── Scopes ───────────────────────────────────────────────────

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('estado', 'publicada')->whereNotNull('published_at');
    }

    public function scopeFeatured(Builder $q): Builder
    {
        return $q->where('destacada', true);
    }

    public function scopeByRegion(Builder $q, $regionId): Builder
    {
        return $regionId ? $q->where('region_id', $regionId) : $q;
    }

    public function scopeByCommune(Builder $q, $communeId): Builder
    {
        return $communeId ? $q->where('commune_id', $communeId) : $q;
    }

    public function scopeByFormato(Builder $q, $formato): Builder
    {
        return $formato ? $q->where('formato', $formato) : $q;
    }

    public function scopeByTerm(Builder $q, $termId): Builder
    {
        return $termId
            ? $q->whereHas('terms', fn ($t) => $t->where('taxonomy_terms.id', $termId))
            : $q;
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->where(function ($w) {
            $w->whereDate('fecha_inicio', '>=', now()->toDateString())
                ->orWhere('sin_fecha_definida', true);
        });
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('orden')->orderBy('fecha_inicio');
    }

    // ── Accessors ────────────────────────────────────────────────

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado]['txt'] ?? (string) $this->estado;
    }

    public function getEstadoFiltroAttribute(): string
    {
        return self::ESTADOS[$this->estado]['filtro'] ?? (string) $this->estado;
    }

    public function getEstadoColorAttribute(): array
    {
        return self::ESTADOS[$this->estado] ?? self::ESTADOS['borrador'];
    }

    /** Formato corto que usan las tarjetas del home, tipo "Sáb 26 jul". */
    public function getFechaCortaAttribute(): string
    {
        if ($this->sin_fecha_definida || ! $this->fecha_inicio) {
            return 'Por definir';
        }

        return Str::ucfirst($this->fecha_inicio->locale('es')->isoFormat('ddd D MMM'));
    }

    /** El formato del listado de "Mi cuenta": "26 julio 2026". */
    public function getFechaListaAttribute(): string
    {
        if ($this->sin_fecha_definida || ! $this->fecha_inicio) {
            return 'Fecha por definir';
        }

        return $this->fecha_inicio->locale('es')->isoFormat('D MMMM YYYY');
    }

    public function getFechaLargaAttribute(): string
    {
        if ($this->sin_fecha_definida || ! $this->fecha_inicio) {
            return 'Fecha por definir';
        }

        return $this->fecha_inicio->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    }

    /** "Vie 4 dic · 09:00-13:00 · Recoleta", el resumen del paso 5 del wizard. */
    public function getResumenFechaLugarAttribute(): string
    {
        $horas = collect([$this->hora_inicio, $this->hora_termino])
            ->filter()
            ->map(fn ($h) => substr((string) $h, 0, 5))
            ->implode('-');

        return collect([$this->fecha_corta, $horas, $this->commune?->nombre])
            ->filter()
            ->implode(' · ');
    }

    public function getLugarAttribute(): string
    {
        return collect([$this->commune?->nombre, $this->region?->nombre])
            ->filter()->implode(', ') ?: 'Por definir';
    }

    public function getImagenUrlAttribute(): string
    {
        return $this->imagen_portada
            ? asset($this->imagen_portada)
            : asset('img/dps-banner-2560x1080-010726.jpg');
    }

    public function getInscritosCountAttribute(): int
    {
        return $this->registrations()->where('estado', '!=', 'cancelado')->count();
    }

    /**
     * La clave de la URL publica: «5/jornada-de-reforestacion».
     *
     * Punto 1 de la tanda del 11/09. Hasta ahora la ficha se dirigia solo por
     * slug, y dos actividades con el mismo titulo —lo normal cuando la misma
     * jornada se repite en otra ciudad o el ano siguiente— se peleaban por la
     * direccion: la segunda acababa en «impermeabiliza-3», que no dice nada a
     * nadie. Con la ID delante, cada ficha tiene una direccion propia y el
     * slug pasa a ser lo que siempre debio ser, texto para que se lea.
     *
     * ── Por que un atributo y no `getRouteKey()` ──
     *
     * `getRouteKey()` es global: lo usan TODAS las rutas que reciben una
     * actividad, incluidas las del panel (`/admin/actividades/{activity}`) y
     * las de mi-cuenta, que van por id. Cambiarlo ahi las habria roto todas.
     * Con un atributo, la ruta publica pide `{activity:id_slug}` y las demas
     * siguen con su id.
     *
     * La barra sobrevive a la generacion de la URL porque Laravel la deja sin
     * escapar a proposito (`RouteUrlGenerator`, mapa `dontEncode`).
     */
    public function getIdSlugAttribute(): string
    {
        return $this->id.'/'.$this->slug;
    }

    /**
     * Manda la ID; el slug es decoracion.
     *
     * Que el slug NO decida es justamente lo que hace que un enlace impreso o
     * ya enviado por correo siga funcionando cuando la ONG le cambia el titulo
     * a la actividad: cambia el texto, la ID no. Si el slug que llega no es el
     * de ahora, el controlador redirige al bueno en vez de dar un 404.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field === 'id_slug') {
            $id = (int) strtok((string) $value, '/');

            return $id > 0 ? $this->newQuery()->whereKey($id)->first() : null;
        }

        return parent::resolveRouteBinding($value, $field);
    }

    public function puedeRecibirInscripciones(): bool
    {
        return $this->estado === 'publicada'
            && $this->inscripcion_habilitada
            && ($this->cupos_disponibles === null || $this->cupos_disponibles > 0);
    }

    /**
     * Por qué esta actividad no admite inscripciones, para poder decirlo bien.
     *
     * Hasta el 11/09 los tres motivos daban el mismo aviso —«Esta actividad no
     * está recibiendo inscripciones»—, y el que más se ve es justo el que peor
     * se leía: el organizador que marca «sin inscripción previa» está diciendo
     * «ven sin más», y al asistente le llegaba algo que suena a puerta cerrada.
     *
     * El orden importa. `inscripcion_habilitada` manda sobre los cupos: si no
     * se pide inscripción, no hay cupos que agotar, y un `cupos_disponibles` a
     * cero de una actividad sin inscripción es un dato muerto, no un motivo.
     *
     * Devuelve `null` cuando sí admite inscripciones.
     */
    public function motivoSinInscripciones(): ?string
    {
        if ($this->puedeRecibirInscripciones()) {
            return null;
        }

        if (! $this->inscripcion_habilitada) {
            return 'sin_inscripcion_previa';
        }

        if ($this->cupos_disponibles !== null && $this->cupos_disponibles <= 0) {
            return 'cupos_agotados';
        }

        return 'no_publicada';
    }

    /** El aviso que va en la ficha pública, ya redactado. */
    public function avisoSinInscripciones(): ?string
    {
        return match ($this->motivoSinInscripciones()) {
            'sin_inscripcion_previa' => 'No es necesario inscripción previa. ¡Te esperamos en la actividad!',
            // Reservado para los cupos, por decisión del cliente del 11/09.
            'cupos_agotados', 'no_publicada' => 'Esta actividad no está recibiendo inscripciones.',
            default => null,
        };
    }
}
