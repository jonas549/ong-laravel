<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParticipationCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo', 'descripcion', 'nota', 'cta', 'href', 'color',
        'icono', 'mask_path', 'art_path', 'orden', 'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('orden');
    }
}
