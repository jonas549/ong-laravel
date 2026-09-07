<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\CodigoQr;
use Illuminate\Support\Facades\Gate;

/**
 * La descarga del QR de una actividad, para imprimirlo.
 *
 * Un solo controlador para las dos puertas —la cuenta del organizador y el
 * panel del administrador—, porque es la misma descarga. Quién puede lo decide
 * `ActivityPolicy::qr()`, no el prefijo de la ruta.
 *
 * Las dos salidas no son un lujo:
 *
 * - **PNG** para pegar en un documento, un correo o una plantilla de Canva.
 * - **SVG** para imprimir. Es vectorial, así que el mismo archivo vale para una
 *   pegatina de 5 cm y para un pendón de dos metros sin que se vea un solo
 *   borde dentado. Es la respuesta de verdad a «buena resolución».
 */
class QrController extends Controller
{
    public function png(Activity $activity, CodigoQr $qr)
    {
        Gate::authorize('qr', $activity);

        return response($qr->png($activity), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$qr->nombreArchivo($activity, 'png').'"',
            // Que no se quede pegado en la caché del navegador si algún día
            // cambia la dirección que codifica.
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function svg(Activity $activity, CodigoQr $qr)
    {
        Gate::authorize('qr', $activity);

        return response($qr->svg($activity), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$qr->nombreArchivo($activity, 'svg').'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
