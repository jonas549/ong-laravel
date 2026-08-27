<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HomeController;
use App\Models\HomeSection;
use App\Models\HomeSectionVersion;
use App\Services\SanitizadorHtml;
use App\Support\CatalogoHome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * El editor de contenido del home.
 *
 * Una pantalla por sección y no un formulario gigante: son doce secciones con
 * más de sesenta campos entre todas, y un solo formulario obligaría a guardar
 * el home entero para cambiar una coma. Además, un envío incompleto lo vaciaría
 * de golpe: eso pasó de verdad en Configuración y hubo que restaurar a mano la
 * contraseña de SMTP.
 *
 * Lo publicado y el borrador son dos columnas distintas, así que escribir en el
 * panel no toca el sitio hasta que alguien pulsa Publicar.
 */
class HomeSectionController extends Controller
{
    public function __construct(private SanitizadorHtml $limpiador) {}

    /* --------------------------------------------------------------- lista */

    public function index()
    {
        HomeSection::sembrarLasQueFalten();

        return view('admin.home.index', [
            'secciones' => HomeSection::ordenadas(),
        ]);
    }

    /* -------------------------------------------------------------- editor */

    public function edit(string $seccion)
    {
        abort_unless(CatalogoHome::existe($seccion), 404);

        HomeSection::sembrarLasQueFalten();

        $fila = HomeSection::query()->where('clave', $seccion)->firstOrFail();

        return view('admin.home.editar', [
            'seccion' => $fila,
            'meta' => CatalogoHome::seccion($seccion),
            'campos' => CatalogoHome::campos($seccion),
            'versiones' => $fila->versions()->with('user')->take(20)->get(),
        ]);
    }

    /* ------------------------------------------------------------ publicar */

    public function update(Request $request, string $seccion)
    {
        abort_unless(CatalogoHome::existe($seccion), 404);

        $fila = HomeSection::query()->where('clave', $seccion)->firstOrFail();
        $contenido = $this->validarYLimpiar($request, $seccion);

        DB::transaction(function () use ($fila, $contenido, $request) {
            /*
             * La versión se guarda ANTES de pisar lo publicado, y guarda lo que
             * ya estaba. El historial tiene que ser «cómo estaba el sitio», que
             * es lo que hace falta para volver atrás; si guardara lo nuevo, la
             * primera fila del historial sería siempre igual a lo que ya se ve.
             */
            if (filled($fila->contenido)) {
                $this->guardarVersion($fila, $fila->contenido, $request, 'Reemplazada por una edición');
            }

            $fila->update([
                'contenido' => $contenido,
                'borrador' => null,
                'borrador_at' => null,
                'borrador_por' => null,
            ]);
        });

        HomeSection::olvidarCache();

        return redirect()
            ->route('admin.home.editar', $seccion)
            ->with('ok', 'Publicado. Ya se ve en el sitio.');
    }

    /* -------------------------------------------------------- autoguardado */

    /**
     * Guarda el borrador sin publicarlo. Lo llama el editor cada pocos
     * segundos, así que responde JSON y no redirige.
     */
    public function borrador(Request $request, string $seccion)
    {
        abort_unless(CatalogoHome::existe($seccion), 404);

        $fila = HomeSection::query()->where('clave', $seccion)->firstOrFail();
        $contenido = $this->validarYLimpiar($request, $seccion, borrador: true);

        $fila->update([
            'borrador' => $contenido,
            'borrador_at' => now(),
            'borrador_por' => $request->user()->id,
        ]);

        return response()->json([
            'guardado' => true,
            'cuando' => $fila->borrador_at->locale('es')->isoFormat('HH:mm'),
        ]);
    }

    public function descartarBorrador(string $seccion)
    {
        abort_unless(CatalogoHome::existe($seccion), 404);

        HomeSection::query()->where('clave', $seccion)->firstOrFail()->update([
            'borrador' => null,
            'borrador_at' => null,
            'borrador_por' => null,
        ]);

        return back()->with('ok', 'Borrador descartado. Vuelve a verse lo que está publicado.');
    }

    /* ----------------------------------------------------------- encendido */

    public function alternar(string $seccion)
    {
        abort_unless(CatalogoHome::existe($seccion), 404);

        // Las dos primeras van ancladas: «¿Cómo participar?» se monta 96 px
        // sobre el hero, así que apagar cualquiera de las dos deja un agujero
        // en la portada en vez de una sección menos.
        abort_if(CatalogoHome::esFija($seccion), 403, 'Esa sección no se puede apagar: el diseño de la portada la da por hecha.');

        $fila = HomeSection::query()->where('clave', $seccion)->firstOrFail();
        $fila->update(['activo' => ! $fila->activo]);

        HomeSection::olvidarCache();

        return back()->with('ok', $fila->activo
            ? "«{$fila->tituloAdmin()}» vuelve a verse en el home."
            : "«{$fila->tituloAdmin()}» ya no se ve en el home.");
    }

    /* ----------------------------------------------------------- reordenar */

