<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'slug', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /**
     * Las que se ofrecen al publicar.
     *
     * Una region apagada deja de salir en los selectores del wizard, pero no se
     * borra: hay actividades apuntando a ella y borrarla las dejaria sin
     * ubicacion. Por eso aqui se apaga y no se elimina.
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class)->orderBy('nombre');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('orden');
    }
}
