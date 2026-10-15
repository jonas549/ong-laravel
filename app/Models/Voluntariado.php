<?php

namespace App\Models;

use App\Support\Fecha;
use App\Support\Filtro;
use App\Support\RangoDeFechas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Una oportunidad de Voluntariados Chile, tal como la dejó la última
 * sincronización. Ver la migración `2025_02_11_000001` y
 * `App\Services\SincronizadorVoluntariados`.
 *
 * Es de sólo lectura para el sitio: aquí no se escribe nada a mano, y la
 * postulación se hace en su sitio, no en el nuestro.
 */
class Voluntariado extends Model
{
    protected $table = 'voluntariados';

    protected $guarded = ['id'];

    /** Los requisitos que trae la API, con el rótulo que se pinta en la ficha. */
    public const REQUISITOS = [
        'minimum_age' => 'Edad mínima',
        'certificate' => 'Certificado',
        'profession_or_study' => 'Profesión o estudios',
        'prior_experience' => 'Experiencia previa',
    ];

    /** Su sitio, a donde lleva el menú mientras la sección esté apagada. */
    public const SU_SITIO = 'https://voluntariadoschile.cl/oportunidades';

    /**
     * ¿Está encendida la sección? Configuración → General →
     * «Mostrar la sección de voluntariados» (13/10).
     *
     * Apagada, la sección existe y se puede abrir por su dirección, pero no se
     * enlaza desde ninguna parte del sitio —el menú lleva a su sitio— y lleva
     * noindex, para que no la encuentre nadie por Google. Encenderla es mover
     * el interruptor (y tener VCH_API_KEY en el .env para que haya contenido).
     */
    public static function seccionVisible(): bool
    {
        return (bool) Setting::get('voluntariados_visible', false);
    }

    protected function casts(): array
    {
        return [
            'areas_impacto' => 'array',
            'regiones_originales' => 'array',
            'region_ids' => 'array',
            'comunas_originales' => 'array',
            'requisitos' => 'array',
            'crudo' => 'array',
            'fecha_inicio' => 'date',
            'fecha_termino' => 'date',
            'actualizado_api_at' => 'datetime',
            'baja_at' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Lo que se enseña en el sitio: las que llegaron en la última
     * sincronización, sin las que ellos marcan cerradas y sin las que ya
     * terminaron aunque la API las siga listando (su documentación dice que
     * desaparecen, pero un rango vencido no invita a nadie).
     */
    public function scopePublicos(Builder $q): Builder
    {
        $hoy = now(Fecha::zona())->toDateString();

        return $q->where('activo', true)
            ->where(fn ($w) => $w->whereNull('estado_api')->orWhere('estado_api', 'not like', '%cerrad%'))
            ->where(fn ($w) => $w->whereNull('fecha_termino')->orWhereDate('fecha_termino', '>=', $hoy))
            ->where(fn ($w) => $w->whereNotNull('fecha_termino')->orWhereNull('fecha_inicio')->orWhereDate('fecha_inicio', '>=', $hoy));
    }

    public function scopePorRegion(Builder $q, ?int $region): Builder
    {
        return $region ? $q->whereJsonContains('region_ids', $region) : $q;
    }

    public function scopePorFormato(Builder $q, ?string $formato): Builder
    {
        return $formato ? $q->where('formato', $formato) : $q;
    }

    /** Como en las actividades: por título o por organización. */
    public function scopePorTexto(Builder $q, ?string $texto): Builder
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return $q;
        }

        $como = '%'.Filtro::like($texto).'%';

        return $q->where(fn ($w) => $w->where('titulo', 'like', $como)->orWhere('organizacion_nombre', 'like', $como));
    }

    /**
     * Primero las que tienen fecha, de la más cercana a la más lejana; las de
     * convocatoria permanente, al final. El mismo criterio que el listado de
     * actividades.
     */
    public function scopePorFecha(Builder $q): Builder
    {
        return $q->orderByRaw('CASE WHEN fecha_inicio IS NULL THEN 1 ELSE 0 END')
            ->orderBy('fecha_inicio')
            ->orderBy('titulo')
            ->orderBy('id');
    }

