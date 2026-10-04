<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A dónde mandar a alguien que acaba de entrar.
 *
 * `redirect()->intended()` lleva a la última página protegida que se pidió
 * sin sesión, sea de quien sea. Con dos puertas —el panel y mi-cuenta— eso
 * fallaba en cuanto la página pendiente era de la otra: alguien abría
 * `/admin` sin sesión, quedaba apuntado como destino, entraba después por la
 * puerta de organizador y acababa en un 403 «No puedes ver esta página»
 * (04/10). Cada puerta sólo acepta destinos de su lado; los demás se tiran.
 */
class DestinoPendiente
{
    public const PANEL = 'panel';

    public const CUENTA = 'cuenta';

    public static function redirigir(Request $request, string $puerta, string $porDefecto): RedirectResponse
    {
        $pendiente = $request->session()->pull('url.intended');

        if (is_string($pendiente) && self::esDe($pendiente, $puerta)) {
            return redirect()->to($pendiente);
        }

        return redirect()->to($porDefecto);
    }

    /** Si una URL del propio sitio pertenece al panel o a mi-cuenta. */
    public static function esDe(string $url, string $puerta): bool
    {
        $ruta = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $esPanel = $ruta === '/admin' || str_starts_with($ruta, '/admin/');

        return $puerta === self::PANEL ? $esPanel : ! $esPanel;
    }
}
