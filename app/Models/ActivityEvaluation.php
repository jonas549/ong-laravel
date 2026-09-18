<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una respuesta a la encuesta de evaluación de una actividad.
 *
 * Sin borrado en blando a propósito: una evaluación no se edita ni se archiva,
 * y una papelera de opiniones ajenas es justo lo que no queremos poder hacer.
 * Si hay que quitar una —un insulto, una prueba— se quita de verdad.
 */
class ActivityEvaluation extends Model
{
    use HasFactory;

    /**
     * De dónde salió el asistente. Cinco valores fijos del encargo.
     *
     * La clave es lo que se guarda y el valor lo que se lee: así renombrar la
     * etiqueta no invalida lo ya recogido ni rompe una exportación anterior.
     */
    public const ORIGENES = [
        'redes' => 'Redes sociales',
        'organizacion' => 'La organización',
        'conocido' => 'Un amigo o familiar',
        'sitio' => 'Sitio web',
        'otro' => 'Otro',
    ];

    /** Los extremos de cada escala, tal como los pide el wireframe. */
    public const ESCALAS = [
        'experiencia' => [
            'pregunta' => '¿Cómo evaluarías tu experiencia en esta actividad?',
            'min' => 'Muy mala',
            'max' => 'Excelente',
        ],
        'motivacion' => [
            'pregunta' => '¿Qué tan dispuesto(a) estarías a participar en futuras actividades del Día del Patrimonio Social que promuevan la solidaridad y los vínculos comunitarios?',
            'min' => 'Nada dispuesto(a)',
            'max' => 'Muy dispuesto(a)',
        ],
    ];

    /** Lo que cabe en la respuesta abierta. El mismo número en la regla y en el contador. */
    public const MAX_SIGNIFICADO = 300;

    protected $fillable = [
        'activity_id', 'nombre', 'correo',
        'experiencia', 'significado', 'motivacion',
        'como_se_entero', 'foto_path', 'foto_autorizada', 'ip_hash',
    ];

    protected function casts(): array
    {
        return [
            'experiencia' => 'integer',
            'motivacion' => 'integer',
            'foto_autorizada' => 'boolean',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /* ── Lectura ─────────────────────────────────────────── */

    public function getOrigenLabelAttribute(): string
    {
        return self::ORIGENES[$this->como_se_entero] ?? '';
    }

    public function tieneFoto(): bool
    {
        return filled($this->foto_path);
    }

    /* ── Consultas ───────────────────────────────────────── */

    public function scopeConFoto(Builder $q): Builder
    {
        return $q->whereNotNull('foto_path');
    }

    /**
     * Las fotos que se pueden usar para difusión.
     *
     * `foto_autorizada` a secas no basta: la casilla puede quedar marcada de un
     * intento anterior del navegador y sin foto no autoriza nada.
     */
    public function scopeAutorizadas(Builder $q): Builder
    {
        return $q->whereNotNull('foto_path')->where('foto_autorizada', true);
    }

    public function scopeSinAutorizar(Builder $q): Builder
    {
        return $q->whereNotNull('foto_path')->where('foto_autorizada', false);
    }
}
