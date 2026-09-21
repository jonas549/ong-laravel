<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Setting;
use App\Support\Filtro;
use App\Support\Marquesina;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Páginas → Marquesina de organizaciones.
 *
 * Qué organizaciones salen en la tira del home y en qué orden, y el
 * interruptor que la llena sola con todas. Ver `App\Support\Marquesina`.
 */
class MarquesinaController extends Controller
{
    public function edit()
    {
        return view('admin.marquesina.edit', [
            'automatica' => Marquesina::automatica(),
            'elegidas' => Marquesina::elegidas()->map(fn (Organization $o) => $this->fila($o))->values(),
            'cuantasTodas' => Marquesina::todas()->count(),
        ]);
    }

    /**
     * El buscador para añadir a la lista.
     *
     * Propio del panel y no el público del wizard: aquél lleva freno de 60
     * por minuto, devuelve ocho y no trae el logo.
     */
    public function buscar(Request $request)
    {
        $texto = Filtro::texto($request, 'q');

        if (mb_strlen(trim($texto)) < 2) {
            return response()->json(['organizaciones' => []]);
        }

        $organizaciones = Organization::query()
            ->where('nombre', 'like', '%'.Filtro::like(trim($texto)).'%')
            ->orderByRaw('CASE WHEN nombre LIKE ? THEN 0 ELSE 1 END', [Filtro::like(trim($texto)).'%'])
            ->orderBy('nombre')
            ->limit(15)
            ->get(['id', 'nombre', 'logo_path', 'activo']);

        return response()->json([
            'organizaciones' => $organizaciones->map(fn (Organization $o) => $this->fila($o))->values(),
        ]);
    }

    public function update(Request $request)
    {
        $datos = $request->validate([
            'automatica' => ['nullable', 'boolean'],
            'organizaciones' => ['nullable', 'array', 'max:1000'],
            // Borradas no: el formulario no las ofrece, y en el home no saldrían.
            'organizaciones.*' => ['integer', Rule::exists('organizations', 'id')->whereNull('deleted_at')],
        ], [], ['organizaciones.*' => 'organización']);

        /*
         * Se guardan las dos cosas siempre, esté como esté el interruptor: la
         * lista manual se puede ir preparando con el automático encendido, y
         * apagarlo no puede borrarla.
         */
        Setting::set(Marquesina::AJUSTE, $request->boolean('automatica'));
        Marquesina::guardar($datos['organizaciones'] ?? []);

        return back()->with('ok', $request->boolean('automatica')
            ? 'Guardado. La marquesina muestra todas las organizaciones; la lista manual queda guardada para cuando lo apagues.'
            : 'Guardado. La marquesina muestra la lista elegida.');
    }

    /** @return array<string, mixed> */
    private function fila(Organization $o): array
    {
        return [
            'id' => $o->id,
            'nombre' => $o->nombre,
            'logo' => $o->logo_url,
            'activa' => (bool) $o->activo,
        ];
    }
}
