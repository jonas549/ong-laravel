<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Exportador;
use App\Support\Fecha;
use App\Support\Filtro;
use App\Support\Listado;
use App\Support\Papelera;
use App\Support\Texto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Organizaciones.
 *
 * **Eliminar sólo cuando no arrastra nada.** La clave foránea de las actividades
 * es `cascadeOnDelete`, así que borrar una organización se lleva sus actividades
 * y, con ellas, las inscripciones de todas esas actividades: puede ser mucha
 * gente apuntada desapareciendo con un clic, y sin forma de deshacerlo desde el
 * panel.
 *
 * Así que el botón de eliminar sólo aparece si la organización no tiene ni una
 * actividad. Si las tiene, la pantalla dice cuántas y qué hacer antes. Para todo
 * lo demás está desactivar, que la esconde del sitio sin tocar nada.
 *
 * La comprobación se repite en el servidor: el botón que no está es el dibujo,
 * la regla vive donde se ejecuta la acción.
 */
class OrganizationController extends Controller
{
    public function index(Request $request, bool $soloPendientes = false)
    {
        $estado = Filtro::texto($request, 'estado');

        $consulta = Organization::with('user')
            ->withCount(['activities', 'activities as publicadas_count' => fn ($q) => $q->where('estado', 'publicada')])
            ->when(Filtro::texto($request, 'q'), fn ($q, $b) => $q->where('nombre', 'like', '%'.Filtro::like($b).'%'))
            ->when($soloPendientes, fn ($q) => $q->where('verificada', false))
            /*
             * «Activas» es la definición del KPI de la portada: con al menos una
             * actividad publicada. La tarjeta decía 1 y llevaba a un listado de
             * 3 filas, que es peor que no enlazar nada.
             */
            ->when(
                Filtro::texto($request, 'filtro') === 'activas',
                fn ($q) => $q->whereHas('activities', fn ($a) => $a->where('estado', 'publicada')),
            )
            ->when($estado !== '', fn ($q) => $q->where('activo', $estado === 'si'));

        $consulta = Papelera::aplicar($consulta, $request);

        return view('admin.organizations.index', [
            'organizaciones' => Listado::ordenar($consulta, $request, ['nombre', 'tipo', 'verificada', 'activo', 'created_at'], 'nombre')
                ->paginate(Listado::porPagina($request))
                ->withQueryString(),
            'soloPendientes' => $soloPendientes,
            'soloActivas' => Filtro::texto($request, 'filtro') === 'activas',
            'verEliminados' => Papelera::incluyeEliminados($request),
            'pendientes' => Organization::where('verificada', false)->count(),
        ]);
    }

    /** Las que esperan verificación, que es lo que se revisa a diario. */
    public function verificacion(Request $request)
    {
        return $this->index($request, true);
    }

    public function edit(Organization $organization)
    {
        return view('admin.organizations.edit', [
            'organizacion' => $organization->loadCount(['activities', 'registrations']),
            'tipos' => Organization::TIPOS,
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(Organization::TIPOS)],
            'tipo_otro' => ['nullable', 'required_if:tipo,Otra', 'string', 'max:255'],
            'unidad_educativa' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'correo_contacto' => ['nullable', 'email', 'max:255'],
            'enlace_web' => ['nullable', 'url', 'max:255'],
            'enlace_red_social' => ['nullable', 'url', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255'],
        ], [], [
            'nombre' => 'el nombre',
            'tipo' => 'el tipo',
            'correo_contacto' => 'el correo de contacto',
        ]);

        $organization->update($datos);

        return redirect()
            ->route('admin.organizations.edit', $organization)
            ->with('ok', 'Datos actualizados.');
    }

    public function toggleVerified(Organization $organization)
    {
        $organization->update(['verificada' => ! $organization->verificada]);

        return back()->with('ok', $organization->verificada
            ? "«{$organization->nombre}» queda verificada."
            : "«{$organization->nombre}» vuelve a estar sin verificar.");
    }

    /**
     * La esconde del sitio sin tocar sus actividades.
     *
     * Es lo que hay que usar casi siempre: una organización que ya no participa
     * deja de verse, y sus actividades y las inscripciones de esas actividades
     * siguen donde estaban.
     */
    public function alternar(Organization $organization)
    {
        $organization->update(['activo' => ! $organization->activo]);

        return back()->with('ok', $organization->activo
            ? "«{$organization->nombre}» vuelve a estar activa."
            : "«{$organization->nombre}» queda desactivada. Sus actividades e inscripciones no cambian.");
    }

    /**
     * Eliminar, sólo si no arrastra nada.
     *
     * La comprobación está aquí y no sólo en la vista: el botón escondido es el
     * dibujo, y un POST no pasa por el dibujo.
     */
    public function destroy(Organization $organization)
    {
        $actividades = $organization->activities()->count();

        if ($actividades > 0) {
            return back()->with('error', 'No se puede eliminar «'.$organization->nombre.'»: tiene '
                .Texto::cuantos($actividades, 'actividad')
                .'. Elimínalas primero o desactiva la organización, que la esconde sin borrar nada.');
        }

        $organization->delete();

        return back()->with('ok', "«{$organization->nombre}» eliminada. Se puede recuperar con el filtro de la papelera.");
    }

    public function restaurar(int $id)
    {
        $organizacion = Organization::withTrashed()->findOrFail($id);
        $organizacion->restore();

        return back()->with('ok', "«{$organizacion->nombre}» restaurada.");
    }

    public function exportar(Request $request, Exportador $exportador)
    {
        $formato = Filtro::texto($request, 'formato') === 'csv' ? 'csv' : 'xlsx';

        $filas = (function () {
            $consulta = Organization::with('user')->withCount('activities')->orderBy('nombre');

            foreach ($consulta->cursor() as $o) {
                yield [
                    $o->nombre,
                    $o->tipo_label,
                    $o->user?->email,
                    $o->correo_contacto,
                    $o->verificada ? 'Sí' : 'No',
                    $o->activo ? 'Sí' : 'No',
                    $o->activities_count,
                    Fecha::iso($o->created_at),
                ];
            }
        })();

        return $exportador->descargar($formato, 'Organizaciones', [
            'Nombre', 'Tipo', 'Correo de la cuenta', 'Correo de contacto',
            'Verificada', 'Activa', 'Actividades', 'Alta',
        ], $filas);
    }
}
