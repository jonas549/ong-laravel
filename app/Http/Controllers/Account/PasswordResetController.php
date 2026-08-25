<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SmtpConfigService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "¿Olvidaste tu contraseña?" del login de mi-cuenta.html.
 *
 * Usa el broker de Laravel tal cual; lo único propio es que el correo sale
 * por el SMTP configurado desde el panel, igual que el resto de los avisos.
 */
class PasswordResetController extends Controller
{
    public function request()
    {
        return view('account.auth.forgot');
    }

    public function email(Request $request)
    {
        $request->validate(
            ['email' => ['required', 'email']],
            [],
            ['email' => 'correo'],
        );

        app(SmtpConfigService::class)->aplicar();

        $estado = Password::sendResetLink($request->only('email'));

        // No confirmamos ni desmentimos que el correo exista: da lo mismo el
        // resultado, la respuesta es la misma.
        if ($estado === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => 'Ya te enviamos un enlace hace poco. Revisa tu correo antes de pedir otro.',
            ]);
        }

        return back()->with('ok', 'Si ese correo tiene una cuenta, te enviamos un enlace para crear una contraseña nueva.');
    }

    public function reset(Request $request, string $token)
    {
        return view('account.auth.reset', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ], ['email' => 'correo', 'password' => 'contraseña']);

        $estado = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $usuario, string $password) {
                $usuario->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($usuario));
            },
        );

        if ($estado !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'El enlace no es válido o ya caducó. Pide uno nuevo.',
            ]);
        }

        return redirect()
            ->route('account.login')
            ->with('ok', 'Listo, ya puedes entrar con tu contraseña nueva.');
    }
}
