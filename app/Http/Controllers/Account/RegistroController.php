<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistroOrganizadorRequest;
use App\Models\AccessLog;
use App\Models\Organization;
use App\Models\User;
use App\Rules\CorreoEnviable;
use App\Services\ControlDeAcceso;
use App\Services\CorreoTransaccional;
use App\Support\ReglasDeCampo;
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
            'organizacionElegida' => $this->organizacionReclamada(),
        ]);
    }

    /**
     * La organización libre que se estuviera reclamando, si la hay.
     *
     * Se vuelve a leer de la base y no de lo que llega en el POST: el id viene
     * del navegador, así que decidir con lo que él diga permitiría reclamar
     * una organización que ya tiene dueño sólo con cambiar el número.
     *
     * Hace falta al volver de un rebote: sin esto, un error de validación le
     * devuelve la pantalla pidiéndole otra vez el tipo de organización, que es
     * justo lo que se le había dejado de pedir.
     *
     * @return array<string, mixed>|null
     */
    private function organizacionReclamada(): ?array
    {
        $id = (int) old('org_id');

        if ($id <= 0) {
            return null;
        }

        $organizacion = Organization::sinReclamar()->where('activo', true)->find($id);

        return $organizacion ? [
            'id' => $organizacion->id,
            'nombre' => $organizacion->nombre,
            'tipo' => $organizacion->tipo,
            'tipo_otro' => $organizacion->tipo_otro,
            'libre' => true,
        ] : null;
    }

    public function store(RegistroOrganizadorRequest $request, CorreoTransaccional $correos, ControlDeAcceso $acceso)
    {
        // Con sesión abierta no se crea otra cuenta: sería dejar la anterior
        // huérfana sin querer.
        if (Auth::check()) {
            return redirect()->route(Auth::user()->esAdmin() ? 'admin.dashboard' : 'account.activities.index');
        }

        // Las reglas viven en RegistroOrganizadorRequest, que comparte con el
        // wizard el trait de reclamar organizaciones.
        $datos = $request->validated();

        $reclamada = $request->reclamada();

        // Reclamando una del listado, el tipo lo trae ella: el formulario ni
        // lo pinta, así que lo que llegue en el POST no manda.
        if ($reclamada) {
            $datos['org_tipo'] = $reclamada->tipo;
        }

        // Los campos condicionales sólo valen para su tipo: si no, quedaba
        // guardado lo que se hubiera escrito antes de cambiar de opción.
        $datos['org_tipo_otro'] = $datos['org_tipo'] === 'Otra' ? ($datos['org_tipo_otro'] ?? null) : null;
        $datos['org_unidad_educativa'] = $datos['org_tipo'] === 'Institución educativa'
            ? ($datos['org_unidad_educativa'] ?? null)
            : null;

        try {
            $usuario = DB::transaction(function () use ($datos, $request) {
                $usuario = User::create([
                    'name' => $datos['name'],
                    'email' => $datos['email'],
                    'password' => $datos['password'],
                    'role' => User::ROL_ORGANIZER,
                    'is_active' => true,
                ]);

                /*
                 * C1: si eligió una organización del listado histórico, se le
                 * pone dueño en vez de crear otra igual. Es lo mismo que hace
                 * el wizard (P10), y por eso lo decide el mismo trait.
                 *
                 * De lo que llega en el formulario sólo se le aplica el correo
                 * de contacto: el nombre y el tipo son los de la ONG y no se
                 * pisan con lo que venga.
                 */
                $request->reclamarOCrear(
                    $usuario,
                    campos: [
                        'nombre' => $datos['org_nombre'],
                        'tipo' => $datos['org_tipo'],
                        'tipo_otro' => $datos['org_tipo_otro'],
                        'unidad_educativa' => $datos['org_unidad_educativa'],
                        'correo_contacto' => $datos['email'],
                    ],
                    alReclamar: ['correo_contacto' => $datos['email']],
                );

                return $usuario;
            });
        } catch (UniqueConstraintViolationException) {
            // Doble clic en "Crear cuenta": los dos pasaron la validación antes
            // de que ninguno de los dos INSERT llegara. Se contesta lo mismo que
            // habría contestado la validación, no un 500.
            throw ValidationException::withMessages([
                'email' => ReglasDeCampo::CORREO_YA_EXISTE,
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
