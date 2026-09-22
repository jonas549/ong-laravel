<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublishActivityRequest;
use App\Models\AccessLog;
use App\Models\Activity;
use App\Models\Commune;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityCatalogService;
use App\Services\ActivityModerationService;
use App\Services\AprobacionAutomatica;
use App\Services\ControlDeAcceso;
use App\Services\Geocodificador;
use App\Services\CorreoTransaccional;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Support\ArchivosRetenidos;
use App\Support\Filtro;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * El wizard público de cinco pasos.
 *
 * Los pasos se navegan en el cliente con Alpine (no hay una request por
 * paso) y todo se envía junto al final. Las dos rutas llevan el middleware
 * `invitado`: el wizard crea siempre una cuenta nueva, así que a quien ya
 * tiene sesión abierta se le manda a sumar la actividad desde su cuenta. Publicar y crear la cuenta ocurren
 * en la misma transacción, como en el prototipo.
 */
class PublishController extends Controller
{
    public function create()
    {
        abort_unless(Setting::get('publicacion_abierta', true), 403, 'La publicación de actividades está cerrada por ahora.');

        // El admin no publica como organización: no tiene ninguna detrás.
        if (Auth::user()?->esAdmin()) {
            return redirect()
                ->route('admin.dashboard')
                ->with('error', 'Las actividades se publican desde una cuenta de organización.');
        }

        return view('public.publish.wizard', $this->catalogos());
    }

    /**
     * El buscador de organizaciones del paso 3.
     *
     * Devuelve las que coinciden con lo escrito, diciendo de cada una si está
     * libre —importada del listado histórico y sin cuenta todavía— o si ya
     * tiene dueño.
     *
     * **Es público y no filtra nada sensible**: los nombres de las
     * organizaciones participantes se publican en el propio sitio, en la
     * portada y en cada ficha de actividad. Lo único que se añade es si tiene
     * cuenta o no, que es lo que el formulario necesita para saber si ofrecer
     * reclamarla o mandar a iniciar sesión. No salen correos ni nada de quien
     * la administra.
     *
     * Lleva freno en la ruta: es una consulta `LIKE` sin sesión.
     */
    public function organizaciones(Request $request)
    {
        return response()->json([
            'organizaciones' => Organization::buscarPorNombre(Filtro::texto($request, 'q')),
        ]);
    }

