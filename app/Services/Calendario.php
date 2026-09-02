<?php

namespace App\Services;

use App\Models\Activity;
use App\Support\Fecha;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * «Añadir a mi calendario»: el enlace de Google y el archivo `.ics`.
 *
 * **Sin librería.** Un `.ics` son quince líneas de texto plano y un enlace de
 * Google es una URL con parámetros; traer una dependencia para esto sería
 * pagar mantenimiento durante años a cambio de nada. Este proyecto va a un
 * hosting compartido y sin desarrollador propio: cada paquete que no está es
 * uno que nadie tendrá que actualizar.
 *
 * **Sin adjuntos, tampoco.** El `.ics` se sirve desde una ruta pública en vez
 * de viajar pegado al correo. Así no hace falta el trabajo de adjuntos que
 * está aplazado, el correo no engorda, y quien lo abra desde el móvil lo baja
 * cuando quiera.
 *
 * ── La zona horaria, que es donde esto se tuerce ──
 *
 * La aplicación guarda en UTC, pero `fecha_inicio` es una columna DATE y
 * `hora_inicio` una TIME: son la hora de pared en Chile, sin zona pegada. Si se
 * volcaran tal cual al `.ics`, cada evento aparecería corrido cuatro horas en
 * el calendario de quien lo abra. Se montan explícitamente en la zona del sitio
 * y se pasan a UTC, que es como el formato manda escribirlas.
 */
class Calendario
{
    /** Cuánto dura un evento que dice a qué hora empieza pero no cuándo acaba. */
    private const HORAS_POR_DEFECTO = 2;

    /**
     * ¿Hay algo que agendar?
     *
     * Una actividad «disponible de forma permanente» no tiene fecha, y ofrecer
     * un botón de calendario para algo que no ocurre ningún día concreto es
     * prometer lo que no se puede cumplir.
     */
    public function agendable(?Activity $actividad): bool
    {
        return $actividad !== null
            && ! $actividad->sin_fecha_definida
            && $actividad->fecha_inicio !== null;
    }

    /**
     * El bloque HTML con los dos enlaces, listo para meter en un correo.
     *
     * Se da hecho, y no como dos URLs sueltas, por una razón concreta: las
     * plantillas las edita la ONG desde el panel y no tienen condicionales. Con
     * dos variables de URL, una actividad sin fecha dejaría un `href` vacío
     * dentro de un enlace que sigue diciendo «añadir a mi calendario». Así el
     * bloque entero desaparece y no queda nada roto.
     */
    public function bloqueHtml(?Activity $actividad): string
    {
        if (! $this->agendable($actividad)) {
            return '';
        }

        $google = e($this->enlaceGoogle($actividad));
        $ics = e($this->enlaceIcs($actividad));

        return trim(<<<HTML
        <p style="margin:22px 0 0;font-size:14px;color:#63666A;">
            Añádelo a tu calendario:
            <a href="{$google}" style="color:#cc6600;font-weight:600;">Google Calendar</a>
            &nbsp;·&nbsp;
            <a href="{$ics}" style="color:#cc6600;font-weight:600;">Apple, Outlook y otros</a>
        </p>
        HTML);
    }

    /** La ruta propia que devuelve el `.ics`. */
    public function enlaceIcs(Activity $actividad): string
    {
        return route('activities.calendario', $actividad);
    }

    /**
     * La URL de «añadir a Google Calendar».
     *
     * Es su formato de siempre, sin API ni credenciales: parámetros en la
     * dirección y Google abre el formulario de evento relleno.
     */
    public function enlaceGoogle(Activity $actividad): string
    {
        [$inicio, $fin, $todoElDia] = $this->momentos($actividad);

        $formato = $todoElDia ? 'Ymd' : 'Ymd\THis\Z';

        /*
         * `dates` se pega aparte para que la barra entre las dos fechas quede
         * literal. `http_build_query` la convertiría en %2F: Google lo entiende
         * igual, pero es de esas cosas que no puedo comprobar desde aquí y que
         * no cuesta nada dejar en la forma que su documentación enseña.
         */
        $fechas = 'dates=' . $inicio->format($formato) . '/' . $fin->format($formato);

        return 'https://calendar.google.com/calendar/render?action=TEMPLATE&' . $fechas . '&' . http_build_query([
            'text' => $actividad->titulo,
            'details' => $this->descripcion($actividad),
            'location' => $this->lugar($actividad),
        ]);
    }

    /**
     * El archivo `.ics`.
     *
     * Las líneas van separadas por CRLF porque el RFC 5545 lo exige, y hay
     * lectores —Outlook entre ellos— que con saltos de Unix se atragantan.
     */
    public function ics(Activity $actividad): string
    {
        [$inicio, $fin, $todoElDia] = $this->momentos($actividad);

        $lineas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//' . $this->escapar(config('app.name')) . '//ES',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            // Estable: el mismo evento reimportado se actualiza en vez de
            // duplicarse, que es lo que pasa con un identificador al azar.
            'UID:actividad-' . $actividad->id . '@' . parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'),
        ];

