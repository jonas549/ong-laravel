<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Fechas y horas para la pantalla, en la hora de Chile.
 *
 * **La aplicación guarda en UTC y eso se queda como está.** Cambiar
 * `APP_TIMEZONE` haría que Laravel escribiera en hora local, y todo lo ya
 * guardado —correos, accesos, inscripciones— pasaría a leerse cuatro horas
 * corrido. Lo que estaba mal no era el almacenamiento, era que el panel
 * enseñaba la hora cruda: el aviso del autoguardado decía «21:36» cuando en
 * Chile eran las 17:36.
 *
 * Así que se convierte **al pintar**, y en un solo sitio. Las 21 llamadas
 * sueltas que había repartidas por las vistas pasan por aquí, para que no
 * vuelva a haber dos pantallas diciendo horas distintas del mismo momento.
 *
 * La zona sale de `config('app.zona_horaria')`, que se puede cambiar desde el
 * `.env` sin tocar código: el día que la ONG opere fuera de Chile, es una
 * línea.
 */
class Fecha
{
    /** «8 ago. 2026» */
    public static function corta(mixed $valor): string
    {
        return static::pinta($valor, 'D MMM YYYY');
    }

    /** «8 ago. 2026, 17:36» */
    public static function conHora(mixed $valor): string
    {
        return static::pinta($valor, 'D MMM YYYY, HH:mm');
    }

    /** «8 ago. 17:36» — para tablas apretadas */
    public static function diaYHora(mixed $valor): string
    {
        return static::pinta($valor, 'D MMM HH:mm');
    }

    /** «8 de agosto 2026, 17:36» */
    public static function larga(mixed $valor): string
    {
        return static::pinta($valor, 'D [de] MMMM YYYY, HH:mm');
    }

    /** «17:36» */
    public static function hora(mixed $valor): string
    {
        return static::pinta($valor, 'HH:mm');
    }

    /**
     * «hace 3 horas».
     *
     * También se convierte, aunque el resultado sea el mismo: el cálculo es
     * contra `now()` y la diferencia no cambia con la zona. Se hace igual para
     * que no haya un solo camino que se salte la conversión, porque el día que
     * alguien cambie el formato aquí, ya estará en la zona correcta.
     */
    public static function relativa(mixed $valor, bool $corta = false): string
    {
        $fecha = static::aCarbon($valor);

        return $fecha ? $fecha->locale('es')->diffForHumans(null, $corta) : '—';
    }

    /** Para un `<input type="datetime-local">`. */
    public static function paraInput(mixed $valor): string
    {
        $fecha = static::aCarbon($valor);

        return $fecha ? $fecha->format('Y-m-d\TH:i') : '';
    }

    /** Para una exportación: la fecha sin adornos. */
    public static function iso(mixed $valor): string
    {
        $fecha = static::aCarbon($valor);

        return $fecha ? $fecha->format('Y-m-d') : '';
    }

    public static function zona(): string
    {
        return config('app.zona_horaria', 'America/Santiago');
    }

    private static function pinta(mixed $valor, string $formato): string
    {
        $fecha = static::aCarbon($valor);

        return $fecha ? $fecha->locale('es')->isoFormat($formato) : '—';
    }

    /**
     * Lo que llegue, convertido a la zona de la pantalla.
     *
     * Devuelve null en vez de reventar cuando el valor es nulo: una fecha que
     * falta es lo normal —un correo sin enviar, una actividad sin publicar— y
     * la pantalla tiene que poder decir «—» sin caerse.
     */
    private static function aCarbon(mixed $valor): ?Carbon
    {
        if (blank($valor)) {
            return null;
        }

        $fecha = $valor instanceof Carbon ? $valor->copy() : Carbon::parse($valor);

        return $fecha->setTimezone(static::zona());
    }
}
