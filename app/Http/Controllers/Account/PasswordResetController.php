<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SesionesActivas;
use App\Services\SmtpConfigService;
use App\Support\Filtro;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Recuperación de contraseña para los dos paneles.
 *
 * El enlace del correo es uno solo, porque el token no distingue de qué panel
 * salió la petición. Lo que cambia es a dónde se vuelve al terminar: se decide
 * por el rol de la cuenta, así que un admin acaba en el panel y un organizador
 * en su cuenta, sin importar por dónde empezara.
 */
class PasswordResetController extends Controller
{
    public function __construct(private SesionesActivas $sesiones) {}

    public function request(Request $request)
    {
        return view('account.auth.forgot', [
            'esAdmin' => $this->desdeAdmin($request),
        ]);
    }

    public function email(Request $request)
    {
        $request->validate(
            ['email' => ['required', 'email']],
            [],
            ['email' => 'el correo'],
        );

        app(SmtpConfigService::class)->aplicar();

        // El resultado se ignora a propósito. Antes se avisaba del throttle con
        // un mensaje propio, y eso delataba qué correos existen: pidiendo el
        // enlace dos veces seguidas, una cuenta real contestaba "ya te enviamos
        // uno" y una inexistente seguía con el mensaje neutro.
        Password::sendResetLink($request->only('email'));

        // No se confirma ni se desmiente que el correo exista: la respuesta es
        // la misma en todos los casos, para no revelar qué cuentas hay.
        return back()->with('ok', 'Si ese correo tiene una cuenta, te enviamos un enlace para crear una contraseña nueva. Puede tardar un par de minutos.');
    }

    public function reset(Request $request, string $token)
    {
        return view('account.auth.reset', [
            'token' => $token,
            'email' => Filtro::texto($request, 'email'),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            // El tope de 72 no es capricho: bcrypt ignora lo que pase de ahí.
            // Sin ese límite alguien podía elegir una contraseña de 100
            // caracteres y entrar después con los primeros 72, sin enterarse.
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ], [
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no puede pasar de 72 caracteres.',
        ], ['email' => 'el correo', 'password' => 'la contraseña']);

        $reseteado = null;

        $estado = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $usuario, string $password) use (&$reseteado) {
                $usuario->forceFill([
                    'password' => $password,
                    // Invalida el "recuérdame" de cualquier dispositivo donde
                    // la sesión siguiera abierta con la contraseña anterior.
                    'remember_token' => Str::random(60),
                ])->save();

                $reseteado = $usuario;

                event(new PasswordReset($usuario));
            },
        );

        if ($estado !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'El enlace no es válido o ya caducó. Pide uno nuevo.',
            ]);
        }

        /*
         * Se cierran TODAS las sesiones, no sólo las de otros dispositivos:
         * este es el flujo que usa quien sospecha que le entraron en la cuenta,
         * y hasta ahora quien estuviera dentro con la contraseña vieja seguía
         * dentro tan tranquilo. Aquí no hay sesión propia que preservar, porque
         * para llegar a esta pantalla no hace falta haber iniciado sesión.
         */
        $this->sesiones->cerrarTodas($reseteado);

        $destino = $reseteado?->esAdmin() ? 'admin.login' : 'account.login';

        return redirect()
            ->route($destino)
            ->with('ok', 'Listo, ya puedes entrar con tu contraseña nueva.');
    }

    /** La pantalla cambia de aspecto según por dónde se haya entrado. */
    private function desdeAdmin(Request $request): bool
    {
        return $request->routeIs('admin.*') || $request->is('admin/*');
    }
}
