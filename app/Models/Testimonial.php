<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use HasFactory;

    protected $fillable = ['texto', 'autor', 'cargo', 'logo_path', 'color', 'bleed', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['bleed' => 'boolean', 'activo' => 'boolean'];
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('orden');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset($this->logo_path) : null;
    }
}
