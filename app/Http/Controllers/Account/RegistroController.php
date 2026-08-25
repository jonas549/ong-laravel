<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Organization;
use App\Models\User;
use App\Rules\CorreoEnviable;
use App\Services\ControlDeAcceso;
use App\Services\CorreoTransaccional;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Registro de cuenta sin pasar por el wizard.
 *
 * Crea también la organización, no sólo el usuario: un organizador sin
 * organización no puede hacer nada —ni publicar, ni ver inscritos—, así que
 * una cuenta suelta sería una cuenta muerta. Se piden los dos datos mínimos
 * que el wizard también pide, nombre y tipo, y el resto se completa después
 * desde la primera actividad.
 */
class RegistroController extends Controller
{
    public function create()
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->esAdmin() ? 'admin.dashboard' : 'account.activities.index');
        }

        return view('account.auth.registro', [
            'tiposOrg' => Organization::TIPOS,
        ]);
    }

    public function store(Request $request, CorreoTransaccional $correos, ControlDeAcceso $acceso)
    {
        // Con sesión abierta no se crea otra cuenta: sería dejar la anterior
        // huérfana sin querer.
        if (Auth::check()) {
            return redirect()->route(Auth::user()->esAdmin() ? 'admin.dashboard' : 'account.activities.index');
        }

        $datos = $request->validate([
            'org_nombre' => ['required', 'string', 'max:255'],
            'org_tipo' => ['required', Rule::in(Organization::TIPOS)],
            'org_tipo_otro' => ['nullable', 'required_if:org_tipo,Otra', 'string', 'max:255'],
            'org_unidad_educativa' => ['nullable', 'required_if:org_tipo,Institución educativa', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', new CorreoEnviable, Rule::unique('users', 'email')],
            // El tope de 72 no es capricho: bcrypt ignora lo que pase de ahí.
            // Sin ese límite alguien podía elegir una contraseña de 100
            // caracteres y entrar después con los primeros 72, sin enterarse.
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ], [
            'email.unique' => 'Ya existe una cuenta con ese correo. Entra con ella.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no puede pasar de 72 caracteres.',
            'org_tipo_otro.required_if' => 'Especifica qué tipo de organización es.',
            'org_unidad_educativa.required_if' => 'Indica el nombre de la unidad educativa.',
        ], [
            'org_nombre' => 'el nombre de la organización',
            'org_tipo' => 'el tipo de organización',
            'name' => 'el nombre',
            'email' => 'el correo',
            'password' => 'la contraseña',
        ]);

        // Los campos condicionales sólo valen para su tipo: si no, quedaba
        // guardado lo que se hubiera escrito antes de cambiar de opción.
        $datos['org_tipo_otro'] = $datos['org_tipo'] === 'Otra' ? ($datos['org_tipo_otro'] ?? null) : null;
        $datos['org_unidad_educativa'] = $datos['org_tipo'] === 'Institución educativa'
            ? ($datos['org_unidad_educativa'] ?? null)
            : null;

        try {
            $usuario = DB::transaction(function () use ($datos) {
                $usuario = User::create([
                    'name' => $datos['name'],
                    'email' => $datos['email'],
                    'password' => $datos['password'],
                    'role' => User::ROL_ORGANIZER,
                    'is_active' => true,
                ]);

                Organization::create([
                    'user_id' => $usuario->id,
                    'nombre' => $datos['org_nombre'],
                    'tipo' => $datos['org_tipo'],
                    'tipo_otro' => $datos['org_tipo_otro'],
                    'unidad_educativa' => $datos['org_unidad_educativa'],
                    'correo_contacto' => $datos['email'],
                ]);

                return $usuario;
            });
        } catch (UniqueConstraintViolationException) {
            // Doble clic en "Crear cuenta": los dos pasaron la validación antes
            // de que ninguno de los dos INSERT llegara. Se contesta lo mismo que
            // habría contestado la validación, no un 500.
            throw ValidationException::withMessages([
                'email' => 'Ya existe una cuenta con ese correo. Entra con ella.',
            ]);
        }

        Auth::login($usuario);
        $request->session()->regenerate();

        // La entrada se registra como cualquier otra: sin esto, quien acaba de
        // registrarse está usando el sitio sin aparecer en el log de accesos.
        $usuario->forceFill(['last_login_at' => now()])->save();
        $acceso->exito($request, AccessLog::PANEL_ORGANIZADOR, $usuario);

        // Primero la bienvenida y después la verificación, el mismo orden que
        // sigue el wizard, para que las dos altas entreguen lo mismo.
        $correos->bienvenida($usuario->fresh('organization'));
        event(new Registered($usuario));

        return redirect()
            ->route('account.activities.index')
            ->with('ok', 'Tu cuenta está lista. Te enviamos un correo para confirmar tu dirección.');
    }
}
