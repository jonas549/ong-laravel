<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Services\ControlDeAcceso;
use App\Support\Filtro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Log de accesos.
 *
 * Sirve para dos cosas distintas: ver quién entró y detectar que alguien está
 * probando contraseñas. Por eso el filtro por defecto no es "todo" sino que
 * se muestran los fallidos arriba en el resumen, que es lo que se mira.
 */
class AccessLogController extends Controller
{
    public function index(Request $request, ControlDeAcceso $control)
    {
        $filtros = [
            'resultado' => Filtro::texto($request, 'resultado'),
            'panel' => Filtro::texto($request, 'panel'),
            'q' => Filtro::texto($request, 'q'),
            'desde' => Filtro::texto($request, 'desde'),
            'hasta' => Filtro::texto($request, 'hasta'),
        ];

        $accesos = AccessLog::query()
            ->with(['user:id,name,email,role', 'actor:id,name'])
            ->when($filtros['resultado'] === 'fallidos', fn ($q) => $q->fallidos())
            ->when(
                $filtros['resultado'] && $filtros['resultado'] !== 'fallidos',
                fn ($q, $_) => $q->where('resultado', $filtros['resultado'])
            )
            ->when($filtros['panel'], fn ($q, $p) => $q->where('panel', $p))
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($filtros['q'], function ($q, $b) {
                $b = Filtro::like($b);

                $q->where(function ($w) use ($b) {
                    $w->where('email', 'like', "%{$b}%")->orWhere('ip', 'like', "%{$b}%");
                });
            })
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        $desde24h = now()->subDay();

        // Correos que ahora mismo están bloqueados: se sacan de los fallos
        // recientes y se le pregunta al propio servicio, que es quien manda.
        $sospechosos = AccessLog::fallidos()
            ->where('created_at', '>=', $desde24h)
            ->selectRaw('email, panel, ip, COUNT(*) as intentos, MAX(created_at) as ultimo')
            ->whereNotNull('email')
            ->groupBy('email', 'panel', 'ip')
            ->havingRaw('COUNT(*) >= ?', [3])
            ->orderByDesc('intentos')
            ->limit(10)
            ->get()
            ->map(function ($fila) use ($control) {
                $fila->bloqueado = $control->segundosRestantes($fila->panel, $fila->email, $fila->ip);
                $fila->acumulados = $control->fallosAcumulados($fila->panel, $fila->email, $fila->ip);

                return $fila;
            });

        return view('admin.accesos.index', [
            'accesos' => $accesos,
            'filtros' => $filtros,
            'resultados' => AccessLog::RESULTADOS,
            'exitos24h' => AccessLog::where('resultado', 'exito')->where('created_at', '>=', $desde24h)->count(),
            'fallos24h' => AccessLog::fallidos()->where('created_at', '>=', $desde24h)->count(),
            'sospechosos' => $sospechosos,
            // La regla que se aplica de verdad, leída del ajuste. Sin esto no
            // había forma de saber desde el panel con cuántos intentos bloquea:
            // sólo cabía suponerlo.
            'intentos' => $control->intentos(),
            'minutosBloqueo' => (int) round($control->bloqueo() / 60),
        ]);
    }

    /** Levanta el bloqueo de un correo, cuando es la propia persona la que se quedó fuera. */
    public function desbloquear(Request $request, ControlDeAcceso $control)
    {
        $datos = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'panel' => ['required', Rule::in([AccessLog::PANEL_ADMIN, AccessLog::PANEL_ORGANIZADOR])],
            'ip' => ['nullable', 'ip'],
        ], [], ['email' => 'el correo', 'panel' => 'el panel', 'ip' => 'la IP']);

        // Se mira antes de levantarlo: si no, la pantalla confirmaba haber
        // levantado bloqueos que no existían.
        $habiaBloqueo = $control->segundosRestantes($datos['panel'], $datos['email'], $datos['ip'] ?? null) > 0;

        $control->liberar($datos['panel'], $datos['email'], $datos['ip'] ?? null, $request->user());

        // Queda constancia de quién lo levantó: es una acción del panel que
        // afecta a la seguridad de una cuenta ajena.
        Log::info('Se levantó un bloqueo de acceso', [
            'admin_id' => $request->user()->id,
            'admin_email' => $request->user()->email,
            'objetivo' => $datos['email'],
            'panel' => $datos['panel'],
            'ip' => $datos['ip'] ?? null,
            'habia_bloqueo' => $habiaBloqueo,
        ]);

        return back()->with('ok', $habiaBloqueo
            ? "Se levantó el bloqueo de {$datos['email']}. Ya puede volver a intentarlo."
            : "{$datos['email']} no tenía ningún bloqueo activo desde esa IP.");
    }
}
