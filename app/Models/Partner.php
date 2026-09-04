<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use HasFactory, SoftDeletes;

    /** Los cinco bloques de logos del home. */
    public const GRUPOS = [
        'auspician' => 'Auspician',
        'participan' => 'Participan',
        'colaboran' => 'Colaboran',
        'sponsor' => 'Marquesina',
        'participante' => 'Organizaciones participantes',
    ];

    /**
     * El alto con que se pinta cada logo en su fila.
     *
     * Sólo se usa en los tres grupos de logos (auspician, participan y
     * colaboran). En la marquesina todas las pastillas van del mismo alto, así
     * que las filas de ese grupo se quedan en `normal` y no se tocan.
     */
    public const TAMANOS = [
        'grande' => 'Grande',
        'mediano' => 'Mediano',
        'chico' => 'Pequeño',
        'normal' => 'Sin tamaño (marquesina)',
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

    /** El tamaño con el que se pinta: lo desconocido cae en el más discreto. */
    public function getTamanoClaseAttribute(): string
    {
        return in_array($this->tamano, ['grande', 'mediano', 'chico'], true)
            ? $this->tamano
            : 'chico';
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset($this->logo_path) : null;
    }
}