        if ($todoElDia) {
            // En un evento de día completo el final es EXCLUSIVO: para que dure
            // el día 4 hay que decir que termina el 5.
            $lineas[] = 'DTSTART;VALUE=DATE:' . $inicio->format('Ymd');
            $lineas[] = 'DTEND;VALUE=DATE:' . $fin->format('Ymd');
        } else {
            $lineas[] = 'DTSTART:' . $inicio->format('Ymd\THis\Z');
            $lineas[] = 'DTEND:' . $fin->format('Ymd\THis\Z');
        }

        $lineas[] = 'SUMMARY:' . $this->escapar($actividad->titulo);
        $lineas[] = 'DESCRIPTION:' . $this->escapar($this->descripcion($actividad));

        if ($lugar = $this->lugar($actividad)) {
            $lineas[] = 'LOCATION:' . $this->escapar($lugar);
        }

        $lineas[] = 'URL:' . route('activities.show', $actividad);
        $lineas[] = 'END:VEVENT';
        $lineas[] = 'END:VCALENDAR';

        return implode("\r\n", array_map($this->plegar(...), $lineas)) . "\r\n";
    }

    /** Un nombre de archivo que no asuste a ningún sistema. */
    public function nombreArchivo(Activity $actividad): string
    {
        return Str::slug($actividad->titulo) . '.ics';
    }

    /**
     * Cuándo empieza y cuándo acaba, en UTC, y si es de día completo.
     *
     * @return array{0: Carbon, 1: Carbon, 2: bool}
     */
    private function momentos(Activity $actividad): array
    {
        $zona = Fecha::zona();
        $dia = $actividad->fecha_inicio->format('Y-m-d');
        $ultimoDia = $actividad->fecha_termino?->format('Y-m-d') ?? $dia;

        // Sin hora de inicio no hay evento con horario: es un día entero.
        if (blank($actividad->hora_inicio)) {
            return [
                Carbon::createFromFormat('Y-m-d', $dia, $zona)->startOfDay(),
                Carbon::createFromFormat('Y-m-d', $ultimoDia, $zona)->startOfDay()->addDay(),
                true,
            ];
        }

        $inicio = Carbon::createFromFormat('Y-m-d H:i', $dia . ' ' . $this->hhmm($actividad->hora_inicio), $zona);

        $fin = blank($actividad->hora_termino)
            ? $inicio->copy()->addHours(self::HORAS_POR_DEFECTO)
            : Carbon::createFromFormat('Y-m-d H:i', $ultimoDia . ' ' . $this->hhmm($actividad->hora_termino), $zona);

        // Una hora de término anterior a la de inicio dentro del mismo día no
        // es un error de datos: la actividad cruza la medianoche.
        if ($fin->lessThanOrEqualTo($inicio)) {
            $fin = $inicio->copy()->addHours(self::HORAS_POR_DEFECTO);
        }

        return [$inicio->utc(), $fin->utc(), false];
    }

    private function hhmm(string $hora): string
    {
        return substr($hora, 0, 5);
    }

    private function descripcion(Activity $actividad): string
    {
        return collect([
            Str::limit(strip_tags((string) $actividad->descripcion), 500),
            $actividad->organization?->nombre ? 'Organiza: ' . $actividad->organization->nombre : null,
            route('activities.show', $actividad),
        ])->filter()->implode("\n\n");
    }

    private function lugar(Activity $actividad): string
    {
        return trim(($actividad->direccion ? $actividad->direccion . ', ' : '') . $actividad->lugar, ', ');
    }

    /**
     * El escapado del RFC 5545: coma, punto y coma y barra invertida llevan
     * barra delante, y los saltos de línea van como `\n` literal.
     */
    private function escapar(string $texto): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n'],
            $texto,
        );
    }

    /**
     * Ninguna línea puede pasar de 75 octetos; las largas se parten y las
     * continuaciones empiezan con un espacio.
     *
     * Se cuenta en BYTES y se corta por caracteres, que no es lo mismo en
     * cuanto hay una tilde: partir una letra de dos bytes por la mitad deja el
     * archivo ilegible.
     */
    private function plegar(string $linea): string
    {
        if (strlen($linea) <= 75) {
            return $linea;
        }

        $trozos = [];
        $actual = '';
        $tope = 74;

        foreach (mb_str_split($linea) as $letra) {
            if (strlen($actual . $letra) > $tope) {
                $trozos[] = $actual;
                $actual = '';
                // Las continuaciones llevan un espacio delante, que ocupa sitio.
                $tope = 73;
            }

            $actual .= $letra;
        }

        $trozos[] = $actual;

        return implode("\r\n ", $trozos);
    }
}
