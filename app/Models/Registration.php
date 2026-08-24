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
        'es_mayor_edad', 'estado', 'token', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'es_mayor_edad' => 'boolean',
            'confirmed_at' => 'datetime',
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

    public function scopeConfirmadas($query)
    {
        return $query->where('estado', 'confirmado');
    }

    public function getEstadoLabelAttribute(): string
    {
        return ucfirst($this->estado);
    }

    /** Colores de la tabla de inscritos del prototipo. */
    public function getEstadoColorAttribute(): array
    {
        return $this->estado === 'confirmado'
            ? ['bg' => '#eaf6f5', 'ink' => '#0d6b64']
            : ['bg' => '#fff8e6', 'ink' => '#8a6a00'];
    }
}
