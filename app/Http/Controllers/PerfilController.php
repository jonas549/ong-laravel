<?php

namespace App\Http\Controllers;

use App\Mail\CorreoCambiado;
use App\Models\Setting;
use App\Rules\CorreoEnviable;
use App\Services\SesionesActivas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Perfil de la persona que ha iniciado sesión.
 *
 * Uno solo para los dos paneles: los datos y las acciones son idénticos, y lo
 * único que cambia es el layout en el que se pinta y a dónde se vuelve.
 */
class PerfilController extends Controller
{
    public function __construct(private SesionesActivas $sesiones) {}

    public function edit(Request $request)
    {
        $usuario = $request->user();

        return view('perfil.edit', [
            'usuario' => $usuario,
            'sesiones' => $this->sesiones->de($usuario, $request),
            'sesionesDisponibles' => $this->sesiones->disponible(),
            'esAdmin' => $usuario->esAdmin(),
        ]);
    }

    public function update(Request $request)
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', new CorreoEnviable, Rule::unique('users', 'email')->ignore($usuario->id)],
            'actual_correo' => ['nullable', 'string'],
        ], [], ['name' => 'el nombre', 'email' => 'el correo', 'actual_correo' => 'la contraseña actual']);

        $correoAnterior = $usuario->email;

        // Se compara sin distinguir mayúsculas porque MySQL tampoco las
        // distingue: cambiar sólo la caja es el mismo buzón, y antes eso
        // invalidaba la verificación y mandaba un correo para nada.
        $cambioCorreo = mb_strtolower($correoAnterior) !== mb_strtolower($datos['email']);

        if ($cambioCorreo) {
            /*
             * Cambiar la dirección exige la contraseña, igual que cambiarla a
             * ella. Sin esto, quien se hiciera con una sesión ponía su propio
             * correo y pedía acto seguido "olvidé mi contraseña": el enlace le
             * llegaba a él y la cuenta cambiaba de dueño sin que hiciera falta
             * saber la contraseña en ningún momento.
             */
            if (blank($datos['actual_correo']) || ! Hash::check($datos['actual_correo'], $usuario->password)) {
                throw ValidationException::withMessages([
                    'actual_correo' => blank($datos['actual_correo'])
                        ? 'Escribe tu contraseña actual para cambiar el correo.'
                        : 'Esa no es tu contraseña actual.',
                ]);
            }

            // Volver a verificar: si no, bastaría con apuntar la cuenta a otra
            // dirección para saltarse la verificación.
            $datos['email_verified_at'] = null;
        }

        unset($datos['actual_correo']);

        $usuario->forceFill($datos)->save();

        if (! $cambioCorreo) {
            return back()->with('ok', 'Datos guardados.');
        }

        // Sin esto había que ir a buscar el botón de reenviar: la dirección
        // nueva quedaba sin verificar y sin ningún correo en camino.
        $usuario->sendEmailVerificationNotification();

        // Y la anterior se entera del cambio: es lo único que le queda a la
        // persona para darse cuenta si el cambio no lo hizo ella.
        $contacto = Setting::get('sitio_email_contacto');

        Mail::to($correoAnterior)->send(new CorreoCambiado(
            nombre: $usuario->name,
            correoAnterior: $correoAnterior,
            correoNuevo: $usuario->email,
            enlaceAyuda: $contacto ? 'mailto:'.$contacto : url('/'),
        ));

        return back()->with('ok', 'Datos guardados. Te enviamos un correo a la dirección nueva para confirmarla, y avisamos a la anterior del cambio.');
    }

    public function password(Request $request)
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'actual' => ['required', 'string'],
            // El tope de 72 no es capricho: bcrypt ignora lo que pase de ahí.
            // Sin ese límite alguien podía elegir una contraseña de 100
            // caracteres y entrar después con los primeros 72, sin enterarse.
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ], [
            'password.confirmed' => 'Las contraseñas nuevas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no puede pasar de 72 caracteres.',
        ], ['actual' => 'la contraseña actual', 'password' => 'la contraseña nueva']);

        if (! Hash::check($datos['actual'], $usuario->password)) {
            throw ValidationException::withMessages([
                'actual' => 'Esa no es tu contraseña actual.',
            ]);
        }

        if (Hash::check($datos['password'], $usuario->password)) {
            throw ValidationException::withMessages([
                'password' => 'La contraseña nueva tiene que ser distinta de la actual.',
            ]);
        }

        $usuario->forceFill(['password' => $datos['password']])->save();

        // Se cierran las demás sesiones: si alguien más tenía la contraseña
        // vieja y una sesión abierta, cambiarla no serviría de nada.
        $cerradas = $this->sesiones->cerrarOtras($usuario, $request);

        // La sesión actual se mantiene, pero con la contraseña nueva.
        $request->session()->put('password_hash_web', $usuario->getAuthPassword());

        return back()->with('ok', 'Contraseña actualizada. '.$this->resumenDeCierre($cerradas));
    }

    /** Cierra una sesión concreta desde la lista. */
    public function cerrarSesion(Request $request)
    {
        $id = $request->validate(['sesion' => ['required', 'string']])['sesion'];

        return $this->sesiones->cerrar($request->user(), $request, $id)
            ? back()->with('ok', 'Sesión cerrada.')
            : back()->with('error', 'No pudimos cerrar esa sesión. Puede que ya estuviera cerrada.');
    }

    public function cerrarOtras(Request $request)
    {
        $cerradas = $this->sesiones->cerrarOtras($request->user(), $request);

        return back()->with('ok', $this->resumenDeCierre($cerradas));
    }

    /**
     * Qué contar sobre las sesiones cerradas.
     *
     * Con un driver de sesión que no sea `database` no hay forma de cerrarlas
     * ni de contarlas, así que decir "no había otras sesiones abiertas" sería
     * afirmar algo que no se sabe.
     */
    private function resumenDeCierre(int $cerradas): string
    {
        if (! $this->sesiones->disponible()) {
            return 'Las sesiones de otros dispositivos no se pueden cerrar desde aquí porque no se están guardando en base de datos.';
        }

        if ($cerradas === 0) {
            return 'No había otras sesiones abiertas.';
        }

        return "Cerramos {$cerradas} ".($cerradas === 1 ? 'sesión' : 'sesiones').' en otros dispositivos.';
    }
}
