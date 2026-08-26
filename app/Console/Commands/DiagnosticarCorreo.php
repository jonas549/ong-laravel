<?php

namespace App\Console\Commands;

use App\Models\EmailTemplate;
use App\Services\DiagnosticoCorreo;
use App\Services\MailTestService;
use Illuminate\Console\Command;

/**
 * `php artisan dps:correo` — por qué no sale el correo, en una pantalla.
 *
 * Pensado para correrlo en el servidor por SSH. Recorre la cadena entera en
 * orden —configuración, transporte, servidor SMTP, cola, plantillas— y en cada
 * eslabón dice lo que hay, no lo que debería haber. Con `--enviar` hace además
 * un envío real y síncrono, saltándose la cola.
 */
class DiagnosticarCorreo extends Command
{
    protected $signature = 'dps:correo
                            {--enviar= : Manda un correo real a esta dirección, sin pasar por la cola}
                            {--sin-sonda : No habla con el servidor SMTP, sólo revisa la configuración}';

    protected $description = 'Diagnostica por qué no sale el correo: configuración, transporte, SMTP, cola y plantillas';

    private bool $hayProblema = false;

    public function handle(DiagnosticoCorreo $diag, MailTestService $prueba): int
    {
        $this->newLine();
        $this->line('  <options=bold>Diagnóstico de correo · '.config('app.name').'</>');
        $this->line('  <fg=gray>'.config('app.url').' · entorno '.app()->environment().'</>');

        $this->configuracion($diag);
        $transporte = $this->transporte($diag);

        if (! $this->option('sin-sonda')) {
            $this->sonda($diag);
        }

        $this->cola($diag);
        $this->plantillas($diag);

        if ($destino = $this->option('enviar')) {
            $this->envioReal($prueba, $destino, $transporte);
        }

        $this->newLine();

        if ($this->hayProblema) {
            $this->line('  <fg=red;options=bold>Hay al menos un eslabón roto.</> Lo marcado con ✗ arriba es lo que impide que salga el correo.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->line('  <fg=green;options=bold>La cadena está entera.</> Si aun así no llega, mira la carpeta de spam y el registro del servidor.');
        $this->newLine();

        return self::SUCCESS;
    }

    private function configuracion(DiagnosticoCorreo $diag): void
    {
        $this->titulo('1. Configuración SMTP del panel');
        $cfg = $diag->configuracionPanel();

        $this->dato('Interruptor "Usar esta configuración"', $cfg['activo'] ? 'encendido' : 'apagado');

        if (! $cfg['se_aplica']) {
            $this->mal($cfg['motivo']);

            return;
        }

        $this->dato('Servidor', $cfg['host'].':'.($cfg['puerto'] ?: '(sin puerto)'));
        $this->dato('Cifrado', $cfg['encryption'] ?: '(sin definir)');
        $this->dato('Usuario', $cfg['usuario'] ?: '(vacío)');
        $this->dato('Remitente', $cfg['remitente'] ?: '(vacío, se usa el del .env)');

        $clave = $cfg['contrasena'];

        match (true) {
            ! $clave['guardada'] => $this->mal('No hay contraseña SMTP guardada.'),
            ! $clave['legible'] => $this->mal($clave['nota']),
            default => $this->bien('Contraseña guardada y legible.'),
        };

        foreach ($cfg['avisos'] as $aviso) {
            $this->aviso($aviso);
        }
    }

    /** @return array<string, mixed> */
    private function transporte(DiagnosticoCorreo $diag): array
    {
        $this->titulo('2. Transporte que lleva el correo');
        $t = $diag->transporte();

        $this->dato('MAIL_MAILER del .env', $t['mailer_env']);
        $this->dato('Mailer efectivo', $t['mailer_efectivo'].($t['config_desde_panel'] ? ' (impuesto por el panel)' : ' (del .env)'));
        $this->dato('Remitente efectivo', (string) $t['remitente']);

        if ($t['error_transporte']) {
            $this->mal('No se pudo construir el transporte: '.$t['error_transporte']);

            return $t;
        }

        $this->dato('Transporte', (string) $t['transporte']);

        if (! $t['entrega_de_verdad']) {
            $this->mal(
                'Este transporte NO entrega nada a nadie: escribe en '
                .($t['transporte_clase'] === \Illuminate\Mail\Transport\LogTransport::class ? 'storage/logs' : 'memoria')
                .'. Todo lo que "salga" por aquí aparecerá como enviado sin haber salido.'
            );
        } else {
            $this->bien('Es un transporte real.');
        }

        return $t;
    }

    private function sonda(DiagnosticoCorreo $diag): void
    {
        $this->titulo('3. Conversación real con el servidor SMTP');
        $r = $diag->sondaSmtp();

        foreach ($r['pasos'] as $paso) {
            $this->line('     <fg=gray>·</> '.$paso);
        }

        $r['ok'] ? $this->bien('El servidor acepta la conexión, las credenciales y el remitente.')
                 : $this->mal((string) $r['error']);
    }

    private function cola(DiagnosticoCorreo $diag): void
    {
        $this->titulo('4. Cola');
        $c = $diag->cola();

        if (! $c['medible']) {
            $this->aviso($c['nota']);

            return;
        }

        $this->dato('Trabajos esperando', (string) $c['pendientes']);
        $this->dato('Trabajos fallidos', (string) $c['fallidos']);

        if ($c['atascada']) {
            $espera = \Carbon\CarbonInterval::seconds($c['espera_segundos'])->cascade()->forHumans(short: true);
            $this->mal(
                "El trabajo más viejo lleva {$espera} esperando: el worker no está corriendo. "
                .'Todo el correo de este sistema va a la cola, así que ahora mismo no sale ni uno.'
            );
            $this->line('     <fg=gray>Falta esta línea en el cron del servidor:</>');
            $this->line('     <fg=yellow>* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1</>');
        } elseif ($c['pendientes'] > 0) {
            $this->bien('Hay trabajos en cola pero son recientes; el worker parece estar dándoles salida.');
        } else {
            $this->bien('La cola está vacía.');
        }

        if ($c['fallidos'] > 0) {
            $this->aviso("Hay {$c['fallidos']} trabajos fallidos. Míralos con `php artisan queue:failed`.");
        }
    }

    private function plantillas(DiagnosticoCorreo $diag): void
    {
        $this->titulo('5. Plantillas de los correos automáticos');
        $p = $diag->plantillas();

        if (! $p['medible']) {
            $this->aviso($p['nota']);

            return;
        }

        foreach ($p['detalle'] as $clave => $e) {
            $estado = match (true) {
                ! $e['existe'] => '<fg=red>no está en la base de datos</>',
                ! $e['activo'] => '<fg=yellow>desactivada desde el panel</>',
                default => '<fg=green>lista</>',
            };

            $this->line(sprintf('     <fg=gray>·</> %-24s %s', $clave, $estado));
        }

        if ($p['faltan']) {
            $this->mal(
                'Faltan '.count($p['faltan']).' de '.count(EmailTemplate::CATALOGO).' plantillas. '
                .'Los correos que las usan no se envían y no dejan rastro. '
                .'Se restauran con `php artisan dps:instalar` o desde el panel, en Configuración → Plantillas de correo.'
            );
        } elseif ($p['apagadas']) {
            $this->aviso('Hay plantillas desactivadas a propósito desde el panel: '.implode(', ', $p['apagadas']).'.');
        } else {
            $this->bien('Las cinco plantillas están sembradas y activas.');
        }
    }

    private function envioReal(MailTestService $prueba, string $destino, array $transporte): void
    {
        $this->titulo('6. Envío real');

        if (! $transporte['entrega_de_verdad']) {
            $this->mal('No tiene sentido enviar: el transporte activo no entrega. Arregla el punto 2 primero.');

            return;
        }

        $this->line("     <fg=gray>·</> enviando a {$destino}…");
        $r = $prueba->enviar($destino);

        $r['ok'] ? $this->bien($r['mensaje']) : $this->mal($r['mensaje'].' '.$r['detalle']);
    }

    private function titulo(string $texto): void
    {
        $this->newLine();
        $this->line('  <options=bold>'.$texto.'</>');
    }

    private function dato(string $etiqueta, string $valor): void
    {
        $this->line(sprintf('     <fg=gray>%-36s</> %s', $etiqueta, $valor));
    }

    private function bien(string $texto): void
    {
        $this->line('     <fg=green>✓</> '.$texto);
    }

    private function aviso(string $texto): void
    {
        $this->line('     <fg=yellow>!</> '.$texto);
    }

    private function mal(string $texto): void
    {
        $this->hayProblema = true;
        $this->line('     <fg=red>✗</> '.$texto);
    }
}
