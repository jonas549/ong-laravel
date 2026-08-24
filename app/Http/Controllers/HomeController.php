<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Edition;
use App\Models\Partner;
use App\Models\ParticipationCard;
use App\Models\Post;
use App\Models\Stat;
use App\Models\Testimonial;

class HomeController extends Controller
{
    public function index()
    {
        $actividades = Activity::published()
            ->featured()
            ->with(['commune', 'region', 'terms'])
            ->ordered()
            ->take(9)
            ->get();

        // Si todavía no hay destacadas, mostramos las publicadas más próximas
        // para que el home nunca se vea vacío.
        if ($actividades->isEmpty()) {
            $actividades = Activity::published()
                ->with(['commune', 'region', 'terms'])
                ->ordered()
                ->take(9)
                ->get();
        }

        $partners = Partner::activos()->ordered()->get()->groupBy('grupo');

        return view('public.home', [
            'tarjetas' => ParticipationCard::activos()->ordered()->get(),
            'actividades' => $actividades,
            'voces' => Testimonial::activos()->ordered()->get(),
            'cifras' => Stat::activos()->ordered()->get(),
            'ediciones' => Edition::activos()->ordered()->get(),
            'noticias' => Post::published()->latest('published_at')->take(3)->get(),
            'grupos' => collect([
                'Auspician' => $partners->get('auspician', collect()),
                'Participan' => $partners->get('participan', collect()),
                'Colaboran' => $partners->get('colaboran', collect()),
            ]),
            'participantes' => $partners->get('participante', collect()),
        ]);
    }
}
