<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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
        ], [], ['email' => 'correo', 'password' => 'contraseña']);

        if (! Auth::attempt($datos + ['role' => User::ROL_ADMIN, 'is_active' => true], $request->boolean('remember'))) {
            // Si la contraseña es correcta pero la cuenta es de organizador,
            // el problema es la puerta, no las credenciales: hay que decirlo.
            $otroRol = User::credencialesDeOtroRol($datos['email'], $datos['password'], User::ROL_ADMIN);

            throw ValidationException::withMessages([
                'email' => $otroRol
                    ? 'Esa es una cuenta de organizador. Entra por el acceso de organizaciones, en ' . route('account.login') . '.'
                    : 'Esas credenciales no corresponden a una cuenta de administración.',
            ]);
        }

        $request->session()->regenerate();
        Auth::user()->forceFill(['last_login_at' => now()])->save();

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
