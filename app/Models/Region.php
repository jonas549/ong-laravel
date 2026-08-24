<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'slug', 'orden'];

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
