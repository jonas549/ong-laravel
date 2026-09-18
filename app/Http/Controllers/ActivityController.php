<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Commune;
use App\Models\Region;
use App\Models\TaxonomyTerm;
use App\Services\Calendario;
use App\Support\CalendarioMes;
use App\Support\Filtro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ActivityController extends Controller
{
    /**
     * El listado público y su vista de calendario.
     *
     * Son dos maneras de mirar lo mismo, así que **comparten la consulta**: los
     * filtros de región, comuna, tema y formato se aplican una sola vez, aquí,
     * y de ahí salen las dos. Ni el calendario tiene su propia lista de filtros
     * ni hay que acordarse de replicar uno nuevo en dos sitios.
     *
     * Lo que cambia de una a otra es sólo el final: el listado pagina de doce
     * en doce, y el calendario acota al mes que se esté mirando. Eso vive en
     * `App\Support\CalendarioMes`.
     */
    public function index(Request $request)
    {
        $vista = Filtro::texto($request, 'vista') === 'calendario' ? 'calendario' : 'lista';

        $filtradas = Activity::published()
            ->byRegion($request->integer('region') ?: null)
            ->byCommune($request->integer('comuna') ?: null)
            ->byFormato(Filtro::texto($request, 'formato') ?: null)
            ->byTerm($request->integer('tema') ?: null);

        $comunes = [
            'vista' => $vista,
            'regiones' => Region::activas()->ordered()->get(),
            'comunas' => $request->integer('region')
                ? Commune::activas()->where('region_id', $request->integer('region'))->orderBy('nombre')->get()
                : collect(),
            'temas' => TaxonomyTerm::grupo('tema')->activos()->ordered()->get(),
            'formatos' => Activity::FORMATOS,
        ];

        if ($vista === 'calendario') {
            return view('public.activities.index', $comunes + [
                'calendario' => CalendarioMes::montar($filtradas, Filtro::texto($request, 'mes') ?: null),
                'sinFecha' => CalendarioMes::sinFecha($filtradas),
            ]);
        }

        return view('public.activities.index', $comunes + [
            'actividades' => $filtradas
                ->with(['commune', 'region', 'terms', 'organization'])
                ->ordered()
                ->paginate(12)
                ->withQueryString(),
        ]);
    }

    public function show(Request $request, Activity $activity)
    {
        // La ficha es pública sólo si está publicada; su organización puede
        // verla igual, que es lo que hace el botón "Vista previa" del editor.
        //
        // 404 y no 403: un 403 confirmaría que esa dirección existe, y una ficha
        // sin publicar no debería ni asomar para quien no es de la casa.
        abort_unless(Gate::allows('view', $activity), 404);

        /*
         * Una sola direccion buena por ficha.
         *
         * Manda la ID, asi que `/activity/5/lo-que-sea` encuentra la actividad
         * igual; pero se responde con una redireccion permanente a la direccion
         * con el slug de ahora, para que no haya diez direcciones distintas
         * sirviendo el mismo contenido. Eso es lo que hace que un enlace ya
         * enviado siga valiendo despues de que la ONG cambie el titulo.
         */
        $canonica = route('activities.show', $activity);

        if ($request->fullUrl() !== $canonica && $request->url() !== $canonica) {
            return redirect($canonica, 301);
        }

        $activity->load(['organization', 'region', 'commune', 'terms', 'collaborators']);

        $relacionadas = Activity::published()
            ->where('id', '!=', $activity->id)
            ->when($activity->region_id, fn ($q) => $q->where('region_id', $activity->region_id))
            ->with(['commune', 'region', 'terms'])
            ->take(3)
            ->get();

        return view('public.activities.show', compact('activity', 'relacionadas'));
    }

    /**
     * La direccion de antes, `/actividades/{slug}`.
     *
     * Sigue viva y redirige permanente a la nueva. No es cortesia: esa
     * direccion esta dentro de correos ya enviados —el de inscripcion, el de
     * recordatorio, el de actividad publicada— y en lo que la gente haya
     * compartido por WhatsApp. Romperla convertiria todo eso en un 404.
     *
     * Un 301 y no un 302 para que los buscadores trasladen la pagina en vez de
     * quedarse con las dos.
     */
    public function showLegacy(Activity $activity)
    {
        abort_unless(Gate::allows('view', $activity), 404);

        return redirect(route('activities.show', $activity), 301);
    }

    /**
     * El «añadir a mi calendario» de los correos: un archivo .ics.
     *
     * Mismo permiso que la ficha, y por el mismo motivo: si la actividad no
     * está publicada, esta dirección tampoco puede confirmar que existe.
     */
    public function calendario(Activity $activity, Calendario $calendario)
    {
        abort_unless(Gate::allows('view', $activity), 404);

        // Sin fecha no hay nada que agendar. Un .ics vacío sería peor que un
        // 404: el calendario lo aceptaría sin rechistar y no aparecería nada.
        abort_unless($calendario->agendable($activity), 404);

        $activity->load(['organization', 'commune', 'region']);

        return response($calendario->ics($activity), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$calendario->nombreArchivo($activity).'"',
        ]);
    }
}
