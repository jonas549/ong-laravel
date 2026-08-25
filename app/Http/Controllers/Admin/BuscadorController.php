<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Support\Filtro;
use App\Support\MenuPanel;
use Illuminate\Http\Request;

/**
 * Buscador del panel.
 *
 * Busca en lo que se busca de verdad —una actividad, una organización, una
 * persona inscrita— y además en el propio menú, porque con el árbol ya crecido
 * cuesta más recordar dónde estaba una pantalla que encontrar un registro.
 */
class BuscadorController extends Controller
{
    private const POR_GRUPO = 8;

    public function __invoke(Request $request)
    {
        $termino = Filtro::texto($request, 'q');

        return view('admin.buscador', [
            'termino' => $termino,
            'grupos' => mb_strlen($termino) >= 2 ? $this->buscar($termino) : [],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function buscar(string $termino): array
    {
        $like = '%'.Filtro::like($termino).'%';

        $grupos = [
            [
                'titulo' => 'Pantallas del panel',
                'items' => $this->pantallas($termino),
            ],
            [
                'titulo' => 'Actividades',
                'items' => Activity::with('organization')
                    ->where(fn ($q) => $q->where('titulo', 'like', $like)->orWhere('slug', 'like', $like))
                    ->latest('updated_at')->limit(self::POR_GRUPO)->get()
                    ->map(fn (Activity $a) => [
                        'texto' => $a->titulo,
                        'detalle' => ($a->organization?->nombre ?? 'Sin organización').' · '.$a->estado,
                        'url' => route('admin.activities.show', $a),
                    ])->all(),
            ],
            [
                'titulo' => 'Organizaciones',
                'items' => Organization::where('nombre', 'like', $like)
                    ->orderBy('nombre')->limit(self::POR_GRUPO)->get()
                    ->map(fn (Organization $o) => [
                        'texto' => $o->nombre,
                        'detalle' => $o->verificada ? 'Verificada' : 'Sin verificar',
                        'url' => route('admin.organizations.index', ['q' => $o->nombre]),
                    ])->all(),
            ],
            [
                'titulo' => 'Usuarios',
                'items' => User::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
                    ->orderBy('name')->limit(self::POR_GRUPO)->get()
                    ->map(fn (User $u) => [
                        'texto' => $u->name,
                        'detalle' => $u->email.' · '.($u->esAdmin() ? 'Administrador' : 'Organizador'),
                        'url' => route('admin.users.index', ['rol' => $u->role, 'q' => $u->email]),
                    ])->all(),
            ],
            [
                'titulo' => 'Inscripciones',
                'items' => Registration::with('activity')
                    ->where(fn ($q) => $q->where('nombre', 'like', $like)->orWhere('correo', 'like', $like))
                    ->latest('id')->limit(self::POR_GRUPO)->get()
                    ->map(fn (Registration $r) => [
                        'texto' => $r->nombre,
                        'detalle' => $r->correo.' · '.($r->activity?->titulo ?? 'Actividad borrada'),
                        'url' => route('admin.registrations.index', ['q' => $r->correo]),
                    ])->all(),
            ],
        ];

        return array_values(array_filter($grupos, fn ($g) => $g['items'] !== []));
    }

    /**
     * Nodos del menú cuyo texto contiene el término, con su camino para que se
     * entienda dónde está cada uno.
     *
     * @return array<int, array<string, string>>
     */
    private function pantallas(string $termino): array
    {
        $encontrados = [];

        $recorrer = function (array $nodo, array $camino) use (&$recorrer, $termino, &$encontrados) {
            $camino[] = $nodo['texto'];

            $url = MenuPanel::url($nodo);

            if ($url && mb_stripos($nodo['texto'], $termino) !== false) {
                $encontrados[] = [
                    'texto' => $nodo['texto'],
                    'detalle' => implode(' → ', array_slice($camino, 0, -1)) ?: 'Panel',
                    'url' => $url,
                ];
            }

            foreach ($nodo['hijos'] as $hijo) {
                $recorrer($hijo, $camino);
            }
        };

        foreach (MenuPanel::arbol() as $nodo) {
            $recorrer($nodo, []);
        }

        return array_slice($encontrados, 0, self::POR_GRUPO);
    }
}
