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
        foreach (Setting::grupo('general')->get() as $ajuste) {
            $valor = $ajuste->tipo === 'bool'
                ? $request->boolean($ajuste->clave)
                : $request->input($ajuste->clave);

            Setting::set($ajuste->clave, $valor);
        }

        return back()->with('ok', 'Configuración guardada.');
    }
}
