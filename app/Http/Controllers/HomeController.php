<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Edition;
use App\Models\HomeSection;
use App\Models\Organization;
use App\Models\Partner;
use App\Models\ParticipationCard;
use App\Models\Post;
use App\Models\Stat;
use App\Models\Testimonial;
use Illuminate\Support\Collection;

class HomeController extends Controller
{
    public function index()
    {
        return view('public.home', $this->datos());
    }

    /**
     * Los datos del home.
     *
     * Se comparte con la vista previa del panel, que pinta lo mismo con los
     * borradores puestos. Que sea el mismo método no es comodidad: si la vista
     * previa armara sus datos por su cuenta, enseñaría un home *parecido* en
     * vez del home, y una vista previa que miente es peor que no tenerla.
     *
     * @return array<string, mixed>
     */
    public function datos(bool $borrador = false): array
    {
        // Las mismas secciones en los dos casos: una sección apagada sigue
        // apagada en la vista previa. Lo único que cambia es de dónde leen los
        // parciales su texto, y eso lo decide el `$borrador` que va con ellas.
        $secciones = HomeSection::visibles();

        // Cuántas actividades y noticias salen es un ajuste de cada sección,
        // así que hay que leerlo antes de consultarlas.
        $actividades = $secciones->firstWhere('clave', 'actividades') ?? HomeSection::de('actividades');
        $noticias = $secciones->firstWhere('clave', 'noticias') ?? HomeSection::de('noticias');
        $etiquetas = $secciones->firstWhere('clave', 'partners') ?? HomeSection::de('partners');

        $partners = Partner::activos()->ordered()->get()->groupBy('grupo');

        return [
            'secciones' => $secciones,
            'borrador' => $borrador,
            'tarjetas' => ParticipationCard::activos()->ordered()->get(),
            'actividades' => $this->actividades($actividades, $borrador),
            'voces' => Testimonial::activos()->ordered()->get(),
            'cifras' => Stat::activos()->ordered()->get(),
            'ediciones' => Edition::activos()->ordered()->get(),
            'noticias' => Post::published()
                ->latest('published_at')
                ->take($this->entre($noticias->numero('cuantas', $borrador), 1, 12))
                ->get(),
            'grupos' => collect([
                $etiquetas->texto('label_auspician', $borrador) => $partners->get('auspician', collect()),
                $etiquetas->texto('label_participan', $borrador) => $partners->get('participan', collect()),
                $etiquetas->texto('label_colaboran', $borrador) => $partners->get('colaboran', collect()),
                $etiquetas->texto('label_alianzas', $borrador) => $partners->get('alianzas', collect()),
            ]),
            'participantes' => $this->paraLaMarquesina($partners->get('participante', collect())),
            'somosParte' => $partners->get('somos-parte', collect()),
        ];
    }

    /**
     * Quién sale en la marquesina de organizaciones participantes.
     *
     * **La fuente pasa a ser la tabla de organizaciones** (P12). Antes eran las
     * once pastillas de texto del grupo «participante» de `partners`, que
     * alguien había escrito a mano: una lista paralela que nadie actualizaba y
     * que no tenía nada que ver con quién había publicado de verdad.
     *
     * Y ahí estaba el «repite logos» que trajo el cliente. La marquesina pinta
     * su lista DOS VECES —lo necesita: la animación desplaza el carril un 50%
     * y sin la segunda pasada el bucle daría un salto— así que con once
     * elementos la vuelta se ve enseguida y parece que se repiten. No se
     * arregla quitando la segunda pasada, que rompería la animación: se
     * arregla trayendo la lista de verdad, que son todas las organizaciones
     * participantes.
     *
     * Lo que sí era un defecto de datos se corta aquí: **se quitan los nombres
     * repetidos**. En producción hay dos organizaciones llamadas
     * «deltadigital.cl» —de antes de que el wizard lo impidiera— y salían las
     * dos, una detrás de otra, que es repetición de verdad y no la del bucle.
     *
     * Si no hay ninguna organización con actividad publicada se cae a las
     * pastillas de siempre: en una instalación recién sembrada el home no
     * puede quedarse con un hueco.
     *
     * @param  \Illuminate\Support\Collection  $pastillas
     * @return \Illuminate\Support\Collection
     */
    private function paraLaMarquesina($pastillas)
    {
        $organizaciones = Organization::query()
            ->where('activo', true)
            ->whereHas('activities', fn ($q) => $q->where('estado', 'publicada'))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'logo_path'])
            // Por nombre normalizado: «Delta  Digital» y «delta digital» son la
            // misma, y el índice único que lo impediría no existe todavía.
            ->unique(fn (Organization $o) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($o->nombre))))
            ->values();

        return $organizaciones->isNotEmpty() ? $organizaciones : $pastillas;
    }

    /**
     * Las actividades del carrusel, según lo que pida la sección.
     *
     * En «destacadas» se cae a las próximas publicadas cuando no hay ninguna
     * marcada: el home no puede quedarse con un hueco porque nadie se haya
     * acordado de destacar nada.
     *
     * @return Collection<int, Activity>
     */
    private function actividades(HomeSection $seccion, bool $borrador): Collection
    {
        $cuantas = $this->entre($seccion->numero('cuantas', $borrador), 1, 24);

        $base = fn () => Activity::published()->with(['commune', 'region', 'terms'])->ordered()->take($cuantas);

        if ($seccion->texto('seleccion', $borrador) === 'proximas') {
            return $base()->get();
        }

        $destacadas = $base()->featured()->get();

        return $destacadas->isNotEmpty() ? $destacadas : $base()->get();
    }

    /**
     * Un número dentro de sus topes.
     *
     * El panel ya valida el rango al guardar, pero este valor sale de un JSON
     * de la base y acaba en un `take()`: un cero dejaría la sección vacía y un
     * número enorme se traería la tabla entera. Los topes se comprueban donde
     * se usan, no sólo donde se escriben.
     */
    private function entre(int $valor, int $min, int $max): int
    {
        return max($min, min($max, $valor));
    }
}
