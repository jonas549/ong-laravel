<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\User;
use App\Services\ControlDeAcceso;
use App\Support\PuertaDeAcceso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private ControlDeAcceso $acceso) {}

    public function showLogin()
    {
        if (Auth::check() && Auth::user()->esOrganizador()) {
            return redirect()->route('account.activities.index');
        }

        // Si viene rebotado del otro acceso, el correo ya lo escribió allí.
        return view('account.auth.login', [
            'correoSugerido' => PuertaDeAcceso::correoRecordado(PuertaDeAcceso::A_ORGANIZADOR),
        ]);
    }

    public function login(Request $request)
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], ['email' => 'el correo', 'password' => 'la contraseña']);

        $this->acceso->comprobarBloqueo($request, AccessLog::PANEL_ORGANIZADOR);

        if (! Auth::attempt($datos + ['role' => User::ROL_ORGANIZER, 'is_active' => true], $request->boolean('remember'))) {
            /*
             * Un fallo de Auth::attempt puede ser tres cosas distintas, porque
             * la condición lleva también el rol y el is_active. Se separan para
             * que el log diga la verdad y para no mandar a alguien a buscar una
             * contraseña que en realidad ya es correcta.
             */
            $duenno = User::porCredenciales($datos['email'], $datos['password']);

            $motivo = match (true) {
                $duenno === null => 'credenciales',
                $duenno->role !== User::ROL_ORGANIZER => 'rol',
                ! $duenno->is_active => 'inactiva',
                default => 'credenciales',
            };

            $this->acceso->fallo($request, AccessLog::PANEL_ORGANIZADOR, $motivo, $duenno);

            // El mismo aviso con botón, en el otro sentido. Ver el gemelo en
            // Admin\AuthController para el porqué de que vaya sólo aquí.
            if ($motivo === 'rol') {
                PuertaDeAcceso::sugerir(PuertaDeAcceso::A_ADMIN, $datos['email']);
            }

            throw ValidationException::withMessages([
                'email' => match ($motivo) {
                    'rol' => 'Esa es una cuenta de administración, no de organización.',
                    'inactiva' => 'Esa cuenta está desactivada. Escríbenos si crees que es un error.',
                    default => 'No encontramos una cuenta con ese correo y contraseña.',
                },
            ]);
        }

        $request->session()->regenerate();

        $usuario = Auth::user();
        $usuario->forceFill(['last_login_at' => now()])->save();

        $this->acceso->exito($request, AccessLog::PANEL_ORGANIZADOR, $usuario);

        return redirect()->intended(route('account.activities.index'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