    /**
     * Entrar desde el propio wizard, sin salir de él (P14).
     *
     * El encargo venía mal transcrito —decía «cerrar sesión» y era al revés— y
     * lo que pide es que quien ya tiene cuenta pueda identificarse a mitad de
     * rellenar su actividad **sin perder lo escrito**. Por eso esto devuelve
     * JSON y no una redirección: la pantalla no se recarga, el formulario se
     * queda como estaba y sólo cambia el bloque de «crea tu acceso» por el de
     * «esta actividad se sumará a tu cuenta».
     *
     * **Las reglas de acceso son las mismas que las de la puerta de siempre**,
     * y a propósito: mismo `ControlDeAcceso`, mismo bloqueo por intentos,
     * mismo registro. Una segunda puerta con sus propias reglas sería una
     * forma cómoda de saltarse el bloqueo probando contraseñas desde aquí.
     *
     * Sólo entran organizadores. Un administrador no publica como
     * organización —no tiene ninguna detrás— así que se le dice, igual que en
     * `/mi-cuenta/login`.
     */
    public function entrar(Request $request, ControlDeAcceso $acceso)
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], ['email' => 'el correo', 'password' => 'la contraseña']);

        /*
         * El bloqueo lanza su propia excepción de validación, que aquí saldría
         * como un 422 con el mensaje dentro. Se deja pasar tal cual: el
         * mensaje ya explica cuánto falta para poder reintentar.
         */
        $acceso->comprobarBloqueo($request, AccessLog::PANEL_ORGANIZADOR);

        $credenciales = $datos + ['role' => User::ROL_ORGANIZER, 'is_active' => true];

        if (! Auth::attempt($credenciales, $request->boolean('remember'))) {
            $duenno = User::porCredenciales($datos['email'], $datos['password']);

            $motivo = match (true) {
                $duenno === null => 'credenciales',
                $duenno->role !== User::ROL_ORGANIZER => 'rol',
                ! $duenno->is_active => 'inactiva',
                default => 'credenciales',
            };

            $acceso->fallo($request, AccessLog::PANEL_ORGANIZADOR, $motivo, $duenno);

            throw ValidationException::withMessages([
                'email' => match ($motivo) {
                    'rol' => 'Esa es una cuenta de administración, no de organización. Las actividades se publican desde una cuenta de organización.',
                    'inactiva' => 'Esa cuenta está desactivada. Escríbenos si crees que es un error.',
                    default => 'No encontramos una cuenta con ese correo y contraseña.',
                },
            ]);
        }

        $request->session()->regenerate();

        $usuario = Auth::user();
        $usuario->forceFill(['last_login_at' => now()])->save();

        $acceso->exito($request, AccessLog::PANEL_ORGANIZADOR, $usuario);

        $organizacion = $usuario->organization;

        /*
         * Lo que el formulario necesita para rellenarse solo. No va ningún
         * dato que no estuviera ya a la vista de quien acaba de identificarse:
         * es su propia organización.
         */
        return response()->json([
            /*
             * **El token nuevo, y esto no es un extra.**
             *
             * `session()->regenerate()` rota la sesión, y con ella el token
             * CSRF. El formulario que la persona tiene delante se pintó con el
             * token viejo, así que al enviarlo recibía un 419 y perdía todo lo
             * escrito: exactamente lo que este punto venía a evitar. Se
             * devuelve el nuevo para que el formulario se actualice sin
             * recargarse.
             */
            'token' => csrf_token(),
            'correo' => $usuario->email,
            'organizacion' => $organizacion ? [
                'id' => $organizacion->id,
                'nombre' => $organizacion->nombre,
                'tipo' => $organizacion->tipo,
                'tipo_otro' => $organizacion->tipo_otro,
                'num_voluntarios' => $organizacion->num_voluntarios,
                'unidad_educativa' => $organizacion->unidad_educativa,
                'enlace_web' => $organizacion->enlace_web,
                'enlace_red_social' => $organizacion->enlace_red_social,
            ] : null,
            // Lo que decide qué pasos se salta desde ahora (B1/B2).
            'ficha' => $organizacion?->fichaParaElWizard(),
        ]);
    }

    /**
     * Cerrar la sesión sin salir del wizard (B3).
     *
     * Es para quien se encuentra con la sesión de otra persona abierta —un
     * ordenador compartido en la oficina de la organización— o con la cuenta
     * equivocada. Cerrarla desde el menú le llevaba fuera y perdía todo lo que
     * había escrito; por `fetch`, la pantalla se queda como está.
     *
     * Devuelve el token nuevo por lo mismo que `entrar()`: invalidar la sesión
     * lo rota, y el formulario se pintó con el viejo. Sin él, el siguiente
     * envío es un 419 que se lleva lo escrito.
     */
    public function salir(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['token' => csrf_token()]);
    }

    /**
     * Sugerencias de dirección, con su punto en el mapa (P16).
     *
     * **Ayuda, no obliga.** El campo sigue siendo texto libre y el envío no
     * depende de esto: si Photon no responde, `Geocodificador` devuelve una
     * lista vacía y el formulario sigue funcionando igual que antes.
     *
     * La consulta sale del servidor y no del navegador: así quien habla con
     * el geocodificador somos nosotros y no cada visitante escribiendo su
     * dirección letra a letra contra un tercero.
     */
    public function direcciones(Request $request, Geocodificador $geo)
    {
        return response()->json([
            'direcciones' => $geo->sugerencias(Filtro::texto($request, 'q')),
        ]);
    }

    public function store(PublishActivityRequest $request, ActivityModerationService $moderacion)
    {
        abort_unless(Setting::get('publicacion_abierta', true), 403);

        $datos = $request->validated();

        /*
         * Con la sesión abierta el paso «crea tu acceso» no se pinta, así que
         * el correo de la cuenta no viaja en el POST: sale del usuario.
         */
        $conSesion = $request->user();
        $correoCuenta = $datos['email'] ?? $conSesion?->email;

        // El paso 4 ofrece reusar el correo de la cuenta como contacto público.
        $correoPublico = $request->boolean('usar_correo_cuenta')
            ? $correoCuenta
            : ($datos['correo_contacto'] ?? $correoCuenta);

        try {
            $actividad = DB::transaction(function () use ($datos, $request, $correoPublico, $conSesion) {
                $campos = [
                    'nombre' => $datos['org_nombre'],
                    'tipo' => $datos['org_tipo'],
                    'tipo_otro' => $datos['org_tipo_otro'] ?? null,
                    'descripcion' => $datos['org_descripcion'] ?? null,
                    'num_voluntarios' => $datos['org_num_voluntarios'] ?? null,
                    'unidad_educativa' => $datos['org_unidad_educativa'] ?? null,
                    'correo_contacto' => $correoPublico,
                    'enlace_web' => $datos['enlace_web'] ?? null,
                    'enlace_red_social' => $datos['enlace_red_social'] ?? null,
                ];

                /*
                 * El logo puede venir de dos sitios: del formulario, o
                 * retenido de un rebote anterior. Lo segundo es el punto 4 de
                 * la tanda del 11/09 — ver `ArchivosRetenidos` para el porque.
                 */
                if ($logo = $request->file('org_logo')) {
                    $campos['logo_path'] = 'storage/'.$logo->store('organizaciones', 'public');
                } elseif ($retenido = ArchivosRetenidos::rutaAbsoluta('org_logo')) {
                    $campos['logo_path'] = 'storage/'.Storage::disk('public')
                        ->putFile('organizaciones', new File($retenido));
                }

                if ($conSesion) {
                    /*
                     * Ya tiene cuenta y organización: se reusan. El usuario es
                     * `hasOne` de organización, así que crear otra dejaría dos
                     * colgando del mismo dueño y la ficha saldría de la
                     * equivocada. Los campos van rellenos desde el formulario y
                     * son editables, así que lo que venga se guarda; el logo
                     * sólo se pisa si subió uno nuevo.
                     */
                    $organizacion = $conSesion->organization;
                    $organizacion->fill($campos)->save();
                } else {
                    $usuario = User::create([
                        'name' => $datos['org_nombre'],
                        'email' => $datos['email'],
                        'password' => $datos['password'],
                        'role' => User::ROL_ORGANIZER,
                        'is_active' => true,
                    ]);

                    /*
                     * P10. Si eligió una organización del listado histórico, se
                     * le pone dueño en vez de crear otra igual. Es lo que evita
                     * el duplicado: hasta aquí, quien encontraba su nombre ya
                     * registrado no tenía más salida que escribir una variante.
                     *
                     * `reclamada()` la relee de la base y exige que siga libre,
                     * así que un `org_id` cambiado a mano no se lleva una
                     * organización ajena.
                     *
                     * De los campos del formulario sólo se aplican los que la
                     * persona sí ha podido rellenar: el nombre, el tipo y el
                     * logo son los de la ONG y no se pisan con lo que venga,
                     * que para eso se le han dejado de pedir.
                     */
                    if ($reclamada = $request->reclamada()) {
                        $organizacion = $reclamada;

                        $organizacion->fill([
                            'user_id' => $usuario->id,
                            'correo_contacto' => $campos['correo_contacto'],
                            'enlace_web' => $campos['enlace_web'],
                            'enlace_red_social' => $campos['enlace_red_social'],
                            'num_voluntarios' => $campos['num_voluntarios'],
                            'unidad_educativa' => $campos['unidad_educativa'],
                        ])->fill(
                            /*
                             * Salvo el tipo, si el listado no lo traía. El
                             * del cliente llega casi entero sin tipo, y el
                             * que se eligió en el paso 2 es el único que hay:
                             * descartarlo la dejaba sin tipo para siempre.
                             */
                            blank($reclamada->tipo)
                                ? ['tipo' => $campos['tipo'], 'tipo_otro' => $campos['tipo_otro']]
                                : []
                        )->save();
                    } else {
                        $organizacion = Organization::create($campos + [
                            'user_id' => $usuario->id,
                            'logo_path' => $campos['logo_path'] ?? null,
                        ]);
                    }
                }

                $comuna = Commune::find($datos['commune_id'] ?? null);

                // Misma historia que el logo: formulario, o lo retenido.
                $portadaGuardada = null;

                if ($portada = $request->file('imagen')) {
                    $portadaGuardada = 'storage/'.$portada->store('actividades', 'public');
                } elseif ($retenida = ArchivosRetenidos::rutaAbsoluta('imagen')) {
                    $portadaGuardada = 'storage/'.Storage::disk('public')
                        ->putFile('actividades', new File($retenida));
                }

                $actividad = Activity::create([
                    'organization_id' => $organizacion->id,
                    'titulo' => $datos['titulo'],
                    'descripcion' => $datos['descripcion'],
                    'formato' => $datos['formato'],
                    'fecha_inicio' => $datos['fecha_inicio'] ?? null,
                    'hora_inicio' => $datos['hora_inicio'] ?? null,
                    'hora_termino' => $datos['hora_termino'] ?? null,
                    'sin_fecha_definida' => $request->boolean('sin_fecha_definida'),
                    'region_id' => $comuna?->region_id ?? ($datos['region_id'] ?? null),
                    'commune_id' => $comuna?->id,
                    'direccion' => $datos['direccion'] ?? null,
                    // El punto de la sugerencia elegida (P16). Nulo si escribió
                    // la dirección a mano, que sigue estando permitido.
                    'latitud' => $datos['latitud'] ?? null,
                    'longitud' => $datos['longitud'] ?? null,
                    'participantes_estimados' => $datos['participantes_estimados'] ?? null,
                    'cupos_totales' => $datos['cupos_totales'] ?? null,
                    'cupos_disponibles' => $datos['cupos_totales'] ?? null,
                    // El paso 4 no lo pregunta: si pide inscripción, es abierta.
                    'abierta_publico' => true,
                    'inscripcion_habilitada' => $request->boolean('inscripcion_habilitada'),
                    'tiene_accesibilidad' => $request->boolean('tiene_accesibilidad'),
                    'accesibilidad_detalle' => $request->boolean('tiene_accesibilidad')
                        ? ($datos['accesibilidad_detalle'] ?? null)
                        : null,
                    'publico_otro' => $datos['publico_otro'] ?? null,
                    'imagen_portada' => $portadaGuardada,
                    'correo_contacto' => $correoPublico,
                    // Los dos enlaces van arriba, en `$campos`: son de la
                    // organización. Ver 2025_01_12_000001.
                    'estado' => 'borrador',
                ]);

                $terminos = collect($datos['temas'] ?? [])
                    ->merge($datos['publicos'] ?? [])
                    ->merge($datos['caracteristicas'] ?? [])
                    ->filter()
                    ->unique();

                $actividad->terms()->sync($terminos);

                foreach (array_filter($datos['colaboradores'] ?? []) as $i => $nombre) {
                    $actividad->collaborators()->create(['nombre' => $nombre, 'orden' => $i]);
                }

                return $actividad;
            });
        } catch (UniqueConstraintViolationException) {
            // Doble clic en "Enviar actividad", o el mismo correo enviado a la
            // vez por el wizard y por el registro. La transacción revierte, así
            // que no queda basura, pero antes se perdían los cinco pasos
            // rellenados detrás de un 500 con la excepción de MySQL en pantalla.
            throw ValidationException::withMessages([
                'email' => 'Ya existe una cuenta con ese correo. Entra con ella y suma la actividad desde tu cuenta.',
            ]);
        }

        /*
         * Fuera de la transacción: si el correo falla, la actividad ya existe.
         *
         * A quien publica por primera vez le toca revisión siempre, porque su
         * organización se acaba de crear y no tiene nada publicado; de la
         * segunda en adelante, sale directa. Ver `AprobacionAutomatica`.
         */
        [$estado, $motivo] = app(AprobacionAutomatica::class)
            ->estadoAlEnviar($actividad->organization);

        $moderacion->cambiar($actividad, $estado, null, $motivo, automatica: $estado === 'publicada');

        // D1: la guía para organizadores, por los dos caminos —revisión y
        // aprobación automática— y en el momento. Un fallo aquí no toca la
        // actividad: `CorreoTransaccional` lo apunta y sigue.
        app(CorreoTransaccional::class)->guiaOrganizador($actividad);

        /*
         * Quien ya tenía la sesión abierta no estrena cuenta: ni se le vuelve a
         * iniciar sesión —regenerar la sesión aquí le cambiaría la que ya tiene
         * sin motivo—, ni se le manda la bienvenida, ni se dispara `Registered`,
         * que le pediría verificar un correo verificado hace meses.
         */
        if (! $conSesion) {
            $nuevoUsuario = $actividad->organization->user;

            // La cuenta se acaba de crear con la contraseña que eligió aquí
            // mismo: dejarla fuera obligaba a volver a escribirla para ver su
            // actividad, y el enlace del correo de verificación rebotaba al
            // login.
            Auth::login($nuevoUsuario);
            $request->session()->regenerate();

            $nuevoUsuario->forceFill(['last_login_at' => now()])->save();
            app(ControlDeAcceso::class)->exito($request, AccessLog::PANEL_ORGANIZADOR, $nuevoUsuario);

            app(CorreoTransaccional::class)->bienvenida($nuevoUsuario);

            // La cuenta nace aquí, así que aquí sale también el correo de
            // verificación. No bloquea nada: es para confirmar la dirección.
            event(new Registered($nuevoUsuario));
        }

        // Ya están guardados donde tenían que estar: lo retenido sobra, y
        // dejarlo colgando de la sesión ocuparía disco para siempre.
        ArchivosRetenidos::limpiar();

        return redirect()
            ->route('publish.done', $actividad)
            ->with('ok', 'Recibimos tu actividad. Te avisaremos por correo cuando esté revisada.');
    }

    /**
     * El paso 5, «tu actividad fue enviada».
     *
     * La ruta es pública porque el wizard llega aquí recién creada la cuenta,
     * pero la pantalla es del dueño: enseña el nombre de la organización, el
     * título, la fecha y el lugar de una ficha que todavía está en revisión.
     * Sin esta línea bastaba con acertar el slug —que sale del título— para
     * leer actividades que nadie ha publicado aún.
     *
     * `update` es el permiso que ya significa «esta ficha es tuya»; y 404 en
     * vez de 403 para no confirmarle a nadie que esa dirección existe.
     */
    public function done(Activity $activity)
    {
        abort_unless(Gate::allows('update', $activity), 404);

        $activity->load(['organization', 'commune']);

        return view('public.publish.done', compact('activity'));
    }

    /**
     * Los mismos catálogos que usa la edicion en "Mi cuenta", más el tipo de
     * organización, que sólo se pregunta al publicar por primera vez.
     *
     * @return array<string, mixed>
     */
    /**
     * La organización libre que se estuviera reclamando, si la hay.
     *
     * Se vuelve a leer de la base y no de lo que llega en el POST: el id viene
     * del navegador, así que decidir con lo que él diga permitiría reclamar una
     * organización que ya tiene dueño sólo con cambiar el número.
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

    private function catalogos(): array
    {
        return app(ActivityCatalogService::class)->todos()
            + [
                'tiposOrg' => Organization::TIPOS,
                // Con sesión abierta, el paso 3 sale relleno con lo que ya hay
                // guardado y el paso de «crea tu acceso» no se pinta.
                'organizacion' => Auth::user()?->organization,
                /*
                 * La organización que se estuviera reclamando al rebotar el
                 * formulario. Sin esto, un error de validación le devuelve la
                 * pantalla pidiéndole otra vez el logo y el tipo, que es justo
                 * lo que P10 quita.
                 */
                'organizacionElegida' => $this->organizacionReclamada(),
                /*
                 * C4 y B1/B2: los pasos que se salta quien ya tiene ficha.
                 *
                 * La ficha va entera al navegador, que es quien decide: el
                 * tipo se puede cambiar en el paso 2, se puede cambiar de
                 * cuenta a mitad (B3) y en el teléfono el logo no se pide
                 * (B6). Lo de aquí abajo es sólo el estado de partida, para
                 * que la barra no parpadee al cargar.
                 *
                 * Sin sesión no hay ficha y se pregunta todo.
                 */
                'ficha' => Auth::user()?->organization?->fichaParaElWizard(),
                'saltarPaso2' => filled(Auth::user()?->organization?->tipo),
                'saltarPaso3' => ($org = Auth::user()?->organization) !== null && $org->faltanEnElPaso3() === [],
            ];
    }
}
