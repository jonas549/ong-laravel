<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasFactory;

    /**
     * Los cuatro estados posibles de un correo.
     *
     * `NO_ENTREGADO` es el que faltaba: el mailer terminó sin error pero el
     * transporte era `log` o `array`, así que el correo se escribió en un
     * archivo o se quedó en memoria. Antes eso se registraba como enviado y el
     * panel lo daba por bueno.
     *
     * `EN_COLA` es el otro: la fila sólo nacía cuando el worker enviaba, así
     * que sin worker el correo no dejaba rastro en ninguna pantalla.
     */
    public const EN_COLA = 'en_cola';
    public const ENVIADO = 'sent';
    public const FALLIDO = 'failed';
    public const NO_ENTREGADO = 'no_entregado';

    protected $fillable = [
        'mensaje_uuid', 'to', 'cc', 'bcc', 'subject', 'body_html', 'mailable', 'plantilla', 'adjuntos',
        'status', 'transporte', 'error', 'sent_at', 'encolado_at', 'reenviado_at', 'attempts',
        'related_type', 'related_id',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'encolado_at' => 'datetime',
            'reenviado_at' => 'datetime',
            'attempts' => 'integer',
            'adjuntos' => 'array',
        ];
    }

    public function related()
    {
        return $this->morphTo();
    }

    /** Todo lo que no llegó a un servidor de correo, por el motivo que sea. */
    public function scopeFallidos($query)
    {
        return $query->whereIn('status', [self::FALLIDO, self::NO_ENTREGADO]);
    }

    public function scopeEnviados($query)
    {
        return $query->where('status', self::ENVIADO);
    }

    public function scopeEnCola($query)
    {
        return $query->where('status', self::EN_COLA);
    }

    /** Sólo esto cuenta como "salió hacia un servidor de correo de verdad". */
    public function getEntregadoAttribute(): bool
    {
        return $this->status === self::ENVIADO;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::ENVIADO => 'Enviado',
            self::EN_COLA => 'En cola',
            self::NO_ENTREGADO => 'No salió',
            default => 'Falló',
        };
    }

    /** Para pintar el estado sin repetir el mismo `match` en cada vista. */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::ENVIADO => '#0d6b64',
            self::EN_COLA => '#8a6d1f',
            default => '#a8324a',
        };
    }

    public function getStatusFondoAttribute(): string
    {
        return match ($this->status) {
            self::ENVIADO => '#eaf6f5',
            self::EN_COLA => '#fdf6e3',
            default => '#fdecef',
        };
    }

    /**
     * Por qué una fila no cuenta como entregada. El panel la pinta tal cual:
     * sin esto, "No salió" no le dice nada a nadie.
     */
    public function getMotivoAttribute(): ?string
    {
        return match ($this->status) {
            self::NO_ENTREGADO => 'El mailer activo era «'.($this->transporte ?: 'desconocido').'», que no entrega '
                .'a nadie: el correo se escribió en el servidor en vez de enviarse. Revisa Configuración → SMTP.',
            self::EN_COLA => 'Encolado, esperando a que el worker lo procese. Si lleva mucho rato aquí, '
                .'el cron del servidor no está corriendo.',
            self::FALLIDO => $this->error,
            default => null,
        };
    }
}
