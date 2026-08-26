<?php

namespace App\Services;

use App\Models\AccessLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bloqueo por intentos fallidos y registro de accesos.
 *
 * Vive en un servicio y no en cada controlador porque los dos paneles tienen
 * que comportarse igual: si el bloqueo fuera distinto en uno y otro, bastaría
 * con probar por la puerta más floja.
 *
 * **El contador se lleva en `access_logs`, no en la caché.** Antes se apoyaba
 * en `RateLimiter`, que guarda el contador en el almacén de caché, y en
 * producción el cron de despliegue toca la caché cada cinco minutos: el
 * contador podía vaciarse solo y el bloqueo no llegaba a saltar, mientras en
 * local funcionaba siempre porque ahí nadie limpia nada. La tabla ya registraba
 * cada intento y no la borra nadie, así que es el sitio correcto. De paso
 * desaparece una incoherencia: la lista de sospechosos del panel salía de la
 * tabla y el bloqueo de la caché, y podían decir cosas distintas de la misma
 * cuenta.
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
    public function intentos(): int
    {
        return max(1, (int) Setting::get('acceso_intentos', self::INTENTOS));
    }

    /** Cuánto dura el bloqueo, en segundos. */
    public function bloqueo(): int
    {
        return max(60, (int) Setting::get('acceso_bloqueo_minutos', self::MINUTOS_BLOQUEO) * 60);
    }

    /**
     * ¿Está bloqueado este intento? Si lo está, lanza la excepción de
     * validación con el tiempo que falta.
     */
    public function comprobarBloqueo(Request $request, string $panel): void
    {
        $segundos = $this->segundosRestantes($panel, (string) $request->input('email'), $request->ip());

        if ($segundos <= 0) {
            return;
        }

        $this->registrar($request, $panel, 'bloqueado', $request->input('email'));

        throw ValidationException::withMessages([
            'email' => 'Demasiados intentos fallidos. Vuelve a probar en '.$this->enPalabras($segundos).'.',
        ]);
    }

    /** Un intento fallido: cuenta para el bloqueo y queda registrado. */
    public function fallo(Request $request, string $panel, string $motivo, ?User $usuario = null): void
    {
        // El registro ES el contador: no hay nada más que incrementar.
        $this->registrar($request, $panel, $motivo, $request->input('email'), $usuario);
    }

    /** Entrada correcta: la fila de éxito pone el contador a cero. */
    public function exito(Request $request, string $panel, User $usuario): void
    {
        $this->registrar($request, $panel, 'exito', $usuario->email, $usuario);
    }

    /**
     * Segundos que le quedan a un bloqueo, o 0 si no hay bloqueo.
     *
     * Se cuentan los fallos posteriores al último reinicio —entrar bien, o que
     * un admin levante el bloqueo— y dentro de la ventana. El bloqueo caduca a
     * partir del último fallo, no del primero: así insistir mantiene la puerta
     * cerrada, pero dejar de insistir la abre sola.
     */
    public function segundosRestantes(string $panel, string $email, ?string $ip): int
    {
        if ($email === '') {
            return 0;
        }

        $duracion = $this->bloqueo();
        $desde = $this->ultimoReinicio($panel, $email, $ip);
        $ventana = now()->subSeconds($duracion);

        $fallos = $this->consulta($panel, $email, $ip)
            ->whereIn('resultado', AccessLog::FALLOS_QUE_CUENTAN)
            ->where('created_at', '>=', $desde && $desde->greaterThan($ventana) ? $desde : $ventana)
            ->orderByDesc('created_at')
            ->limit($this->intentos())
            ->pluck('created_at');

        if ($fallos->count() < $this->intentos()) {
            return 0;
        }

        $expira = Carbon::parse($fallos->first())->addSeconds($duracion);

        return max(0, (int) ceil(now()->diffInSeconds($expira, false)));
    }

    /**
     * Levanta el bloqueo a mano, desde el panel.
     *
     * No se borra nada: se añade una fila que marca el punto a partir del cual
     * se vuelve a contar. Borrar los intentos perdería justo el rastro que hace
     * falta para saber que alguien estuvo probando contraseñas.
     */
    public function liberar(string $panel, string $email, ?string $ip, ?User $admin = null): void
    {
        AccessLog::create([
            // `user_id` es de quién es la cuenta afectada; `actor_id`, quién lo
            // hizo. Antes se guardaba el admin en `user_id` y el registro decía
            // que el bloqueo era suyo.
            'user_id' => User::where('email', Str::lower($email))->value('id'),
            'actor_id' => $admin?->id,
            'email' => Str::lower(Str::limit($email, 255, '')),
            'panel' => $panel,
            'resultado' => 'desbloqueo',
            'ip' => $ip,
            'user_agent' => null,
        ]);
    }

    /**
     * Deja constancia de una acción que un administrador hace sobre la cuenta
     * de otra persona. No cuenta para el bloqueo: no es un intento de entrar.
     */
    public function registrarAccionDeAdmin(
        Request $request,
        string $resultado,
        User $afectado,
        User $admin,
    ): void {
        AccessLog::create([
            'user_id' => $afectado->id,
            'actor_id' => $admin->id,
            'email' => Str::lower($afectado->email),
            'panel' => $afectado->esAdmin() ? AccessLog::PANEL_ADMIN : AccessLog::PANEL_ORGANIZADOR,
            'resultado' => $resultado,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);
    }

    /** Cuántos fallos lleva acumulados ahora mismo. Para el panel. */
    public function fallosAcumulados(string $panel, string $email, ?string $ip): int
    {
        $desde = $this->ultimoReinicio($panel, $email, $ip);
        $ventana = now()->subSeconds($this->bloqueo());

        return $this->consulta($panel, $email, $ip)
            ->whereIn('resultado', AccessLog::FALLOS_QUE_CUENTAN)
            ->where('created_at', '>=', $desde && $desde->greaterThan($ventana) ? $desde : $ventana)
            ->count();
    }

    /** Cuándo se puso el contador a cero por última vez, si es que pasó. */
    private function ultimoReinicio(string $panel, string $email, ?string $ip): ?Carbon
    {
        $fecha = $this->consulta($panel, $email, $ip)
            ->whereIn('resultado', AccessLog::REINICIOS)
            ->max('created_at');

        return $fecha ? Carbon::parse($fecha) : null;
    }

    /**
     * La combinación que identifica un bloqueo: correo, panel e IP.
     *
     * Con la IP dentro, quien prueba contraseñas desde fuera no puede dejar
     * bloqueada la cuenta de otro, y a la vez se frena el rastreo desde una
     * misma máquina.
     */
    private function consulta(string $panel, string $email, ?string $ip)
    {
        return AccessLog::query()
            ->where('email', Str::lower($email))
            ->where('panel', $panel)
            ->where('ip', $ip);
    }

    private function registrar(Request $request, string $panel, string $resultado, ?string $email, ?User $usuario = null): void
    {
        AccessLog::create([
            'user_id' => $usuario?->id,
            // En minúsculas siempre: el contador busca por este campo, y con
            // "Jonas@" y "jonas@" mezclados cada variante llevaría su cuenta.
            'email' => $email ? Str::lower(Str::limit($email, 255, '')) : null,
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
