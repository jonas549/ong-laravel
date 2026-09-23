<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Support\DatosDifusion;

/**
 * La imagen de difusión de una actividad: la pieza de 1080×1350 para
 * Instagram, armada con la plantilla de la ONG y los datos de la actividad.
 *
 * Se dibuja en el navegador, con canvas (ver `resources/js/difusion.js`): no
 * depende de nada instalado en el servidor ni de servicios de fuera, y usa
 * las mismas fuentes que el sitio. Aquí sólo se decide quién puede verla y se
 * le pasan los textos ya resueltos.
 *
 * Sólo de actividades publicadas: una en revisión todavía puede cambiar o no
 * salir, y la imagen invita a una ficha que aún no existe.
 */
class DifusionController extends Controller
{
    public function show(Activity $activity)
    {
        $this->authorize('update', $activity);

        if ($activity->estado !== 'publicada') {
            return redirect()->route('account.activities.index')
                ->with('error', 'La imagen de difusión está disponible cuando la actividad se publica.');
        }

        return view('account.activities.difusion', [
            'activity' => $activity,
            'datos' => DatosDifusion::de($activity),
        ]);
    }
}
