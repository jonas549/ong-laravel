<?php

namespace App\Services;

use App\Models\AccessLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bloqueo por intentos fallidos y registro de accesos.
 *
 * Vive en un servicio y no en cada controlador porque los dos paneles tienen
 * que comportarse igual: si el bloqueo fuera distinto en uno y otro, bastaría
 * con probar por la puerta más floja.
 */
class ControlDeAcceso
{
    /** Valores de partida, para cuando el ajuste no esté en la base. */
    private const INTENTOS = 5;
    private const MINUTOS_BLOQUEO = 15;

    /**
     * Intentos permitidos antes de bloquear.
     *
     * Se lee de Configuración porque cuál es el número correcto es una decisión
     * de la ONG, no del código: 5 frena el rastreo de contraseñas sin castigar
     * a quien simplemente no se acuerda de la suya, pero se puede bajar. El
     * suelo de 1 evita que un 0 guardado por error deje a todos fuera.
     */
    private function intentos(): int
    {
        return max(1, (int) Setting::get('acceso_intentos', self::INTENTOS));
    }

    /** Cuánto dura el bloqueo, en segundos. */
    private function bloqueo(): int
    {
        return max(60, (int) Setting::get('acceso_bloqueo_minutos', self::MINUTOS_BLOQUEO) * 60);
    }

    /**
     * ¿Está bloqueado este intento? Si lo está, lanza la excepción de
     * validación con el tiempo que falta.
     */
    public function comprobarBloqueo(Request $request, string $panel): void
    {
        $clave = $this->clave($request, $panel);

        if (! RateLimiter::tooManyAttempts($clave, $this->intentos())) {
            return;
        }

        $segundos = RateLimiter::availableIn($clave);

        $this->registrar($request, $panel, 'bloqueado', $request->input('email'));

        throw ValidationException::withMessages([
            'email' => 'Demasiados intentos fallidos. Vuelve a probar en '.$this->enPalabras($segundos).'.',
        ]);
    }

    /** Un intento fallido: cuenta para el bloqueo y queda registrado. */
    public function fallo(Request $request, string $panel, string $motivo, ?User $usuario = null): void
    {
        RateLimiter::hit($this->clave($request, $panel), $this->bloqueo());

        $this->registrar($request, $panel, $motivo, $request->input('email'), $usuario);
    }

    /** Entrada correcta: se limpia el contador y se registra. */
    public function exito(Request $request, string $panel, User $usuario): void
    {
        RateLimiter::clear($this->clave($request, $panel));

        $this->registrar($request, $panel, 'exito', $usuario->email, $usuario);
    }

    /** Segundos que le quedan a un bloqueo, o 0 si no hay bloqueo. */
    public function segundosRestantes(string $panel, string $email, ?string $ip): int
    {
        $clave = $this->claveDe($panel, $email, $ip);

        return RateLimiter::tooManyAttempts($clave, $this->intentos())
            ? RateLimiter::availableIn($clave)
            : 0;
    }

    /** Levanta el bloqueo a mano, desde el panel. */
    public function liberar(string $panel, string $email, ?string $ip): void
    {
        RateLimiter::clear($this->claveDe($panel, $email, $ip));
    }

    /**
     * La clave combina correo, IP y panel: así un atacante desde una IP no
     * puede bloquear la cuenta de otra persona probando su correo desde otro
     * sitio, y a la vez se frena el rastreo de contraseñas desde una misma IP.
     */
    private function clave(Request $request, string $panel): string
    {
        return $this->claveDe($panel, (string) $request->input('email'), $request->ip());
    }

    private function claveDe(string $panel, string $email, ?string $ip): string
    {
        return 'acceso|'.$panel.'|'.Str::lower($email).'|'.$ip;
    }

    private function registrar(Request $request, string $panel, string $resultado, ?string $email, ?User $usuario = null): void
    {
        AccessLog::create([
            'user_id' => $usuario?->id,
            'email' => $email ? Str::limit($email, 255, '') : null,
            'panel' => $panel,
            'resultado' => $resultado,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);
    }

    private function enPalabras(int $segundos): string
    {
        if ($segundos < 60) {
            return $segundos.' segundos';
        }

        $minutos = (int) ceil($segundos / 60);

        return $minutos === 1 ? 'un minuto' : "{$minutos} minutos";
    }
}
