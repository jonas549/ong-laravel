<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaxonomyTerm extends Model
{
    use HasFactory;

    /** Los cuatro grupos de selección múltiple del formulario de actividad. */
    public const GRUPOS = [
        'tema' => 'Temas',
        'caracteristica' => 'Características',
        'publico' => 'Público',
        'acceso' => 'Accesibilidad',
    ];

    /** Cuántos términos admite cada grupo. null = sin tope. */
    public const LIMITES = [
        'tema' => 3,
        'caracteristica' => 5,
        'publico' => null,
        'acceso' => null,
    ];

    protected $fillable = ['grupo', 'nombre', 'slug', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class);
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

    public static function limiteDe(string $grupo): ?int
    {
        return self::LIMITES[$grupo] ?? null;
    }
}
