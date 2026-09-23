<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Setting;

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

    /**
     * Las otras dos preguntas de la encuesta, con el mismo texto que ve quien
     * la responde. Punto 8 del 23/09: las respuestas se enseñan junto a su
     * pregunta, y el texto vive aquí una sola vez para que el formulario y
     * los paneles no puedan decir cosas distintas.
     */
    public const PREGUNTA_SIGNIFICADO = 'Después de participar, ¿qué significa para ti el Patrimonio Social?';

    public const PREGUNTA_ORIGEN = '¿Cómo te enteraste de esta actividad?';

    /**
     * Cada respuesta con la pregunta que la originó, en el orden del
     * formulario. Lo que no se contestó (las dos últimas son opcionales en la
     * práctica) no sale.
     *
     * @return list<array{clave: string, pregunta: string, respuesta: string}>
     */
    public function respuestas(): array
    {
        $escala = fn (string $clave) => $this->{$clave} === null ? '' : sprintf(
            '%d de 5 (1 = %s, 5 = %s)',
            $this->{$clave}, self::ESCALAS[$clave]['min'], self::ESCALAS[$clave]['max'],
        );

        return array_values(array_filter([
            ['clave' => 'experiencia', 'pregunta' => self::ESCALAS['experiencia']['pregunta'], 'respuesta' => $escala('experiencia')],
            ['clave' => 'motivacion', 'pregunta' => self::ESCALAS['motivacion']['pregunta'], 'respuesta' => $escala('motivacion')],
            ['clave' => 'significado', 'pregunta' => self::PREGUNTA_SIGNIFICADO, 'respuesta' => (string) $this->significado],
            ['clave' => 'como_se_entero', 'pregunta' => self::PREGUNTA_ORIGEN, 'respuesta' => $this->origen_label],
        ], fn ($r) => trim($r['respuesta']) !== ''));
    }

    /** Lo que cabe en la respuesta abierta. El mismo número en la regla y en el contador. */
    public const MAX_SIGNIFICADO = 300;

    /** Cuántas fotos admite una respuesta si la ONG no ha dicho otra cosa. */
    public const MAX_FOTOS_POR_DEFECTO = 3;

    /**
     * El tope de fotografías por respuesta, el que haya puesto la ONG.
     *
     * Vive aquí y no repartido por el formulario, la regla de validación y el
     * guardado, que son los tres sitios que lo necesitan. Se acota entre 0 y
     * 10: un 0 apaga el campo, y por arriba hay que parar en algún sitio
     * porque cada foto es una subida más en la misma petición y el límite de
     * `post_max_size` no lo pone la ONG.
     */
    public static function maximoFotos(): int
    {
        $valor = (int) Setting::get('evaluacion_max_fotos', self::MAX_FOTOS_POR_DEFECTO);

        return max(0, min(10, $valor));
    }

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

    /**
     * Las fotografías de esta respuesta.
     *
     * Desde el 2026-09-20 son varias y no una: el máximo lo pone la ONG en
     * Configuración → General. La autorización sigue siendo una sola, la de
     * `foto_autorizada`, porque el consentimiento se firma una vez sobre el
     * envío entero.
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(EvaluationPhoto::class, 'activity_evaluation_id')->orderBy('orden')->orderBy('id');
    }

    /* ── Lectura ─────────────────────────────────────────── */

    public function getOrigenLabelAttribute(): string
    {
        return self::ORIGENES[$this->como_se_entero] ?? '';
    }

    public function tieneFoto(): bool
    {
        return $this->fotos()->exists();
    }

    /** Cuántas trae. Se usa en el listado, donde una sola cifra basta. */
    public function cuantasFotos(): int
    {
        return $this->fotos->count();
    }

    /* ── Consultas ───────────────────────────────────────── */

    public function scopeConFoto(Builder $q): Builder
    {
        return $q->whereHas('fotos');
    }

    /**
     * Las fotos que se pueden usar para difusión.
     *
     * `foto_autorizada` a secas no basta: la casilla puede quedar marcada de un
     * intento anterior del navegador y sin foto no autoriza nada.
     */
    public function scopeAutorizadas(Builder $q): Builder
    {
        return $q->whereHas('fotos')->where('foto_autorizada', true);
    }

    public function scopeSinAutorizar(Builder $q): Builder
    {
        return $q->whereHas('fotos')->where('foto_autorizada', false);
    }
}
