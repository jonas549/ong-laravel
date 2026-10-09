<?php

namespace App\Models;

use App\Support\Filtro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    /** Los cinco tipos del paso 2 del wizard. */
    public const TIPOS = [
        'Organización sin fines de lucro',
        'Empresa o institución privada',
        'Institución educativa',
        'Municipalidad u organismo público',
        'Otra',
    ];

    protected $fillable = [
        'user_id', 'nombre', 'slug', 'tipo', 'tipo_otro', 'descripcion', 'logo_path',
        'num_voluntarios', 'unidad_educativa', 'correo_contacto', 'enlace_web',
        'enlace_red_social', 'anios_participacion', 'marquesina_orden', 'verificada', 'activo', 'requiere_revision',
    ];

    protected function casts(): array
    {
        return [
            'verificada' => 'boolean',
            'activo' => 'boolean',
            'requiere_revision' => 'boolean',
            'num_voluntarios' => 'integer',
            'marquesina_orden' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $org) {
            if (blank($org->slug)) {
                $org->slug = static::slugUnico($org->nombre);
            }
        });

        /*
         * Red de seguridad de las varias cuentas por organización: quien
         * escriba `user_id` a mano —un camino viejo, un escenario de prueba,
         * el importador— deja una principal que no es de la organización, y
         * esa cuenta entraría sin organización. Si la cuenta todavía no tiene
         * ninguna, se le pone ésta. Si ya tiene otra no se toca: decidir a
         * cuál pertenece no es cosa de un efecto secundario. El camino
         * bueno sigue siendo `enlazarCuenta()`.
         */
        static::saved(function (self $org) {
            if ($org->user_id && ($org->wasRecentlyCreated || $org->wasChanged('user_id'))) {
                User::whereKey($org->user_id)
                    ->whereNull('organization_id')
                    ->update(['organization_id' => $org->id, 'organizacion_desde' => now()]);
            }
        });
    }

    /**
     * Sin reclamar: está en el listado pero todavía no tiene cuenta.
     *
     * Es lo que deja el importador del listado histórico. La primera persona
     * que la elija en el wizard y ponga su contraseña se queda con ella.
     */
    public function scopeSinReclamar(Builder $q): Builder
    {
        return $q->whereNull('user_id');
    }

    public function estaSinReclamar(): bool
    {
        return $this->user_id === null;
    }

    /**
     * El interruptor de Configuración → General: «Permitir que una
     * organización tenga más de una cuenta».
     *
     * Apagado por defecto, y apagado es exactamente lo de antes: quien elige
     * una organización que ya tiene cuenta no puede quedársela. Encendido,
     * se suma a ella. Apagarlo no saca a nadie: sólo deja de admitir nuevas.
     */
    public static function admiteVariasCuentas(): bool
    {
        return (bool) Setting::get('organizacion_varias_cuentas', false);
    }

    /**
     * Si una cuenta nueva puede quedarse con esta organización: reclamándola,
     * si está libre, o sumándose, si ya tiene cuenta y el interruptor está
     * encendido.
     */
    public function admiteCuentaNueva(): bool
    {
        return $this->activo && ($this->estaSinReclamar() || static::admiteVariasCuentas());
    }

    /**
     * Las organizaciones a las que se puede llegar con una cuenta nueva.
     * Es `admiteCuentaNueva()` en forma de consulta.
     */
    public function scopeAdmitenCuentaNueva(Builder $q): Builder
    {
        $q->where('activo', true);

        return static::admiteVariasCuentas() ? $q : $q->whereNull('user_id');
    }

    /**
     * La organización que se había elegido en el buscador, al volver de un
     * rebote del formulario, con la misma forma que una sugerencia.
     *
     * Se relee de la base y no de lo que llegó en el POST: el id viene del
     * navegador, y decidir con lo que él diga permitiría elegir una que ya no
     * admite cuentas sólo con cambiar el número. La usan el wizard y el
     * registro, que antes tenían cada uno su copia.
     *
     * @return array<string, mixed>|null
     */
    public static function elegidaAlRebotar(mixed $id): ?array
    {
        $id = (int) $id;
        $org = $id > 0 ? static::admitenCuentaNueva()->find($id) : null;

        return $org ? [
            'id' => $org->id,
            'nombre' => $org->nombre,
            'tipo' => $org->tipo,
            'tipo_otro' => $org->tipo_otro,
            'libre' => $org->estaSinReclamar(),
            'sumable' => ! $org->estaSinReclamar(),
        ] : null;
    }

    /**
     * Enlaza una cuenta a esta organización.
     *
     * **Es el único sitio que escribe `users.organization_id`**, y decide a
     * la vez la cuenta principal: la primera que llega a una organización sin
     * principal se queda como tal; las siguientes sólo se suman. Así no hay
     * dos caminos que puedan dejar una cuenta dentro sin principal, o una
     * principal que no es de la organización.
     *
     * Devuelve true si la cuenta se sumó a una organización que ya tenía
     * principal, que es cuando hay que avisarle.
     */
    public function enlazarCuenta(User $usuario): bool
    {
        $usuario->forceFill([
            'organization_id' => $this->id,
            'organizacion_desde' => now(),
        ])->save();

        $usuario->setRelation('organization', $this);

        if ($this->user_id === null) {
            $this->forceFill(['user_id' => $usuario->id])->save();
            $this->setRelation('user', $usuario);

            return false;
        }

        return $this->user_id !== $usuario->id;
    }

    /**
     * Si la principal sigue pudiendo serlo: existe, está activa y sigue en la
     * organización. Si no, el aviso de quien se suma va al equipo de la ONG y
     * el panel pide elegir otra.
     */
    public function principalActiva(): ?User
    {
        $principal = $this->user;

        return $principal && $principal->is_active && $principal->organization_id === $this->id
            ? $principal
            : null;
    }

    /**
     * Busca organizaciones por nombre para el autocompletado del wizard.
     *
     * Devuelve las dos clases y las distingue, en vez de esconder las que ya
     * tienen cuenta: quien escribe el nombre de su organización y no la ve
     * vuelve a crearla con una variante del nombre, que es exactamente cómo
     * aparecieron los duplicados que hay hoy en producción. Verla y que le
     * digan «ésta ya tiene cuenta, inicia sesión» le lleva a donde tiene que ir.
     *
     * `propia` marca la de quien pregunta, para no decirle a la dueña que esa
     * organización «ya tiene cuenta, inicia sesión» con su sesión abierta.
     *
     * `sumable` marca las que ya tienen cuenta pero admiten otra, con el
     * interruptor de varias cuentas encendido: elegirla no manda a iniciar
     * sesión, suma a quien la elige.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function buscarPorNombre(string $texto, ?User $usuario = null, int $tope = 8)
    {
        $varias = static::admiteVariasCuentas();
        $propiaId = $usuario?->organization_id;

        $texto = trim($texto);

        if (mb_strlen($texto) < 2) {
            return collect();
        }

        // `Filtro::like` es el que ya usa el resto del panel para esto.
        $como = '%'.Filtro::like($texto).'%';

        return static::query()
            ->where('activo', true)
            ->where('nombre', 'like', $como)
            /*
             * Las que empiezan por lo escrito, primero. Sin esto, escribir
             * «del» ofrece antes «Fundación Aldea del Sur» que «Delta», que es
             * lo que casi seguro se estaba buscando.
             */
            ->orderByRaw('CASE WHEN nombre LIKE ? THEN 0 ELSE 1 END', [Filtro::like($texto).'%'])
            ->orderBy('nombre')
            ->limit($tope)
            ->get(['id', 'nombre', 'tipo', 'tipo_otro', 'user_id'])
            ->map(fn (self $o) => [
                'id' => $o->id,
                'nombre' => $o->nombre,
                'tipo' => $o->tipo,
                'tipo_otro' => $o->tipo_otro,
                'libre' => $o->estaSinReclamar(),
                'sumable' => $varias && ! $o->estaSinReclamar(),
                'propia' => $propiaId !== null && $o->id === $propiaId,
            ]);
    }

    /**
     * Lo que el wizard necesita saber de la ficha para decidir qué pasos se
     * salta (C4 y B1/B2 de la sexta tanda).
     *
     * **La decisión la toma el navegador y no el servidor**, y por eso aquí
     * sólo van hechos, no la conclusión: el tipo se puede cambiar en el paso 2
     * sin recargar, se puede cambiar de cuenta a mitad (B3), y en el teléfono
     * el logo no se pide (B6). Con la conclusión cocinada aquí, cualquiera de
     * las tres dejaba el wizard pidiendo lo que no debía. La lógica vive en
     * `faltaEnElPaso3()` de resources/js/wizard.js, y el servidor sólo la
     * repite para pintar el estado de partida sin parpadeo
     * (`faltanEnElPaso3()`).
     *
     * `soloLectura` va encendido para quien no es la cuenta principal: no
     * puede cambiar la ficha, así que no hay nada que preguntarle en los pasos
     * 2 y 3, falte lo que falte. Lo que falte lo completa la principal.
     *
     * @return array{nombre: bool, tipo: ?string, tipo_otro: bool, unidad: bool, logo: bool, soloLectura: bool}
     */
    public function fichaParaElWizard(?User $quien = null): array
    {
        return [
            'nombre' => filled($this->nombre),
            'tipo' => filled($this->tipo) ? $this->tipo : null,
            'tipo_otro' => filled($this->tipo_otro),
            'unidad' => filled($this->unidad_educativa),
            'logo' => filled($this->logo_path),
            'soloLectura' => $quien !== null && ! $quien->editaLaFicha(),
        ];
    }

    /**
     * Qué habría que preguntarle en el paso 3, con el tipo que tiene.
     *
     * **El tipo no está en la lista**: se pregunta en el paso 2, que es su
     * sitio. Estaba, y era B2: una organización del listado histórico que se
     * reclamaba desde `/mi-cuenta/registro` se quedaba sin tipo, así que el
     * paso 3 se pintaba —porque faltaba algo— sin ningún campo que enseñar,
     * porque el tipo no se pide ahí. Salía casi vacío, con «Tu cuenta» y nada
     * más.
     *
     * **El logo sólo cuenta si el tipo no es «Otra»** (B5), y en el teléfono
     * no cuenta nunca (B6), cosa que sólo sabe el navegador. Cuando cuenta es
     * por decisión del cliente: una organización sin logo sale con sus
     * iniciales, y el paso 3 es donde se le puede pedir sin interrumpirla.
     *
     * Los dos condicionales son los mismos que exige el formulario. Si algún
     * día se añade otro campo obligatorio al paso 3, va aquí y en el wizard —
     * si no, el wizard lo saltaría dando por completo lo que no lo está.
     *
     * @return array<int, string> las claves de los campos que faltan
     */
    public function faltanEnElPaso3(?string $tipo = null, ?User $quien = null): array
    {
        // Quien no es la principal no edita la ficha: nada que preguntarle.
        if ($quien !== null && ! $quien->editaLaFicha()) {
            return [];
        }

        $tipo ??= $this->tipo;
        $faltan = [];

        if (blank($this->nombre)) {
            $faltan[] = 'org_nombre';
        }

        if ($tipo === 'Otra' && blank($this->tipo_otro)) {
            $faltan[] = 'org_tipo_otro';
        }

        if ($tipo === 'Institución educativa' && blank($this->unidad_educativa)) {
            $faltan[] = 'org_unidad_educativa';
        }

        if ($tipo !== 'Otra' && blank($this->logo_path)) {
            $faltan[] = 'org_logo';
        }

        return $faltan;
    }

    public static function slugUnico(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'organizacion';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * Las que se ven. Una organizacion apagada deja de salir en los listados
     * publicos, pero no se lleva por delante sus actividades ni las
     * inscripciones de esas actividades: por eso se apaga en vez de borrarse.
     */
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /**
     * **La cuenta principal** (`organizations.user_id`).
     *
     * Antes era «la cuenta» a secas, la única que podía tener. Con varias
     * cuentas por organización es la que la creó o la reclamó —o la que haya
     * elegido después el administrador—: recibe el aviso cuando alguien se
     * suma y es la única que edita la ficha. Quien se suma nunca pasa a ser
     * principal por su cuenta.
     *
     * Las cuentas de la organización, todas, son `cuentas()`.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Todas las cuentas de la organización, la principal incluida. */
    public function cuentas(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Los correos de contacto que se han usado con esta organización, sin el
     * de la cuenta de acceso: el de su ficha y los de cada una de sus
     * actividades, sin repetir (sin distinguir mayúsculas). Punto 2 del 08/10.
     *
     * Deja ver si detrás hay más personas que las que tienen cuenta. Usa las
     * actividades ya cargadas si vienen, para no consultar fila a fila en el
     * listado.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function correosDeContacto()
    {
        $actividades = $this->relationLoaded('activities')
            ? $this->activities
            : $this->activities()->get(['id', 'organization_id', 'correo_contacto']);

        // Sin los de las cuentas: ésos ya salen como cuentas.
        $cuentas = collect([$this->user?->email])
            ->merge($this->cuentas->pluck('email'))
            ->filter()
            ->map(fn ($c) => mb_strtolower($c));

        return collect([$this->correo_contacto])
            ->merge($actividades->pluck('correo_contacto'))
            ->map(fn ($c) => trim((string) $c))
            ->filter()
            ->unique(fn ($c) => mb_strtolower($c))
            ->reject(fn ($c) => $cuentas->contains(mb_strtolower($c)))
            ->values();
    }

    /**
     * Las inscripciones de todas sus actividades.
     *
     * Existe para poder decir en pantalla que se llevaria por delante un
     * borrado: «7 actividades y 24 inscripciones» se entiende, y «no se puede
     * eliminar» a secas, no.
     */
    public function registrations(): HasManyThrough
    {
        return $this->hasManyThrough(Registration::class, Activity::class);
    }

    /** El logo listo para pintar, o null si esta organización no subió ninguno. */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset($this->logo_path) : null;
    }

    /**
     * Las iniciales, para la ficha pública cuando no hay logo.
     *
     * No todas las organizaciones suben uno —hoy ninguna de las tres de local—
     * y dejar el hueco vacío se lee como una imagen rota. Dos letras dan algo
     * reconocible sin inventar un logo que no existe.
     *
     * Las palabras de enlace no cuentan: «Fundación de la Casa» da «FC» y no
     * «FD», que no diría nada.
     */
    public function getInicialesAttribute(): string
    {
        $vacias = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'e', 'para', 'por', 'en', 'a'];

        $palabras = preg_split('/\s+/u', trim((string) $this->nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $utiles = array_values(array_filter(
            $palabras,
            fn ($p) => ! in_array(mb_strtolower($p), $vacias, true),
        ));

        $fuente = $utiles ?: $palabras;

        $letras = mb_substr($fuente[0] ?? '', 0, 1)
            .(count($fuente) > 1 ? mb_substr($fuente[1], 0, 1) : '');

        return mb_strtoupper($letras) ?: '·';
    }

    /** El nombre del tipo, resolviendo el campo libre cuando es "Otra". */
    public function getTipoLabelAttribute(): string
    {
        return $this->tipo === 'Otra' && filled($this->tipo_otro)
            ? $this->tipo_otro
            : (string) $this->tipo;
    }

    public function esEmpresa(): bool
    {
        return $this->tipo === 'Empresa o institución privada';
    }

    public function esEducativa(): bool
    {
        return $this->tipo === 'Institución educativa';
    }
}
