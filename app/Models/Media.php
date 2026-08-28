<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Un archivo de la biblioteca.
 *
 * Ver la migración para qué significan `ruta` y `origen`. Lo importante aquí:
 * **la ruta se guarda relativa a `public/`**, igual que ya la guardaban las
 * columnas de imagen de las demás tablas, así que `Media` y esas columnas
 * hablan el mismo idioma y el selector puede escribir en ellas sin traducir.
 */
class Media extends Model
{
    use HasFactory, SoftDeletes;

    /** Del repositorio (`public/img`), versionado en git. */
    public const ORIGEN_CODIGO = 'codigo';

    /** Subido desde el panel, vive en `storage/app/public`. */
    public const ORIGEN_SUBIDO = 'subido';

    /** Debajo de `public/`, dónde aterriza lo que se sube. */
    public const CARPETA_SUBIDAS = 'storage/medios';

    protected $table = 'media';

    protected $fillable = [
        'ruta', 'origen', 'nombre', 'titulo', 'alt',
        'mime', 'extension', 'peso', 'ancho', 'alto', 'carpeta', 'subido_por',
    ];

    protected function casts(): array
    {
        return [
            'peso' => 'integer',
            'ancho' => 'integer',
            'alto' => 'integer',
        ];
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    /* ── Consultas ──────────────────────────────────────── */

    public function scopeSubidos($query)
    {
        return $query->where('origen', self::ORIGEN_SUBIDO);
    }

    public function scopeDelCodigo($query)
    {
        return $query->where('origen', self::ORIGEN_CODIGO);
    }

    public function scopeOrdered($query)
    {
        return $query->latest('created_at')->orderByDesc('id');
    }

    /* ── Lo que la vista necesita ───────────────────────── */

    public function getUrlAttribute(): string
    {
        return asset($this->ruta);
    }

    /**
     * El nombre que se enseña. `titulo` manda si está puesto; si no, el nombre
     * del archivo. Nunca vacío: una tarjeta sin texto no se puede buscar ni
     * nombrar en una conversación.
     */
    public function getEtiquetaAttribute(): string
    {
        return $this->titulo ?: $this->nombre;
    }

    /** Del repositorio: ni se reemplaza ni se borra desde el panel. */
    public function getEsDelCodigoAttribute(): bool
    {
        return $this->origen === self::ORIGEN_CODIGO;
    }

    public function getEsImagenAttribute(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    /**
     * Un SVG no se redimensiona ni se convierte: es vectorial y ya pesa lo que
     * pesa. Se anota aquí para que el procesamiento no lo intente.
     */
    public function getEsVectorialAttribute(): bool
    {
        return $this->mime === 'image/svg+xml';
    }

    public function getPesoLegibleAttribute(): string
    {
        return self::pesoLegible($this->peso);
    }

    public function getDimensionesAttribute(): ?string
    {
        return $this->ancho && $this->alto ? "{$this->ancho} × {$this->alto}" : null;
    }

    /** ¿El archivo sigue en el disco, o quedó el registro huérfano? */
    public function getExisteAttribute(): bool
    {
        return is_file(public_path($this->ruta));
    }

    /* ── Utilidades ─────────────────────────────────────── */

    public static function pesoLegible(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $kb = $bytes / 1024;

        return $kb < 1024
            ? number_format($kb, 0, ',', '.').' KB'
            : number_format($kb / 1024, 1, ',', '.').' MB';
    }

    /**
     * Borra el archivo del disco. Sólo para lo subido: lo del repositorio lo
     * devuelve el siguiente `git pull`, así que borrarlo sería mentir.
     */
    public function borrarArchivo(): bool
    {
        if ($this->es_del_codigo) {
            return false;
        }

        $relativa = str_replace('storage/', '', $this->ruta);

        return Storage::disk('public')->delete($relativa);
    }
}
