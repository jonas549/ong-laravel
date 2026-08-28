<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Edition extends Model
{
    use HasFactory, SoftDeletes;

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
