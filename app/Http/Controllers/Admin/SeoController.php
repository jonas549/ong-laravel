<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Ajustes de SEO.
 *
 * Los valores viven en `settings`, en su propio grupo, para que se editen igual
 * que el resto de la configuración y no haya que tocar el .env.
 */
class SeoController extends Controller
{
    public function edit()
    {
        return view('admin.settings.seo', [
            'ajustes' => Setting::grupo('seo')->ordered()->get(),
            'valores' => Setting::todos(),
        ]);
    }

    public function update(Request $request)
    {
        $ajustes = Setting::grupo('seo')->get();

        $reglas = [];
        $nombres = [];

        foreach ($ajustes as $ajuste) {
            $reglas[$ajuste->clave] = match (true) {
                $ajuste->tipo === 'bool' => ['nullable', 'boolean'],
                $ajuste->clave === 'seo_descripcion' => ['required', 'string', 'max:320'],
                str_contains($ajuste->clave, 'imagen') => ['nullable', 'string', 'max:255'],
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

        return back()->with('ok', 'Ajustes de SEO guardados.');
    }
}
