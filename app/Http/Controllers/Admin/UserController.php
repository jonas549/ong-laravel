<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ContrasenaCambiadaPorAdmin;
use App\Models\User;
use App\Services\ControlDeAcceso;
use App\Services\SesionesActivas;
use App\Services\SmtpConfigService;
use App\Support\Filtro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserController extends Controller
{
    public function __construct(
        private SesionesActivas $sesiones,
        private ControlDeAcceso $acceso,
    ) {
    }

    public function index(Request $request)
    {
        // El rol viene del menu: "Administradores" y "Organizadores" son dos
        // nodos distintos que llevan a la misma pantalla.
        $rol = Filtro::texto($request, 'rol');

        if ($rol && ! in_array($rol, [User::ROL_ADMIN, User::ROL_ORGANIZER], true)) {
            $rol = '';
        }

        $usuarios = User::with('organization')
            ->when($rol, fn ($q) => $q->where('role', $rol))
            ->when(Filtro::texto($request, 'q'), function ($q, $b) {
                $b = Filtro::like($b);

                $q->where(function ($w) use ($b) {
                    $w->where('name', 'like', "%{$b}%")->orWhere('email', 'like', "%{$b}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'usuarios' => $usuarios,
            'rol' => $rol,
            'conteos' => User::selectRaw('role, COUNT(*) n')->groupBy('role')->pluck('n', 'role'),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            // El tope de 72 no es capricho: bcrypt ignora lo que pase de ahí, y
            // sin él se puede elegir una de 100 y entrar luego con los primeros 72.
            'password' => ['required', 'string', 'min:8', 'max:72'],
            'role' => ['required', Rule::in([User::ROL_ADMIN, User::ROL_ORGANIZER])],
        ], [], [
            'name' => 'el nombre',
            'email' => 'el correo',
            'password' => 'la contraseña',
            'role' => 'rol',
        ]);

        User::create($datos + ['is_active' => true, 'email_verified_at' => now()]);

        return back()->with('ok', 'Usuario creado.');
    }

    public function edit(Request $request, User $user)
    {
        /*
         * El menú distingue "Administradores" de "Organizadores" por el
         * parámetro `rol`, así que sin él la ficha se queda sin nodo marcado y
         * sin migas. Se añade y se redirige en vez de dejarlo pasar, para que
         * la pantalla sea la misma se llegue desde el listado o escribiendo la
         * URL a mano.
         */
        if ($request->query('rol') !== $user->role) {
            // Sin el reflash, este salto se comería el "Contraseña actualizada"
            // de quien llegue aquí desde otro sitio: los mensajes viven un solo
            // request, y éste lo gasta sin pintar nada.
            $request->session()->reflash();

            return redirect()->route('admin.users.edit', [$user, 'rol' => $user->role]);
        }

        return view('admin.users.edit', [
            'usuario' => $user->load('organization'),
            'esUnoMismo' => $user->id === $request->user()->id,
            'sesionesAbiertas' => $this->sesiones->disponible()
                ? $this->sesiones->de($user, $request)->count()
                : null,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in([User::ROL_ADMIN, User::ROL_ORGANIZER])],
        ], [], ['name' => 'el nombre', 'email' => 'el correo', 'role' => 'el rol']);

        // Quitarse a uno mismo el rol de administración es quedarse fuera del
        // panel sin forma de volver a entrar.
        if ($user->id === $request->user()->id && $datos['role'] !== User::ROL_ADMIN) {
            throw ValidationException::withMessages([
                'role' => 'No puedes quitarte a ti mismo el rol de administración: te quedarías fuera del panel.',
            ]);
        }

        $user->update($datos);

        return redirect()
            ->route('admin.users.edit', [$user, 'rol' => $user->role])
            ->with('ok', 'Datos actualizados.');
    }

    /**
     * Le asigna una contraseña nueva a otra persona.
     *
     * No se pide la contraseña actual del afectado —el administrador no la
     * sabe, ese es justamente el caso de uso— así que todo el peso recae en lo
     * que pasa después: se cierran TODAS sus sesiones, queda registrado quién
     * lo hizo y se le avisa por correo. Sin esas tres cosas esto sería una
     * forma silenciosa de quedarse con la cuenta de cualquiera.
     */
    public function cambiarContrasena(Request $request, User $user, SmtpConfigService $smtp)
    {
        /*
         * La propia no: para eso está el perfil, que pide la contraseña actual.
         * Permitirlo aquí reabriría el agujero que se cerró allí, porque una
         * sesión de admin robada podría cambiarla sin conocer la anterior.
         */
        if ($user->id === $request->user()->id) {
            return redirect()
                ->route('admin.perfil')
                ->with('error', 'Tu propia contraseña se cambia desde tu perfil, con la actual delante.');
        }

        $datos = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ], [
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no puede pasar de 72 caracteres.',
        ], ['password' => 'la contraseña nueva']);

        if (Hash::check($datos['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Esa ya es su contraseña actual. Elige otra distinta.',
            ]);
        }

        $user->forceFill(['password' => $datos['password']])->save();

        // Todas, no sólo las de otros dispositivos: quien tuviera una sesión
        // abierta con la contraseña vieja tiene que salir. Rota además el
        // `remember_token`, que es lo único que invalida el "recuérdame".
        $cerradas = $this->sesiones->cerrarTodas($user);

        $this->acceso->registrarAccionDeAdmin($request, 'clave_admin', $user, $request->user());

        Log::warning('Un administrador cambió la contraseña de otra cuenta', [
            'admin_id' => $request->user()->id,
            'admin_email' => $request->user()->email,
            'objetivo_id' => $user->id,
            'objetivo_email' => $user->email,
            'sesiones_cerradas' => $cerradas,
        ]);

        $aviso = $this->avisar($user, $request->user(), $smtp);

        return redirect()
            ->route('admin.users.edit', [$user, 'rol' => $user->role])
            ->with('ok', $this->resumen($user, $cerradas, $aviso));
    }

    public function toggleActive(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('ok', 'Usuario actualizado.');
    }

    /**
     * Avisa al titular. Que el correo falle no deshace el cambio, que ya está
     * hecho: se dice que no salió y se sigue.
     */
    private function avisar(User $usuario, User $admin, SmtpConfigService $smtp): bool
    {
        try {
            $smtp->aplicar();

            Mail::to($usuario->email)->send(new ContrasenaCambiadaPorAdmin(
                nombre: $usuario->name,
                adminNombre: $admin->name,
                enlaceAcceso: route($usuario->esAdmin() ? 'admin.login' : 'account.login'),
                enlaceRecuperar: route('password.request'),
            ));

            return true;
        } catch (Throwable $e) {
            Log::warning('No se pudo avisar del cambio de contraseña', [
                'objetivo_email' => $usuario->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function resumen(User $usuario, int $cerradas, bool $aviso): string
    {
        $partes = ["Contraseña de {$usuario->name} actualizada."];

        $partes[] = match (true) {
            $cerradas === 0 => 'No tenía sesiones abiertas.',
            $cerradas === 1 => 'Se cerró su sesión.',
            default => "Se cerraron sus {$cerradas} sesiones.",
        };

        $partes[] = $aviso
            ? 'Le avisamos por correo.'
            : 'No se pudo enviar el aviso por correo: revisa Configuración → SMTP.';

        return implode(' ', $partes);
    }
}
