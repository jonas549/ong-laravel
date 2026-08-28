<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commune extends Model
{
    use HasFactory;

    protected $fillable = ['region_id', 'nombre', 'slug', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /**
     * Las que se ofrecen al publicar.
     *
     * Igual que las regiones: una comuna que ya no aplica se apaga, no se
     * borra. Hay actividades apuntando a ella, y borrarla las dejaria sin
     * ubicacion sin forma de arreglarlo desde el panel.
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
