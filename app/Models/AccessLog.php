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
        'desbloqueo' => 'Bloqueo levantado a mano',
        'clave_admin' => 'Contraseña cambiada por un administrador',
    ];

    /** Lo que no es entrar bien. Para el resumen y el filtro del panel. */
    public const FALLOS = ['credenciales', 'rol', 'bloqueado', 'inactiva'];

    /**
     * Los que cuentan para bloquear: intentos de contraseña de verdad.
     *
     * `bloqueado` queda fuera a propósito. Si contara, cada intento contra una
     * cuenta ya bloqueada alargaría el bloqueo, y a quien esté probando
     * contraseñas le bastaría con seguir dándole para dejar al dueño fuera
     * indefinidamente.
     *
     * **`rol` también quedó fuera, el 2026-09-02.** Un `rol` sólo se registra
     * cuando `User::porCredenciales` YA ha dado por buena la contraseña: no es
     * un intento de adivinarla, es alguien que sabe la suya y ha llamado a la
     * puerta equivocada. Contarlo no quita ni una posibilidad a quien esté
     * probando contraseñas —el que ya la sabe entraría por la puerta buena— y
     * en cambio dejaba fuera durante quince minutos justo a la persona que se
     * equivoca de acceso, que es de quien viene el problema: el bloqueo salta
     * ANTES de comprobar nada, así que a la sexta vez ya no le salía el aviso
     * con el botón que la lleva a su sitio. Lo enseñó una captura de las
     * pruebas de este mismo arreglo.
     *
     * La fila se sigue guardando: sale en el registro de accesos y en su
     * filtro. Lo único que cambia es que no suma para bloquear.
     */
    public const FALLOS_QUE_CUENTAN = ['credenciales', 'inactiva'];

    /** Los que ponen el contador a cero: entrar bien, o que un admin lo levante. */
    public const REINICIOS = ['exito', 'desbloqueo'];

    protected $fillable = ['user_id', 'actor_id', 'email', 'panel', 'resultado', 'ip', 'user_agent'];

    /** De quién es la cuenta afectada. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Quién lo hizo, cuando no fue el titular: el administrador que levantó un
     * bloqueo o cambió una contraseña ajena. Nulo en los intentos de entrar,
     * donde titular y autor son la misma persona.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Los intentos que no salieron bien.
     *
     * Enumerados en vez de "todo lo que no sea exito": desde que hay
     * resultados que no son intentos de entrar —levantar un bloqueo, cambiar
     * una contraseña desde el panel— ese `!=` los contaba como fallos.
     */
    public function scopeFallidos($query)
    {
        return $query->whereIn('resultado', self::FALLOS);
    }

    public function getResultadoLabelAttribute(): string
    {
        return self::RESULTADOS[$this->resultado] ?? $this->resultado;
    }

    public function getExitosoAttribute(): bool
    {
        return $this->resultado === 'exito';
    }

    /**
     * Tres colores, no dos.
     *
     * Verde para entrar, rojo para los fallos, y ámbar para las acciones que un
     * administrador hace sobre una cuenta ajena: no son un fallo, pero tampoco
     * son rutina, y son justamente las que hay que poder localizar de un
     * vistazo cuando se revisa el registro.
     */
    public function getColorFondoAttribute(): string
    {
        return match (true) {
            $this->exitoso => '#eaf6f5',
            in_array($this->resultado, self::FALLOS, true) => '#fdeaf0',
            default => '#fdf6e3',
        };
    }

    public function getColorTextoAttribute(): string
    {
        return match (true) {
            $this->exitoso => '#0d6b64',
            in_array($this->resultado, self::FALLOS, true) => '#a82249',
            default => '#8a6d1f',
        };
    }

    /** Navegador y sistema, en corto. */
    public function getDispositivoAttribute(): string
    {
        return Dispositivo::describir($this->user_agent);
    }
}
