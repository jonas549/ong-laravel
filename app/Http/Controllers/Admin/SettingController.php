<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

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
                $ajuste->tipo === 'int' => ['required', 'integer', 'min:0', 'max:365'],
                str_contains($ajuste->clave, 'email') => ['required', 'email', 'max:255'],
                default => ['required', 'string', 'max:255'],
            };

            $nombres[$ajuste->clave] = mb_strtolower($ajuste->label ?: $ajuste->clave);
        }

        $request->validate($reglas, [], $nombres);

        foreach ($ajustes as $ajuste) {
            Setting::set($ajuste->clave, $ajuste->tipo === 'bool'
                ? $request->boolean($ajuste->clave)
                : $request->input($ajuste->clave));
        }

        return back()->with('ok', 'Configuración guardada.');
    }
}
