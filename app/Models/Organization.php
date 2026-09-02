<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    /** Los cinco tipos del paso 2 del wizard. */
    public const TIPOS = [
        'Organización sin fines de lucro',
        'Empresa o institución privada',
        'Institución educativa',
        'Municipalidad u organismo público',
        'Otra',
    ];

    protected $fillable = [
        'user_id', 'nombre', 'slug', 'tipo', 'tipo_otro', 'descripcion', 'logo_path',
        'num_voluntarios', 'unidad_educativa', 'correo_contacto', 'enlace_web',
        'enlace_red_social', 'verificada', 'activo', 'requiere_revision',
    ];

    protected function casts(): array
    {
        return [
            'verificada' => 'boolean',
            'activo' => 'boolean',
            'requiere_revision' => 'boolean',
            'num_voluntarios' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $org) {
            if (blank($org->slug)) {
                $org->slug = static::slugUnico($org->nombre);
            }
        });
    }

    public static function slugUnico(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'organizacion';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * Las que se ven. Una organizacion apagada deja de salir en los listados
     * publicos, pero no se lleva por delante sus actividades ni las
     * inscripciones de esas actividades: por eso se apaga en vez de borrarse.
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Las inscripciones de todas sus actividades.
     *
     * Existe para poder decir en pantalla que se llevaria por delante un
     * borrado: «7 actividades y 24 inscripciones» se entiende, y «no se puede
     * eliminar» a secas, no.
     */
    public function registrations(): HasManyThrough
    {
        return $this->hasManyThrough(Registration::class, Activity::class);
    }

    /** El nombre del tipo, resolviendo el campo libre cuando es "Otra". */
    public function getTipoLabelAttribute(): string
    {
        return $this->tipo === 'Otra' && filled($this->tipo_otro)
            ? $this->tipo_otro
            : (string) $this->tipo;
    }

    public function esEmpresa(): bool
    {
        return $this->tipo === 'Empresa o institución privada';
    }

    public function esEducativa(): bool
    {
        return $this->tipo === 'Institución educativa';
    }
}
