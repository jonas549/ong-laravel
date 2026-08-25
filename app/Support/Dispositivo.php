<?php

namespace App\Support;

/**
 * Traduce un user agent a algo legible.
 *
 * Lo usan el registro de accesos y la lista de sesiones activas: en las dos
 * pantallas hay que decir desde dónde entró alguien, y el user agent completo
 * ocupa una línea entera sin aportar nada de un vistazo.
 */
class Dispositivo
{
    public static function describir(?string $userAgent): string
    {
        $ua = (string) $userAgent;

        $navegador = match (true) {
            // El orden importa: Edge y Opera también dicen "Chrome".
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            $ua === '' => 'Desconocido',
            default => 'Otro',
        };

        $sistema = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => '',
        };

        return trim($navegador.($sistema ? " · {$sistema}" : ''));
    }
}
