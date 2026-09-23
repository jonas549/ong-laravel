<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\CatalogoAjustes;
use App\Support\Enlace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings.general', [
            'ajustes' => Setting::grupo('general')->ordered()->get(),
            'valores' => Setting::todos(),
        ]);
    }

    public function update(Request $request)
    {
        $ajustes = Setting::grupo('general')->get();

        /*
         * Antes no se validaba nada y se guardaba lo que llegara. Un ajuste
         * enviado como array tumbaba la pantalla con un 500 al convertirlo a
         * texto, y el número de días del recordatorio aceptaba cualquier cosa.
         */
        $reglas = [];
        $nombres = [];

        foreach ($ajustes as $ajuste) {
            /*
             * Los que no son casillas van como `required`. El formulario guarda
             * todos los ajustes del grupo en cada envío, así que un POST al que
             * le faltaran campos los dejaba vacíos de golpe; así no se guarda
             * nada hasta que llegue el formulario entero.
             *
             * Las casillas se quedan en `nullable` porque una desmarcada no
             * viaja en la peticion, y eso significa justamente `false`.
             */
            $reglas[$ajuste->clave] = match (true) {
                $ajuste->tipo === 'bool' => ['nullable', 'boolean'],
                /*
                 * Los de lista cerrada se validan contra la lista, y no como
                 * texto libre. Sin esto, un POST a mano podía dejar guardado un
                 * valor que el `match` del servicio no sabe interpretar: la
                 * encuesta se habría quedado con el comportamiento por defecto
                 * sin que nada lo dijera.
                 */
                CatalogoAjustes::tieneOpciones($ajuste->clave) => [
                    'required',
                    Rule::in(array_keys(CatalogoAjustes::opciones($ajuste->clave))),
                ],
                $ajuste->tipo === 'int' => ['required', 'integer', 'min:0', 'max:365'],
                // Vacío vale: significa «el mensaje de siempre» (MensajeCompartir).
                $ajuste->clave === 'compartir_mensaje' => ['nullable', 'string', 'max:300'],
                str_contains($ajuste->clave, 'email') => ['required', 'email', 'max:255'],
                /*
                 * Q3: los ajustes que son un enlace —hoy solo el del kit de
                 * difusion— se validan como tal y se completan antes, igual
                 * que los del wizard. Se reconocen por el sufijo `_url` de la
                 * clave, que es lo unico que los distingue de un texto.
                 *
                 * `nullable` y no `required`: el propio ajuste dice que vacio
                 * no pinta el boton, asi que tiene que poder vaciarse.
                 */
                str_ends_with($ajuste->clave, '_url') => Enlace::reglas(),
                default => ['required', 'string', 'max:255'],
            };

            $nombres[$ajuste->clave] = mb_strtolower($ajuste->label ?: $ajuste->clave);
        }

        $request->merge(Enlace::normalizarCampos(
            $request->all(),
            collect($ajustes)->pluck('clave')->filter(fn ($c) => str_ends_with($c, '_url'))->all(),
        ));

        $request->validate($reglas, [], $nombres);

        foreach ($ajustes as $ajuste) {
            Setting::set($ajuste->clave, $ajuste->tipo === 'bool'
                ? $request->boolean($ajuste->clave)
                : $request->input($ajuste->clave));
        }

        return back()->with('ok', 'Configuración guardada.');
    }
}
