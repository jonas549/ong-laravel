<?php

namespace App\Services;

use App\Models\User;
use App\Support\Dispositivo;
use Illuminate\Auth\Recaller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sesiones abiertas de una persona, y cierre remoto.
 *
 * Se apoya en la tabla `sessions`, que existe porque el driver de sesión es
 * `database`. Si algún día se cambia a `file` o a `cookie`, esto deja de tener
 * de dónde leer: por eso se comprueba el driver antes de nada.
 *
 * Ojo con el "recuérdame": borrar la fila de `sessions` NO echa a quien tenga
 * esa cookie. En la siguiente petición Laravel la lee, vuelve a autenticar y
 * crea una sesión nueva. Por eso todo cierre remoto rota además el
 * `remember_token`, que es lo único que invalida esas cookies.
 */
class SesionesActivas
{
    public function disponible(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * Sesiones vivas de esta persona.
     *
     * Se filtran las caducadas: la fila sobrevive hasta que pasa el recolector
     * —una lotería de 2 entre 100—, así que sin este filtro la pantalla
     * enseñaba como "abierta ahora mismo" una sesión de hacía días.
     *
     * @return Collection<int, object>
     */
    public function de(User $usuario, Request $request)
    {
        if (! $this->disponible()) {
            return collect();
        }

        $actual = $request->session()->getId();

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $usuario->id)
            ->where('last_activity', '>=', $this->limiteDeVida())
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($s) => (object) [
                'id' => $s->id,
                'esActual' => $s->id === $actual,
                'ip' => $s->ip_address,
                'dispositivo' => Dispositivo::describir($s->user_agent),
                'ultimaActividad' => Carbon::createFromTimestamp($s->last_activity),
            ]);
    }

    /**
     * Cierra todas las demás sesiones y deja viva la actual.
     *
     * Devuelve cuántas cerró contando sólo las que estaban vivas: si contara
     * también las caducadas, el aviso diría "cerramos 5 sesiones" cuando en
     * realidad no había ninguna abierta.
     */
    public function cerrarOtras(User $usuario, Request $request): int
    {
        if (! $this->disponible()) {
            return 0;
        }

        $tabla = config('session.table', 'sessions');

        $vivas = DB::table($tabla)
            ->where('user_id', $usuario->id)
            ->where('id', '!=', $request->session()->getId())
            ->where('last_activity', '>=', $this->limiteDeVida())
            ->count();

        DB::table($tabla)
            ->where('user_id', $usuario->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $this->rotarRecordatorio($usuario, $request);

        return $vivas;
    }

    /** Cierra una sesión concreta. Nunca la que se está usando. */
    public function cerrar(User $usuario, Request $request, string $id): bool
    {
        if (! $this->disponible() || $id === $request->session()->getId()) {
            return false;
        }

        $cerrada = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $usuario->id)
            ->where('id', $id)
            ->delete() > 0;

        if ($cerrada) {
            $this->rotarRecordatorio($usuario, $request);
        }

        return $cerrada;
    }

    /**
     * Cierra TODAS las sesiones, incluida la de quien lo pide.
     *
     * Es lo que hace falta tras restablecer la contraseña: ahí no hay sesión
     * propia que preservar, y quien estuviera dentro con la contraseña vieja
     * —que es justo de quien se huye al usar ese flujo— tiene que salir.
     */
    public function cerrarTodas(User $usuario): int
    {
        $usuario->forceFill(['remember_token' => Str::random(60)])->save();

        if (! $this->disponible()) {
            return 0;
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $usuario->id)
            ->delete();
    }

    /**
     * Invalida las cookies de "recuérdame" de todos los dispositivos y le
     * devuelve una nueva a este, si es que traía una de verdad.
     *
     * Sin lo segundo, quien cierra las sesiones ajenas se quedaría también sin
     * su propio recordatorio y tendría que volver a escribir la contraseña la
     * próxima vez, sin haber pedido nada de eso.
     */
    private function rotarRecordatorio(User $usuario, Request $request): void
    {
        $traiaRecordatorio = $this->recordatorioValido($usuario, $request);

        $usuario->forceFill(['remember_token' => Str::random(60)])->save();

        if (! $traiaRecordatorio) {
            return;
        }

        /*
         * La cookie se pone a mano en vez de con `$guard->login($usuario, true)`
         * por dos motivos. Uno, login() migra el identificador de sesión, y con
         * dos peticiones a la vez —un doble clic en "Cerrar las demás"— eso
         * dejaba dos filas nuevas en `sessions`, es decir, una sesión viva de
         * más justo cuando se prometía lo contrario. Y dos, aquí no hay que
         * volver a autenticar a nadie: la persona ya está dentro y lo único
         * que cambia es el token del recordatorio.
         *
         * El formato es el que lee Illuminate\Auth\Recaller: id|token|hash.
         */
        Cookie::queue(Cookie::forever(
            Auth::guard('web')->getRecallerName(),
            $usuario->getAuthIdentifier().'|'.$usuario->getRememberToken().'|'.$usuario->getAuthPassword(),
        ));
    }

    /**
     * ¿La petición trae un recordatorio válido y de esta misma persona?
     *
     * `$request->cookies->has(...)` no vale: es cierto también cuando la cookie
     * viene y no se puede descifrar, porque EncryptCookies deja la clave puesta
     * con valor nulo. Con esa comprobación bastaba con mandar un
     * `remember_web_…=basura` para que le devolviéramos un recordatorio nuevo y
     * válido a quien nunca tuvo uno: una sesión robada de dos horas se
     * convertía en acceso permanente.
     */
    private function recordatorioValido(User $usuario, Request $request): bool
    {
        $valor = $request->cookies->get(Auth::guard('web')->getRecallerName());

        if (! is_string($valor) || $valor === '') {
            return false;
        }

        $recaller = new Recaller($valor);

        if (! $recaller->valid() || (string) $recaller->id() !== (string) $usuario->getAuthIdentifier()) {
            return false;
        }

        return hash_equals((string) $usuario->getRememberToken(), $recaller->token());
    }

    /** Momento a partir del cual una sesión sigue considerándose viva. */
    private function limiteDeVida(): int
    {
        return now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp();
    }
}
