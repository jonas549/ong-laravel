<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;

    protected $fillable = ['titulo', 'slug', 'extracto', 'contenido', 'imagen', 'published_at', 'activo'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'activo' => 'boolean'];
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

    public function scopePublished($query)
    {
        return $query->where('activo', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function getFechaAttribute(): string
    {
        return $this->published_at?->locale('es')->isoFormat('D MMM YYYY') ?? '';
    }

    public function getImagenUrlAttribute(): ?string
    {
        return $this->imagen ? asset($this->imagen) : null;
    }
}
