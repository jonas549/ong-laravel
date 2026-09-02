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
        'imagen_portada', 'correo_contacto', 'enlace_red_social', 'enlace_web',
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

    public function puedeRecibirInscripciones(): bool
    {
        return $this->estado === 'publicada'
            && $this->inscripcion_habilitada
            && ($this->cupos_disponibles === null || $this->cupos_disponibles > 0);
    }
}