    // ── Lo que necesita `App\Support\CalendarioMes` (15/10) ────────
    // Los mismos cuatro que `Activity`, más `esPermanente()`. La API sólo trae
    // fechas, sin hora.

    /** Las que caen en alguna casilla: con día y sin ser permanentes. */
    public function scopeConDia(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('horario_tipo')->orWhere('horario_tipo', '<>', 'continuous'))
            ->whereNotNull('fecha_inicio');
    }

    /** Las de la franja de arriba: las mismas que `esPermanente()`. */
    public function scopeSinDia(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->where('horario_tipo', 'continuous')->orWhereNull('fecha_inicio'));
    }

    public function scopeParaElCalendario(Builder $q): Builder
    {
        return $q;
    }

    public function scopeOrdenDelDia(Builder $q): Builder
    {
        return $q->orderBy('fecha_inicio')->orderBy('titulo');
    }

    /** Su portada, o la imagen predeterminada de las actividades si no trae. */
    public function getImagenAttribute(): string
    {
        return $this->imagen_url ?: Activity::imagenPorDefecto();
    }

    /** Las regiones, con nuestro nombre cuando lo reconocemos y el suyo si no. */
    public function getRegionesAttribute(): array
    {
        $nuestras = Region::query()->whereIn('id', $this->region_ids ?? [])->pluck('nombre', 'id');

        return collect($this->regiones_originales ?? [])
            ->map(fn ($suya) => $nuestras->get(\App\Support\VoluntariadosChile\Equivalencias::regionId($suya)) ?? $suya)
            ->unique()->values()->all();
    }

    /** El sitio, corto, para la tarjeta: la comuna si hay una sola; si no, la región. */
    public function getLugarCortoAttribute(): string
    {
        $comunas = $this->comunas_originales ?? [];
        $regiones = $this->regiones;

        return match (true) {
            count($comunas) === 1 => $comunas[0],
            count($regiones) === 1 => $regiones[0],
            count($regiones) > 1 => 'Varias regiones',
            default => 'Por definir',
        };
    }

    /** El sitio entero, para la ficha. */
    public function getLugarAttribute(): string
    {
        return implode(' · ', array_filter([
            implode(', ', $this->comunas_originales ?? []),
            implode(', ', $this->regiones),
        ])) ?: 'Por definir';
    }

    public function esPermanente(): bool
    {
        return $this->horario_tipo === 'continuous' || $this->fecha_inicio === null;
    }

    /** Para la tarjeta. */
    public function getFechaCortaAttribute(): string
    {
        return $this->esPermanente()
            ? 'Convocatoria permanente'
            : RangoDeFechas::corto($this->fecha_inicio, $this->fecha_termino);
    }

    /** Para la ficha. */
    public function getFechaLargaAttribute(): string
    {
        return $this->esPermanente()
            ? 'Convocatoria permanente: puedes postular cuando quieras.'
            : RangoDeFechas::largo($this->fecha_inicio, $this->fecha_termino);
    }

    /** El formato nuestro si lo reconocemos; si no, el suyo tal cual. */
    public function getFormatoVisibleAttribute(): ?string
    {
        return $this->formato ?: $this->formato_original;
    }

    /**
     * Los requisitos que piden de verdad: rótulo y detalle.
     *
     * Sólo los que vienen con `required = true`. Su documentación no dice qué
     * significa un detalle en uno que no se exige, así que no se enseña: es
     * preferible callar a afirmar un requisito que no existe.
     *
     * @return array<int, array{rotulo: string, detalle: ?string}>
     */
    public function getRequisitosExigidosAttribute(): array
    {
        $lista = [];

        foreach (self::REQUISITOS as $clave => $rotulo) {
            $r = $this->requisitos[$clave] ?? null;

            if (is_array($r) && ($r['required'] ?? false) === true) {
                $detalle = trim((string) ($r['detail'] ?? ''));
                $lista[] = ['rotulo' => $rotulo, 'detalle' => $detalle !== '' ? $detalle : null];
            }
        }

        return $lista;
    }

    /** Dos letras para cuando no traen logo, como las organizaciones. */
    public function getInicialesAttribute(): string
    {
        return (new Organization(['nombre' => (string) $this->organizacion_nombre]))->iniciales;
    }
}
