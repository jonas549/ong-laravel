<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Edition;
use App\Models\HomeSection;
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
            ]),
            'participantes' => $partners->get('participante', collect()),
        ];
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
