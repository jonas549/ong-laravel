<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory;

    protected $fillable = ['titulo', 'slug', 'contenido', 'meta_descripcion', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $p) {
            if (blank($p->slug)) {
                $p->slug = Str::slug($p->titulo);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
