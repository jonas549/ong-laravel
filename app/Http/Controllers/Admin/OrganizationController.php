<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\Exportador;
use App\Support\CuentaDeAcceso;
use App\Support\Enlace;
use App\Support\Fecha;
use App\Support\Filtro;
use App\Support\Listado;
use App\Support\Papelera;
use App\Support\Texto;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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

        // Las actividades, sólo con su correo: lo pide la columna de correos
        // (punto 2 del 08/10) sin una consulta por fila.
        $consulta = Organization::with(['user', 'activities:id,organization_id,correo_contacto'])
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
            'organizaciones' => Listado::ordenar($consulta, $request, ['id', 'nombre', 'tipo', 'verificada', 'activo', 'created_at'], 'nombre')
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

    /**
     * Sumar una organización al listado (punto 11 del 30/09).
     *
     * Sin cuenta queda exactamente como las del listado importado: libre, sin
     * verificar y activa. Sale en el buscador del wizard y quien la represente
     * la reclama poniéndose su contraseña. Lo que no se le pone aquí —el tipo,
     * por ejemplo— se le pregunta entonces.
     *
     * Desde el punto 1 del 08/10 se le puede crear también su cuenta de acceso
     * en el mismo paso (ver `store()`).
     */
    public function create()
    {
        return view('admin.organizations.create', [
            'organizacion' => new Organization(['activo' => true]),
            'tipos' => Organization::TIPOS,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(Enlace::normalizarCampos($request->all(), ['enlace_web', 'enlace_red_social']));
        $request->merge(['nombre' => preg_replace('/\s+/u', ' ', trim((string) $request->input('nombre')))]);

        /*
         * La cuenta de acceso, opcional (punto 1 del 08/10). Con la casilla
         * marcada se crea en el mismo paso, con las reglas de Panel →
         * Usuarios (`CuentaDeAcceso`), y la organización nace con ella como
         * dueña. Sin marcar, queda como hasta ahora: libre y reclamable.
         */
        $conCuenta = $request->boolean('crear_cuenta');

        $validados = $request->validate([
            // El mismo criterio que el wizard: sin repetir, salvo las borradas.
            'nombre' => ['required', 'string', 'max:255', Rule::unique('organizations', 'nombre')->whereNull('deleted_at')],
            'tipo' => ['nullable', Rule::in(Organization::TIPOS)],
            'tipo_otro' => ['nullable', 'required_if:tipo,Otra', 'string', 'max:255'],
            'unidad_educativa' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'correo_contacto' => ['nullable', 'email', 'max:255'],
            'enlace_web' => Enlace::reglas(),
            'enlace_red_social' => Enlace::reglas(),
            'logo_path' => ['nullable', 'string', 'max:255'],
            'anios_participacion' => ['nullable', 'string', 'max:100'],
        ] + ($conCuenta ? CuentaDeAcceso::reglas() : []), [
            'nombre.unique' => 'Ya hay una organización con ese nombre en el listado. Búscala antes de crear otra.',
        ] + CuentaDeAcceso::mensajes(), [
            'nombre' => 'el nombre',
            'tipo' => 'el tipo',
            'correo_contacto' => 'el correo de contacto',
        ] + CuentaDeAcceso::atributos());

        $cuenta = $conCuenta ? Arr::only($validados, ['name', 'email', 'password']) : null;
        $datos = Arr::except($validados, ['name', 'email', 'password']);

        $organizacion = DB::transaction(function () use ($datos, $cuenta) {
            $usuario = $cuenta ? CuentaDeAcceso::crear($cuenta, User::ROL_ORGANIZER) : null;

            return Organization::create(array_merge($datos, [
                // Sin dueño es lo que la deja reclamable en el wizard.
                'user_id' => $usuario?->id,
                // Como al crearla desde Panel → Usuarios: sin correo de
                // contacto escrito, el de la cuenta.
                'correo_contacto' => ($datos['correo_contacto'] ?? null) ?: $usuario?->email,
                'verificada' => false,
                'activo' => true,
            ]));
        });

        return redirect()
            ->route('admin.organizations.edit', $organizacion)
            ->with('ok', $cuenta
                ? "«{$organizacion->nombre}» creada, con la cuenta de acceso {$cuenta['email']}. Ya puede entrar y publicar."
                : "«{$organizacion->nombre}» ya está en el listado, sin cuenta: quien la represente puede reclamarla al publicar una actividad.");
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
        // Q3: aqui tambien. Un enlace escrito sin `https://` se completa antes
        // de validar, en las tres pantallas donde se puede escribir uno.
        $request->merge(Enlace::normalizarCampos($request->all(), ['enlace_web', 'enlace_red_social']));

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(Organization::TIPOS)],
            'tipo_otro' => ['nullable', 'required_if:tipo,Otra', 'string', 'max:255'],
            'unidad_educativa' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'correo_contacto' => ['nullable', 'email', 'max:255'],
            'enlace_web' => Enlace::reglas(),
            'enlace_red_social' => Enlace::reglas(),
            'logo_path' => ['nullable', 'string', 'max:255'],
            'anios_participacion' => ['nullable', 'string', 'max:100'],
            'requiere_revision' => ['nullable', 'boolean'],
        ], [], [
            'nombre' => 'el nombre',
            'tipo' => 'el tipo',
            'correo_contacto' => 'el correo de contacto',
        ]);

        // La casilla no viaja cuando está desmarcada, así que hay que
        // preguntarle al request y no al array de datos validados.
        $datos['requiere_revision'] = $request->boolean('requiere_revision');

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

        /*
         * Con cuenta, tampoco: la cuenta se quedaría sin organización, que es
         * justo lo que dejaba a un organizador sin poder publicar con su ficha
         * (punto 1 del 30/09). Desactivarla la esconde sin romper nada.
         */
        if ($organization->user_id) {
            return back()->with('error', 'No se puede eliminar «'.$organization->nombre.'»: es la organización de la cuenta '
                .($organization->user?->email ?? 'de un organizador')
                .', que se quedaría sin ella. Desactívala para esconderla del sitio.');
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
                    $o->id,
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
            'ID', 'Nombre', 'Tipo', 'Correo de la cuenta', 'Correo de contacto',
            'Verificada', 'Activa', 'Actividades', 'Alta',
        ], $filas);
    }
}
