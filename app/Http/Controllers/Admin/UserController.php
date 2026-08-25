<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Filtro;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
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
            'password' => ['required', 'string', 'min:8'],
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

    public function toggleActive(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('ok', 'Usuario actualizado.');
    }
}
