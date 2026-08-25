<?php

namespace App\Models;

use App\Support\Dispositivo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends Model
{
    use HasFactory;

    public const PANEL_ADMIN = 'admin';

    public const PANEL_ORGANIZADOR = 'organizador';

    /** Por qué terminó así el intento. */
    public const RESULTADOS = [
        'exito' => 'Entró',
        'credenciales' => 'Credenciales incorrectas',
        'rol' => 'Cuenta de otro tipo',
        'bloqueado' => 'Bloqueado por intentos',
        'inactiva' => 'Cuenta desactivada',
    ];

    protected $fillable = ['user_id', 'email', 'panel', 'resultado', 'ip', 'user_agent'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeFallidos($query)
    {
        return $query->where('resultado', '!=', 'exito');
    }

    public function getResultadoLabelAttribute(): string
    {
        return self::RESULTADOS[$this->resultado] ?? $this->resultado;
    }

    public function getExitosoAttribute(): bool
    {
        return $this->resultado === 'exito';
    }

    /** Navegador y sistema, en corto. */
    public function getDispositivoAttribute(): string
    {
        return Dispositivo::describir($this->user_agent);
    }
}