    public function reordenar(Request $request)
    {
        $datos = $request->validate([
            'orden' => ['required', 'array', 'min:1'],
            'orden.*' => ['required', 'string', Rule::in(CatalogoHome::orden())],
        ]);

        $claves = array_values(array_unique($datos['orden']));

        /*
         * Las fijas se reponen en su sitio pase lo que pase por la petición.
         * El editor no las deja arrastrar, pero esto no es el editor: es un
         * POST, y quien tenga sesión de admin puede mandar la lista que quiera.
         * La regla vive donde se guarda, no donde se dibuja.
         */
        $fijas = array_values(array_filter(CatalogoHome::orden(), fn ($c) => CatalogoHome::esFija($c)));
        $orden = array_merge($fijas, array_values(array_diff($claves, $fijas)));

        // Y las que no vinieran en la petición se van al final, en el orden del
        // catálogo, para que ninguna se quede sin número.
        foreach (CatalogoHome::orden() as $clave) {
            if (! in_array($clave, $orden, true)) {
                $orden[] = $clave;
            }
        }

        DB::transaction(function () use ($orden) {
            foreach ($orden as $posicion => $clave) {
                HomeSection::query()->where('clave', $clave)->update(['orden' => $posicion + 1]);
            }
        });

        HomeSection::olvidarCache();

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'orden' => $orden])
            : back()->with('ok', 'Orden guardado.');
    }

    /* ----------------------------------------------------------- versiones */

    /**
     * Restaurar publica una copia de la versión elegida.
     *
     * No borra nada de lo que vino después: antes de publicarla, lo que estaba
     * se guarda como una versión más. Un historial del que se pueda borrar no
     * sirve para lo único que sirve un historial.
     */
    public function restaurar(Request $request, string $seccion, HomeSectionVersion $version)
    {
        abort_unless(CatalogoHome::existe($seccion), 404);

        $fila = HomeSection::query()->where('clave', $seccion)->firstOrFail();
        abort_unless($version->home_section_id === $fila->id, 404);

        $cuando = $version->created_at->locale('es')->isoFormat('D MMM YYYY, HH:mm');

        DB::transaction(function () use ($fila, $version, $request, $cuando) {
            if (filled($fila->contenido)) {
                $this->guardarVersion($fila, $fila->contenido, $request, "Reemplazada al restaurar la versión del {$cuando}");
            }

            $fila->update([
                'contenido' => $version->contenido,
                'borrador' => null,
                'borrador_at' => null,
                'borrador_por' => null,
            ]);
        });

        HomeSection::olvidarCache();

        return redirect()
            ->route('admin.home.editar', $seccion)
            ->with('ok', "Restaurada la versión del {$cuando}. La anterior quedó guardada en el historial.");
    }

    /* -------------------------------------------------------- vista previa */

    /**
     * El home entero con los borradores puestos.
     *
     * Es el home de verdad —mismo controlador de datos, mismos parciales, mismo
     * CSS— y no una maqueta parecida: una vista previa que se arma por su
     * cuenta enseña otra cosa, y entonces no sirve para decidir si publicar.
     */
    public function vistaPrevia(HomeController $home)
    {
        return view('public.home', $home->datos(borrador: true) + ['vistaPrevia' => true]);
    }

    /* ------------------------------------------------------------- privado */

    /**
     * Valida los campos de la sección y devuelve el contenido ya limpio.
     *
     * **Todo pasa por aquí, publicar y autoguardar.** El autoguardado tiene la
     * validación relajada —se escribe a media frase y no puede saltar un error
     * cada dos segundos— pero limpia igual: lo que llega a la base ya está
     * saneado, así que un borrador nunca guarda algo que no se podría publicar.
     *
     * @return array<string, mixed>
     */
    private function validarYLimpiar(Request $request, string $seccion, bool $borrador = false): array
    {
        $campos = CatalogoHome::campos($seccion);
        $reglas = [];
        $nombres = [];

        foreach ($campos as $clave => $campo) {
            $reglas[$clave] = match ($campo['tipo']) {
                // Los campos ricos van sin tope de longitud aquí: el tope real
                // lo pone el sanitizador de Symfony (20.000 caracteres) y
                // repetirlo sería tener dos números que mantener de acuerdo.
                'rico' => ['nullable', 'string'],
                'numero' => [$borrador ? 'nullable' : 'required', 'integer', 'min:'.($campo['min'] ?? 0), 'max:'.($campo['max'] ?? 9999)],
                'opciones' => ['nullable', Rule::in(array_keys($campo['opciones'] ?? []))],
                'parrafo' => ['nullable', 'string', 'max:2000'],
                default => ['nullable', 'string', 'max:500'],
            };

            $nombres[$clave] = mb_strtolower($campo['label']);
        }

        $datos = $request->validate($reglas, [], $nombres);
        $limpio = [];

        foreach ($campos as $clave => $campo) {
            $valor = $datos[$clave] ?? null;

            $limpio[$clave] = match ($campo['tipo']) {
                'rico' => $this->limpiador->limpiar($valor),
                'youtube' => $this->limpiador->idDeYoutube($valor),
                'enlace' => $this->limpiador->enlace($valor),
                'imagen' => $this->limpiador->rutaImagen($valor),
                'numero' => $valor === null ? null : (int) $valor,
                // Los campos de una línea se guardan como texto plano: si
                // alguien pega HTML aquí se verá el HTML, no se ejecutará,
                // porque Blade los escapa al pintarlos.
                default => is_string($valor) ? trim($valor) : $valor,
            };
        }

        return $limpio;
    }

    private function guardarVersion(HomeSection $fila, array $contenido, Request $request, string $nota): void
    {
        $fila->versions()->create([
            'user_id' => $request->user()?->id,
            'autor' => $request->user()?->name,
            'contenido' => $contenido,
            'nota' => $nota,
        ]);
    }
}
