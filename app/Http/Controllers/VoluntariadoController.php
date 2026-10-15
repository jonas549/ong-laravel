<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Region;
use App\Models\Voluntariado;
use App\Support\CalendarioMes;
use App\Support\Filtro;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * /voluntariados: las oportunidades de Voluntariados Chile (13/10).
 *
 * Lee de la tabla `voluntariados`, que rellena la sincronización cada hora;
 * una visita nunca llama a su API. Son contenido de ellos y no se mezclan con
 * las actividades del DPS: ni en las consultas ni en los catálogos.
 */
class VoluntariadoController extends Controller
{
    /**
     * El listado y su vista de calendario, como `/actividades` (15/10): las
     * dos comparten la consulta ya filtrada, y el calendario sólo la acota al
     * mes (ver `App\Support\CalendarioMes`).
     *
     * La barra de filtros es la de actividades: siempre visible, con todas las
     * regiones y los tres formatos aunque no haya voluntariados en cada uno.
     * Sólo lleva los filtros que respalda un campo de la API: búsqueda (título
     * y organización), región (`location.regions`, traducida por
     * `Equivalencias`) y formato (`format`). Sin comuna —llega como texto sin
     * código, y vacía en los híbridos y online— y sin tema: su categoría es su
     * catálogo y se enseña tal cual en la tarjeta, sin filtro.
     */
    public function index(Request $request)
    {
        $vista = Filtro::texto($request, 'vista') === 'calendario' ? 'calendario' : 'lista';

        $filtradas = Voluntariado::publicos()
            ->porRegion($request->integer('region') ?: null)
            ->porFormato(Filtro::texto($request, 'formato') ?: null)
            ->porTexto(mb_substr(Filtro::texto($request, 'q'), 0, 100));

        $comunes = [
            'vista' => $vista,
            'regiones' => Region::activas()->ordered()->get(),
            'formatos' => Activity::FORMATOS,
        ];

        if ($vista === 'calendario') {
            return $this->sinIndexarSiEstaApagada(response()->view('public.voluntariados.index', $comunes + [
                'calendario' => CalendarioMes::montar($filtradas, Filtro::texto($request, 'mes') ?: null),
                'sinFecha' => CalendarioMes::sinFecha($filtradas),
            ]));
        }

        return $this->sinIndexarSiEstaApagada(response()->view('public.voluntariados.index', $comunes + [
            'voluntariados' => $filtradas->porFecha()->paginate(12)->withQueryString(),
        ]));
    }

    public function show(Voluntariado $voluntariado)
    {
        // Una que ya no llega o que cerró da 404, como una actividad sin
        // publicar: la dirección deja de existir para el público.
        abort_unless(Voluntariado::publicos()->whereKey($voluntariado->id)->exists(), 404);

        $relacionados = Voluntariado::publicos()
            ->whereKeyNot($voluntariado->id)
            ->when($voluntariado->region_ids, fn ($q, $ids) => $q->porRegion($ids[0]))
            ->porFecha()->take(3)->get();

        return $this->sinIndexarSiEstaApagada(response()->view('public.voluntariados.show', [
            'vol' => $voluntariado,
            'relacionados' => $relacionados,
        ]));
    }

    /**
     * Con la sección apagada, además del <meta robots> de la vista, la
     * cabecera X-Robots-Tag: la respetan los buscadores aunque no lean el
     * HTML. Ver `Voluntariado::seccionVisible()`.
     */
    private function sinIndexarSiEstaApagada(Response $respuesta): Response
    {
        if (! Voluntariado::seccionVisible()) {
            $respuesta->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $respuesta;
    }
}
