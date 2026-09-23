<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Los textos de la imagen de difusión de una actividad, ya resueltos.
 *
 * La imagen se dibuja en el navegador (`resources/js/difusion.js`), pero lo
 * que dice se decide aquí: cómo se escribe una fecha de varios días, qué sale
 * en «cupos» si no se pide inscripción, qué pasa con una actividad en línea.
 * El navegador sólo reparte en líneas y pinta.
 */
class DatosDifusion
{
    /** El pie, si la ONG no ha escrito otro en Configuración → General. */
    public const FECHAS_POR_DEFECTO = '4 y 5 de diciembre';

    public const HASHTAG_POR_DEFECTO = '#DarEstaEnNuestraNaturaleza';

    /** @return array<string, mixed> */
    public static function de(Activity $a): array
    {
        $a->loadMissing(['organization', 'commune', 'region']);
        $org = $a->organization;

        return [
            'titulo' => $a->titulo,
            // Espacios y saltos de línea seguidos, a uno: la descripción se
            // reparte en dos o tres líneas y un salto a mano la rompería.
            'descripcion' => trim((string) preg_replace('/\s+/u', ' ', (string) $a->descripcion)),
            'foto' => $a->imagen_url,
            'cuando' => self::cuando($a),
            'donde' => self::donde($a),
            'cupos' => self::cupos($a),
            'formato' => (string) $a->formato,
            'organizacion' => [
                'nombre' => (string) $org?->nombre,
                'logo' => $org?->logo_url,
                'iniciales' => (string) $org?->iniciales,
            ],
            // «Más información en:» apunta al sitio, sacado de APP_URL para
            // que al migrar al dominio bueno cambie solo.
            'web' => Str::of((string) parse_url((string) config('app.url'), PHP_URL_HOST))->lower()->toString(),
            'fechas' => trim((string) Setting::get('difusion_fechas')) ?: self::FECHAS_POR_DEFECTO,
            'hashtag' => trim((string) Setting::get('difusion_hashtag')) ?: self::HASHTAG_POR_DEFECTO,
            'archivo' => 'difusion-'.$a->slug.'.png',
        ];
    }

    /**
     * La fecha y, aparte, las horas: van en líneas distintas.
     *
     * @return array{fecha: string, horas: string}
     */
    private static function cuando(Activity $a): array
    {
        if ($a->sin_fecha_definida || ! $a->fecha_inicio) {
            return ['fecha' => 'Fecha por definir', 'horas' => ''];
        }

        $ini = $a->fecha_inicio->locale('es');
        $fin = $a->fecha_termino?->locale('es');

        $fecha = match (true) {
            ! $fin || $fin->isSameDay($ini) => $ini->isoFormat('D [de] MMMM [de] YYYY'),
            $fin->isSameMonth($ini) => $ini->isoFormat('D').' al '.$fin->isoFormat('D [de] MMMM [de] YYYY'),
            $fin->isSameYear($ini) => $ini->isoFormat('D [de] MMMM').' al '.$fin->isoFormat('D [de] MMMM [de] YYYY'),
            default => $ini->isoFormat('D [de] MMMM [de] YYYY').' al '.$fin->isoFormat('D [de] MMMM [de] YYYY'),
        };

        $h = fn ($x) => $x ? substr((string) $x, 0, 5) : '';
        $horas = match (true) {
            $h($a->hora_inicio) && $h($a->hora_termino) => $h($a->hora_inicio).' – '.$h($a->hora_termino),
            (bool) $h($a->hora_inicio) => 'Desde las '.$h($a->hora_inicio),
            default => '',
        };

        return ['fecha' => $fecha, 'horas' => $horas];
    }

    /** @return array{lugar: string, direccion: string} */
    private static function donde(Activity $a): array
    {
        if ($a->formato === 'Online') {
            return ['lugar' => 'Actividad en línea', 'direccion' => ''];
        }

        $lugar = collect([$a->commune?->nombre, $a->region?->nombre])->filter()->implode(', ');

        return [
            'lugar' => $lugar !== '' ? $lugar : 'Lugar por confirmar',
            'direccion' => (string) $a->direccion,
        ];
    }

    private static function cupos(Activity $a): string
    {
        if (! $a->inscripcion_habilitada) {
            return 'Sin inscripción previa';
        }

        return match (true) {
            $a->cupos_disponibles === null => 'Sin límite de cupos',
            $a->cupos_disponibles <= 0 => 'Cupos agotados',
            default => $a->cupos_disponibles.' '.($a->cupos_disponibles === 1 ? 'disponible' : 'disponibles'),
        };
    }
}
