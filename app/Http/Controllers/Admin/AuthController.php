<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\User;
use App\Services\ControlDeAcceso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private ControlDeAcceso $acceso) {}

    public function showLogin()
    {
        if (Auth::check() && Auth::user()->esAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], ['email' => 'el correo', 'password' => 'la contraseña']);

        $this->acceso->comprobarBloqueo($request, AccessLog::PANEL_ADMIN);

        if (! Auth::attempt($datos + ['role' => User::ROL_ADMIN, 'is_active' => true], $request->boolean('remember'))) {
            /*
             * Un fallo de Auth::attempt puede ser tres cosas distintas, porque
             * la condición lleva también el rol y el is_active. Se separan para
             * que el log diga la verdad y para no mandar a alguien a buscar una
             * contraseña que en realidad ya es correcta.
             */
            $duenno = User::porCredenciales($datos['email'], $datos['password']);

            $motivo = match (true) {
                $duenno === null => 'credenciales',
                $duenno->role !== User::ROL_ADMIN => 'rol',
                ! $duenno->is_active => 'inactiva',
                default => 'credenciales',
            };

            $this->acceso->fallo($request, AccessLog::PANEL_ADMIN, $motivo, $duenno);

            throw ValidationException::withMessages([
                'email' => match ($motivo) {
                    'rol' => 'Esa es una cuenta de organizador. Entra por el acceso de organizaciones, en '.route('account.login').'.',
                    'inactiva' => 'Esa cuenta está desactivada. Escríbenos si crees que es un error.',
                    default => 'Esas credenciales no corresponden a una cuenta de administración.',
                },
            ]);
        }

        $request->session()->regenerate();

        $usuario = Auth::user();
        $usuario->forceFill(['last_login_at' => now()])->save();

        $this->acceso->exito($request, AccessLog::PANEL_ADMIN, $usuario);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
