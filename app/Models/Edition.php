<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Edition extends Model
{
    use HasFactory;

    protected $fillable = ['anio', 'titulo', 'descripcion', 'imagen', 'activo'];

    protected function casts(): array
    {
        return ['anio' => 'integer', 'activo' => 'boolean'];
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('anio');
    }

    public function getImagenUrlAttribute(): ?string
    {
        return $this->imagen ? asset($this->imagen) : null;
    }
}
