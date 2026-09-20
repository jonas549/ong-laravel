<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityStatusLog extends Model
{
    use HasFactory;

    protected $fillable = ['activity_id', 'user_id', 'de_estado', 'a_estado', 'comentario'];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Una entrada del historial es un mensaje del hilo cuando trae texto.
     *
     * El resto —«borrador → revisión» sin comentario— es movimiento, no
     * conversación, y en el hilo se pinta distinto.
     */
    public function esMensaje(): bool
    {
        return filled($this->comentario);
    }

    /**
     * De qué lado del hilo viene: la ONG, la organización, o el propio sistema.
     *
     * Se decide por el rol de quien lo escribió y no por el estado al que se
     * movió la actividad, porque los dos lados pueden mover la actividad al
     * mismo estado: una vuelta de ajustes la manda a «revisión» el organizador,
     * y una actividad recién enviada la manda ahí el sistema.
     *
     * Sin autor es el sistema: la aprobación automática y las siembras pasan
     * por aquí con `user_id` nulo.
     */
    public function lado(): string
    {
        if (! $this->user) {
            return 'sistema';
        }

        return $this->user->esAdmin() ? 'ong' : 'organizacion';
    }

    /** Cómo se firma en el hilo. */
    public function firma(): string
    {
        return match ($this->lado()) {
            'ong' => $this->user->name.' · Comunidad de Organizaciones Solidarias',
            'organizacion' => $this->user->name.' · '.($this->activity?->organization?->nombre ?? 'la organización'),
            default => 'El sistema',
        };
    }
}
