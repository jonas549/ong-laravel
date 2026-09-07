<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\ActivityEvaluation;
use App\Models\EmailTemplate;
use App\Models\Setting;
use App\Services\CodigoQr;
use App\Services\Evaluaciones;
use App\Support\CatalogoAjustes;
use Illuminate\Console\Command;
use Throwable;

/**
 * `php artisan dps:qr` — comprueba que el QR y la encuesta están en pie.
 *
 * Existe por una razón muy concreta, y es la única de todo este bloque que no
 * se puede arreglar después: **el QR codifica una URL absoluta que sale de
 * `APP_URL`. Si `APP_URL` está mal en el servidor, cada cartel que se imprima
 * apunta a un sitio equivocado, para siempre.** No hay aviso, no hay error y no
 * se descubre hasta que alguien escanea el cartel el día de la actividad.
 *
 * Es el mismo patrón de fallo mudo que este proyecto ya pagó con el correo, con
 * el bloqueo por intentos y con las rutas de imagen. Así que se comprueba antes,
 * con un comando, y no se da por bueno mirando que la página cargue.
 *
 * De paso mira las otras dos cosas del bloque que dependen del entorno y no del
 * código: los límites de subida de PHP y si la siembra ha llegado.
 */
class ComprobarEvaluacion extends Command
{
    protected $signature = 'dps:qr {--actividad= : Slug o id de una actividad para ver su QR}';

    protected $description = 'Comprueba la URL del sitio, los límites de subida y la encuesta de evaluación';

    private bool $hayProblema = false;

