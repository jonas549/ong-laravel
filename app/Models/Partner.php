<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    use HasFactory;

    /** Los cinco bloques de logos del home. */
    public const GRUPOS = [
        'auspician' => 'Auspician',
        'participan' => 'Participan',
        'colaboran' => 'Colaboran',
        'sponsor' => 'Marquesina',
        'participante' => 'Organizaciones participantes',
    ];

    protected $fillable = [
        'nombre', 'logo_path', 'url', 'grupo', 'tamano', 'color', 'orden', 'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function scopeGrupo($query, string $grupo)
    {
        return $query->where('grupo', $grupo);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset($this->logo_path) : null;
    }
}
