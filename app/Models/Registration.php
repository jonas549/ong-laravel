<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Registration extends Model
{
    use HasFactory;

    public const ESTADOS = ['pendiente', 'confirmado', 'cancelado'];

    protected $fillable = [
        'activity_id', 'nombre', 'correo', 'telefono',
        'es_mayor_edad', 'estado', 'token', 'confirmed_at', 'recordatorio_encolado_at',
    ];

    protected function casts(): array
    {
        return [
            'es_mayor_edad' => 'boolean',
            'confirmed_at' => 'datetime',
            'recordatorio_encolado_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $r) {
            if (blank($r->token)) {
                $r->token = Str::random(48);
            }
        });
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function scopeActivas($query)
    {
        return $query->where('estado', '!=', 'cancelado');
    }

    /**
     * OJO: en producción esto devuelve SIEMPRE una lista vacía. Nada en la
     * aplicación pone `confirmado` —sólo el seeder de datos de demostración—
     * porque la confirmación por correo nunca se construyó y se decidió el
     * 18/09 no construirla. Se conserva el ámbito por si algún día se retoma;
     * no lo uses para contar nada.
     */
    public function scopeConfirmadas($query)
    {
        return $query->where('estado', 'confirmado');
    }

    public function getEstadoLabelAttribute(): string
    {
        return ucfirst($this->estado);
    }

    /**
     * El estado tal como se le enseña al ORGANIZADOR, o null si no hay nada
     * que decir (decisión de Jonas del 18/09, punto 23 de la tanda del 11/09).
     *
     * El cliente reportó que ver «Pendiente» junto a cada inscrito confundía,
     * y tenía razón por un motivo peor del que suponía: **nada en la
     * aplicación pasa nunca una inscripción a `confirmado`**. El único código
     * que escribe ese valor es el seeder de datos de demostración; en
     * producción toda inscripción nace `pendiente` y ahí se queda, porque la
     * confirmación por correo que ese estado suponía no se llegó a construir
     * y se ha decidido no construirla.
     *
     * Así que «Pendiente» no informaba de nada y encima se leía como «esta
     * persona no ha confirmado que viene». Lo único que sí distingue algo es
     * quién se dio de baja, y eso se sigue enseñando.
     *
     * La columna NO se toca en base de datos: `cancelado` la sigue usando.
     */
    public function getEstadoVisibleAttribute(): ?string
    {
        return $this->estado === 'cancelado' ? 'Cancelada' : null;
    }

    /** Colores de la tabla de inscritos del prototipo. */
    public function getEstadoColorAttribute(): array
    {
        return $this->estado === 'confirmado'
            ? ['bg' => '#eaf6f5', 'ink' => '#0d6b64']
            : ['bg' => '#fff8e6', 'ink' => '#8a6a00'];
    }
}
