<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * El logo de la organización, desde «Mi perfil» (B6 de la sexta tanda).
 *
 * El wizard dejó de exigirlo en el teléfono y a las de tipo «Otra», así que
 * hacía falta un sitio donde subirlo después. No lo había: el logo sólo se
 * podía cambiar publicando otra actividad.
 *
 * Sólo toca la organización de quien tiene la sesión: no recibe ningún id, así
 * que no hay número que cambiar en la URL para llegar a la de otro.
 */
class OrganizacionLogoController extends Controller
{
    public function update(Request $request)
    {
        $organizacion = $request->user()->organization;

        abort_unless($organizacion, 404);

        // Las mismas reglas que el logo del wizard (PublishActivityRequest).
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:500'],
        ], [
            'logo.required' => 'Elige una imagen para el logo.',
            'logo.max' => 'El logo no puede pesar más de 500 KB.',
        ], ['logo' => 'el logo']);

        $organizacion->update([
            'logo_path' => 'storage/'.$request->file('logo')->store('organizaciones', 'public'),
        ]);

        return redirect()->to(route('account.perfil').'#logo-organizacion')
            ->with('ok', 'Logo guardado. Ya sale en tus actividades.');
    }
}