    public function handle(Evaluaciones $evaluaciones, CodigoQr $qr): int
    {
        $this->newLine();
        $this->line('  <options=bold>Encuesta de evaluación · '.config('app.name').'</>');
        $this->line('  <fg=gray>entorno '.app()->environment().'</>');

        $this->direccionDelSitio();
        $this->limitesDeSubida();
        $this->siembra();
        $this->ajustes($evaluaciones);
        $this->respuestas();

        if ($slug = $this->option('actividad')) {
            $this->unaActividad($slug, $qr, $evaluaciones);
        }

        $this->newLine();

        if ($this->hayProblema) {
            $this->line('  <fg=red;options=bold>Hay algo que arreglar.</> Lo marcado con ✗ arriba.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->line('  <fg=green;options=bold>Todo en orden.</>');
        $this->newLine();

        return self::SUCCESS;
    }

    /** Lo importante: la dirección que van a llevar impresa los carteles. */
    private function direccionDelSitio(): void
    {
        $url = (string) config('app.url');

        $this->newLine();
        $this->line('  <options=bold>La dirección que codifica el QR</>');
        $this->dato('APP_URL', $url ?: '(vacía)');

        if (blank($url)) {
            $this->mal('APP_URL está vacía. Todos los QR saldrían con una dirección rota.');

            return;
        }

        if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1') || str_ends_with($url, '.test')) {
            if (app()->environment('production')) {
                $this->mal('APP_URL apunta a una dirección local. Un QR impreso con esto no lleva a ninguna parte.');
            } else {
                $this->line('     <fg=gray>Es una dirección local, que es lo correcto fuera de producción.</>');
            }

            return;
        }

        if (! str_starts_with($url, 'https://')) {
            $this->mal('APP_URL no empieza por https. El QR llevaría a una conexión sin cifrar.');
        }

        if (str_ends_with($url, '/')) {
            /*
             * No rompe nada —`route()` normaliza— pero deja el aviso, porque una
             * barra final es el síntoma habitual de que la variable se copió a
             * mano y conviene mirar el resto.
             */
            $this->line('     <fg=yellow>!</> Lleva barra al final. No rompe nada, pero revisa que el resto esté bien.');
        }

        $this->bien('La dirección tiene buena pinta.');
    }

    /**
     * Los límites de PHP para la fotografía.
     *
     * `post_max_size` tiene que ser mayor que `upload_max_filesize`, y no sólo
     * igual: además del archivo viaja el resto del formulario. Si se queda
     * corto, la persona pierde todo lo que escribió y ve un 419 que no menciona
     * ninguna foto (lo ataja `AvisaSiLaSubidaEsDemasiadoGrande`, pero el aviso
     * es un consuelo: lo que hace falta es que quepa).
     */
    private function limitesDeSubida(): void
    {
        $subida = $this->aBytes((string) ini_get('upload_max_filesize'));
        $post = $this->aBytes((string) ini_get('post_max_size'));

        // Lo que promete el formulario, en `EvaluationRequest`.
        $necesario = 5 * 1024 * 1024;

        $this->newLine();
        $this->line('  <options=bold>Límites de subida de PHP</>');
        $this->dato('upload_max_filesize', (string) ini_get('upload_max_filesize'));
        $this->dato('post_max_size', (string) ini_get('post_max_size'));
        $this->dato('memory_limit', (string) ini_get('memory_limit'));

        if ($subida < $necesario) {
            $this->mal('upload_max_filesize no llega a los 5 MB que promete el formulario. Súbelo a 8M en el MultiPHP INI Editor de cPanel.');
        } elseif ($post <= $subida) {
            $this->mal('post_max_size tiene que ser MAYOR que upload_max_filesize: además del archivo viaja el resto del formulario. Ponlo en 12M.');
        } else {
            $this->bien('Caben los 5 MB de fotografía que promete el formulario.');
        }

        $this->line('     <fg=gray>El navegador reduce la foto a 1600 px antes de subirla, así que en la práctica</>');
        $this->line('     <fg=gray>llegan unos 400 KB. Estos límites son la red por si el JavaScript no corre.</>');
    }

    /** ¿Ha llegado la siembra? Es el pendiente número uno del proyecto. */
    private function siembra(): void
    {
        $this->newLine();
        $this->line('  <options=bold>Siembra</>');

        $plantilla = EmailTemplate::where('clave', 'actividad_publicada')->first();

        if (! $plantilla) {
            $this->mal('Falta la plantilla «actividad_publicada». El aviso saldría sin QR. Corre php artisan dps:instalar.');
        } elseif (! $plantilla->activo) {
            $this->mal('La plantilla «actividad_publicada» está desactivada desde el panel: no sale ningún aviso de publicación.');
        } else {
            $this->bien('La plantilla del aviso de publicación existe y está activa.');

            if (! str_contains((string) $plantilla->cuerpo_html, '{{ bloque_qr }}')) {
                $this->line('     <fg=yellow>!</> Su cuerpo no usa {{ bloque_qr }}, así que el correo sale sin código.');
                $this->line('     <fg=gray>Se avisa en el editor de la plantilla; alguien tiene que colocarlo.</>');
            }
        }

        foreach (['evaluacion_apertura', 'evaluacion_dias_abierta'] as $clave) {
            if (! Setting::where('clave', $clave)->exists()) {
                $this->mal("Falta el ajuste «{$clave}»: la ONG no puede cambiarlo desde Configuración.");
            }
        }
    }

    private function ajustes(Evaluaciones $evaluaciones): void
    {
        $apertura = $evaluaciones->apertura();
        $dias = $evaluaciones->diasAbierta();

        $this->newLine();
        $this->line('  <options=bold>Cómo está configurada la encuesta</>');
        $this->dato('Se abre', CatalogoAjustes::EVALUACION_APERTURA[$apertura] ?? $apertura);
        $this->dato('Se cierra', $dias === 0 ? 'nunca' : "a los {$dias} días de terminar la actividad");
    }

    private function respuestas(): void
    {
        $total = ActivityEvaluation::count();
        $conFoto = ActivityEvaluation::query()->conFoto()->count();
        $autorizadas = ActivityEvaluation::query()->autorizadas()->count();

        $this->newLine();
        $this->line('  <options=bold>Respuestas recibidas</>');
        $this->dato('Total', (string) $total);
        $this->dato('Con fotografía', $conFoto.' ('.$autorizadas.' autorizadas para difusión)');
    }

    /** El QR de una actividad concreta, para mirarlo con los ojos. */
    private function unaActividad(string $slug, CodigoQr $qr, Evaluaciones $evaluaciones): void
    {
        $actividad = Activity::where('slug', $slug)->orWhere('id', (int) $slug)->first();

        $this->newLine();
        $this->line('  <options=bold>La actividad pedida</>');

        if (! $actividad) {
            $this->mal("No hay ninguna actividad con slug o id «{$slug}».");

            return;
        }

        $estado = $evaluaciones->estado($actividad);

        $this->dato('Actividad', $actividad->titulo);
        $this->dato('Estado', $actividad->estado);
        $this->dato('El QR lleva a', $qr->destino($actividad));
        $this->dato('La encuesta está', $estado['estado'].($estado['mensaje'] ? ' — '.$estado['mensaje'] : ''));

        $cierre = $evaluaciones->fechaDeCierre($actividad);
        $this->dato('Cierra el', $cierre?->format('d/m/Y') ?? 'no cierra');

        try {
            $destino = storage_path('app/qr-'.$actividad->slug.'.png');
            file_put_contents($destino, $qr->png($actividad));
            $this->bien('QR de prueba escrito en '.$destino);
        } catch (Throwable $e) {
            $this->mal('No se pudo generar el QR: '.$e->getMessage());
        }
    }

    /* ── Pintar ──────────────────────────────────────────── */

    private function dato(string $etiqueta, string $valor): void
    {
        $this->line(sprintf('     <fg=gray>%-24s</> %s', $etiqueta, $valor));
    }

    private function bien(string $texto): void
    {
        $this->line("     <fg=green>✓</> {$texto}");
    }

    private function mal(string $texto): void
    {
        $this->hayProblema = true;
        $this->line("     <fg=red>✗</> {$texto}");
    }

    private function aBytes(string $valor): int
    {
        $valor = trim($valor);

        if ($valor === '' || $valor === '0' || $valor === '-1') {
            return PHP_INT_MAX;
        }

        $numero = (int) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g' => $numero * 1024 * 1024 * 1024,
            'm' => $numero * 1024 * 1024,
            'k' => $numero * 1024,
            default => $numero,
        };
    }
}
