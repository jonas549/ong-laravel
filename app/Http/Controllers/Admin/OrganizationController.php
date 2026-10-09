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

        // Tanda del 09/10: por la fecha en que se creó (la columna «Creada»).
        // Sólo fechas bien formadas: lo demás se ignora en vez de filtrar mal.
        $fecha = fn (string $campo) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v = Filtro::texto($request, $campo)) ? $v : '';
        $desde = $fecha('desde');
        $hasta = $fecha('hasta');

        // Las actividades, sólo con su correo: lo pide la columna de correos
        // (punto 2 del 08/10) sin una consulta por fila.
        $consulta = Organization::with(['user', 'cuentas:id,organization_id,email', 'activities:id,organization_id,correo_contacto'])
            ->withCount(['activities', 'cuentas', 'activities as publicadas_count' => fn ($q) => $q->where('estado', 'publicada')])
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
            ->when($estado !== '', fn ($q) => $q->where('activo', $estado === 'si'))
            ->when($desde, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($hasta, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));

        $consulta = Papelera::aplicar($consulta, $request);

        return view('admin.organizations.index', [
            'organizaciones' => Listado::ordenar($consulta, $request, ['id', 'nombre', 'tipo', 'verificada', 'activo', 'created_at'], 'nombre')
                ->paginate(Listado::porPagina($request))
                ->withQueryString(),
            'soloPendientes' => $soloPendientes,
            'soloActivas' => Filtro::texto($request, 'filtro') === 'activas',
            'verEliminados' => Papelera::incluyeEliminados($request),
            'desde' => $desde,
            'hasta' => $hasta,
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

            $organizacion = Organization::create(array_merge($datos, [
                // Como al crearla desde Panel → Usuarios: sin correo de
                // contacto escrito, el de la cuenta.
                'correo_contacto' => ($datos['correo_contacto'] ?? null) ?: $usuario?->email,
                'verificada' => false,
                'activo' => true,
            ]));

            // Sin cuenta, sin principal: es lo que la deja reclamable en el
            // wizard. Con cuenta, ésa queda como su principal.
            $usuario && $organizacion->enlazarCuenta($usuario);

            return $organizacion;
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
            'organizacion' => $organization->loadCount(['activities', 'registrations'])
                ->load(['cuentas' => fn ($q) => $q->withCount('activities')->orderBy('organizacion_desde')]),
            'tipos' => Organization::TIPOS,
        ]);
    }

    /**
     * Elegir la cuenta principal entre las de la organización.
     *
     * La principal es la que recibe el aviso cuando alguien se suma y la única
     * que edita la ficha. Nunca cambia sola: si se borra o se desactiva, la
     * organización sigue funcionando y la ficha avisa de que hay que elegir
     * otra aquí.
     */
    public function hacerPrincipal(Organization $organization, User $user)
    {
        abort_unless($user->organization_id === $organization->id, 404);

        if (! $user->is_active) {
            return back()->with('error', 'Esa cuenta está desactivada: no podría leer los avisos ni editar la ficha. Actívala antes o elige otra.');
        }

        $organization->forceFill(['user_id' => $user->id])->save();

        return back()->with('ok', "{$user->email} es ahora la cuenta principal de «{$organization->nombre}».");
    }

    /**
     * Crear la cuenta de acceso de una organización que no tiene ninguna,
     * desde su ficha (tanda del 09/10).
     *
     * Hasta aquí sólo se podía al crear la organización, y de las importadas
     * casi ninguna tiene cuenta. Mismos campos y reglas que en «Nueva
     * organización» y Panel → Usuarios (`CuentaDeAcceso`), y queda como su
     * cuenta principal.
     *
     * Sólo si no tiene ninguna: con cuentas, la siguiente se asigna desde
     * Panel → Usuarios, que es donde se ve a quién se está sumando.
     */
    public function crearCuenta(Request $request, Organization $organization)
    {
        if ($organization->cuentas()->exists()) {
            return back()->with('error', 'Esta organización ya tiene cuenta. Para sumarle otra, créala en Usuarios y elige esta organización.');
        }

        $cuenta = $request->validate(CuentaDeAcceso::reglas(), CuentaDeAcceso::mensajes(), CuentaDeAcceso::atributos());

        DB::transaction(function () use ($organization, $cuenta) {
            $usuario = CuentaDeAcceso::crear($cuenta, User::ROL_ORGANIZER);

            /*
             * Una principal que ya no está en la organización (borrada o
             * sacada) no cuenta: sin esto, `enlazarCuenta()` la daría por
             * principal y la cuenta nueva quedaría como sumada a nadie.
             */
            if ($organization->user_id !== null) {
                $organization->forceFill(['user_id' => null])->save();
            }

            $organization->enlazarCuenta($usuario);

            // Como al crearla: sin correo de contacto escrito, el de la cuenta.
            if (blank($organization->correo_contacto)) {
                $organization->forceFill(['correo_contacto' => $usuario->email])->save();
            }
        });

        return redirect()
            ->route('admin.organizations.edit', $organization)
            ->with('ok', "Cuenta creada: {$cuenta['email']} ya puede entrar y publicar por «{$organization->nombre}».");
    }

    /**
     * Sacar una cuenta de la organización.
     *
     * La cuenta no se borra: se queda sin organización, como las que crea el
     * panel antes de asignarles una, y puede volver a enlazarse desde Panel →
     * Usuarios. Sus actividades se quedan en la organización —son de ella— y
     * pasan a verlas la cuenta principal (`Activity::responsable()`), que es
     * también quien recibe desde ahora sus correos. No se reescribe ninguna
     * fila: si la cuenta vuelve, recupera las suyas.
     *
     * La principal no se saca así: primero se elige otra. Si no, la
     * organización se quedaría con cuentas y sin nadie que edite su ficha.
     */
    public function quitarCuenta(Organization $organization, User $user)
    {
        abort_unless($user->organization_id === $organization->id, 404);

        if ($organization->user_id === $user->id) {
            return back()->with('error', 'No se puede sacar a la cuenta principal. Elige antes otra cuenta como principal.');
        }

        $user->forceFill(['organization_id' => null, 'organizacion_desde' => null])->save();

        return back()->with('ok', "{$user->email} ya no es de «{$organization->nombre}». Sus actividades pasan a la cuenta principal.");
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
        $cuentas = $organization->cuentas()->count();

        if ($organization->user_id || $cuentas > 0) {
            return back()->with('error', 'No se puede eliminar «'.$organization->nombre.'»: '
                .($cuentas > 1
                    ? 'tiene '.$cuentas.' cuentas, que se quedarían sin ella.'
                    : 'es la organización de la cuenta '.($organization->user?->email ?? 'de un organizador').', que se quedaría sin ella.')
                .' Desactívala para esconderla del sitio.');
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
            $consulta = Organization::with('user')->withCount(['activities', 'cuentas'])->orderBy('nombre');

            foreach ($consulta->cursor() as $o) {
                yield [
                    $o->id,
                    $o->nombre,
                    $o->tipo_label,
                    $o->user?->email,
                    $o->cuentas_count,
                    $o->correo_contacto,
                    $o->verificada ? 'Sí' : 'No',
                    $o->activo ? 'Sí' : 'No',
                    $o->activities_count,
                    Fecha::iso($o->created_at),
                ];
            }
        })();

        return $exportador->descargar($formato, 'Organizaciones', [
            'ID', 'Nombre', 'Tipo', 'Correo de la cuenta principal', 'Cuentas', 'Correo de contacto',
            'Verificada', 'Activa', 'Actividades', 'Alta',
        ], $filas);
    }
}
