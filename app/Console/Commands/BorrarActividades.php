<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\ActivityCollaborator;
use App\Models\ActivityEvaluation;
use App\Models\ActivityStatusLog;
use App\Models\EvaluationPhoto;
use App\Models\Media;
use App\Models\Registration;
use App\Services\Biblioteca;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * `php artisan dps:borrar-actividades` — deja la plataforma sin actividades.
 *
 * Borra TODAS, también las que están en la papelera, con lo que cuelga de
 * ellas: inscripciones, evaluaciones, sus fotos, colaboradores, el historial de
 * estados y las etiquetas. Y los archivos: la portada, las fotos de la encuesta
 * y el QR de prueba que deja `dps:qr --actividad`.
 *
 * **Activity usa `SoftDeletes`**: un `delete()` sólo la manda a la papelera y
 * las claves foráneas en cascada no llegan a dispararse. Por eso aquí va
 * `forceDelete()`. Los hijos se borran antes uno a uno con su modelo, para que
 * corra cualquier evento que tengan; la cascada de la base queda de respaldo.
 *
 * **La confirmación no se puede saltar.** No hay `--force`, y sin terminal
 * interactiva (`--no-interaction`, un cron) el comando se niega: hay que
 * escribir BORRAR a mano.
 *
 * No toca organizaciones, usuarios, regiones, comunas, taxonomías, plantillas
 * de correo, páginas, ajustes ni la biblioteca de medios. Tampoco el registro
 * de correos enviados (`email_logs`), que es historial.
 */
class BorrarActividades extends Command
{
    protected $signature = 'dps:borrar-actividades';

    protected $description = 'Borra TODAS las actividades con sus inscripciones, evaluaciones y archivos (pide escribir BORRAR)';

    /** Lo único que se borra del disco público: lo que subió el wizard. */
    private const PREFIJO_PORTADAS = 'storage/actividades/';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <options=bold>Borrar todas las actividades · '.config('app.name').'</>');
        $this->line('  <fg=gray>entorno '.app()->environment().' · base '.config('database.connections.'.config('database.default').'.database').'</>');

        $antes = $this->recuento();
        $archivos = $this->archivos();

        $this->newLine();
        $this->line('  <options=bold>Se va a eliminar</>');
        $this->tablaDeRecuento($antes, $archivos);

        if ($antes['actividades'] === 0) {
            $this->newLine();
            $this->line('  No hay actividades. Nada que borrar.');
            $this->newLine();

            return self::SUCCESS;
        }

        if ($archivos['compartidas'] !== []) {
            $this->newLine();
            $this->line('  <fg=yellow>Portadas que se conservan porque las usa algo más:</>');

            foreach ($archivos['compartidas'] as $ruta) {
                $this->line('    · '.$ruta);
            }
        }

        $pendientes = DB::table('jobs')->count();

        if ($pendientes > 0) {
            $this->newLine();
            $this->line("  <fg=yellow>Hay {$pendientes} trabajos en la cola de correos.</> Los que sean de estas");
            $this->line('  actividades fallarán al no encontrarlas e irán a failed_jobs.');
        }

        if (! $this->input->isInteractive()) {
            $this->newLine();
            $this->error('  Este comando necesita que alguien escriba BORRAR. No se puede ejecutar sin interacción.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->newLine();
        $respuesta = (string) $this->ask('Esto no se puede deshacer. Escribe BORRAR para continuar');

        if (trim($respuesta) !== 'BORRAR') {
            $this->newLine();
            $this->line('  Cancelado. No se ha borrado nada.');
            $this->newLine();

            return self::FAILURE;
        }

        $borrados = $this->borrar();
        $despues = $this->recuento();

        $this->newLine();
        $this->line('  <options=bold>Borrado</>');
        $this->table(['Qué', 'Borrados', 'Quedan'], [
            ['Actividades (incluida la papelera)', $borrados['actividades'], $despues['actividades']],
            ['Inscripciones', $borrados['inscripciones'], $despues['inscripciones']],
            ['Evaluaciones', $borrados['evaluaciones'], $despues['evaluaciones']],
            ['Fotos de evaluación (filas)', $borrados['fotos'], $despues['fotos']],
            ['Colaboradores', $borrados['colaboradores'], $despues['colaboradores']],
            ['Historial de estados', $borrados['historial'], $despues['historial']],
            ['Etiquetas de taxonomía (enlaces)', $borrados['etiquetas'], $despues['etiquetas']],
            ['Archivos de portada', $borrados['archivos_portada'], '—'],
            ['Archivos de fotos de evaluación', $borrados['archivos_fotos'], '—'],
            ['Archivos de QR de prueba', $borrados['archivos_qr'], '—'],
        ]);

        if ($borrados['no_borrados'] !== []) {
            $this->line('  <fg=yellow>Archivos que no se pudieron borrar:</>');

            foreach ($borrados['no_borrados'] as $ruta) {
                $this->line('    · '.$ruta);
            }
        }

        $limpio = array_sum($despues) === 0;
        $this->newLine();
        $this->line($limpio
            ? '  <fg=green;options=bold>Hecho.</> No queda ninguna actividad.'
            : '  <fg=red;options=bold>Quedan filas.</> Revisa la columna «Quedan».');
        $this->newLine();

        return $limpio ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Cuántas filas cuelgan de las actividades, papelera incluida.
     *
     * @return array<string, int>
     */
    private function recuento(): array
    {
        $ids = Activity::withTrashed()->select('id');
        $evaluaciones = ActivityEvaluation::whereIn('activity_id', Activity::withTrashed()->select('id'))->select('id');

        return [
            'actividades' => Activity::withTrashed()->count(),
            'inscripciones' => Registration::whereIn('activity_id', $ids)->count(),
            'evaluaciones' => ActivityEvaluation::whereIn('activity_id', Activity::withTrashed()->select('id'))->count(),
            'fotos' => EvaluationPhoto::whereIn('activity_evaluation_id', $evaluaciones)->count(),
            'colaboradores' => ActivityCollaborator::whereIn('activity_id', Activity::withTrashed()->select('id'))->count(),
            'historial' => ActivityStatusLog::whereIn('activity_id', Activity::withTrashed()->select('id'))->count(),
            'etiquetas' => DB::table('activity_taxonomy_term')->whereIn('activity_id', Activity::withTrashed()->select('id'))->count(),
        ];
    }

    /**
     * Los archivos que se irían con las actividades, y los que existen de
     * verdad en el disco.
     *
     * @return array{portadas: int, fotos: int, qr: int, compartidas: array<int, string>}
     */
    private function archivos(): array
    {
        $portadas = 0;
        $fotos = 0;
        $qr = 0;
        $compartidas = [];

        Activity::withTrashed()->orderBy('id')->each(function (Activity $actividad) use (&$portadas, &$fotos, &$qr, &$compartidas) {
            [$borrables, $conservadas] = $this->archivosDe($actividad);

            $portadas += count(array_filter($borrables['publico'], fn ($r) => Storage::disk('public')->exists($r)));
            $fotos += count(array_filter($borrables['privado'], fn ($r) => Storage::disk('local')->exists($r)));
            $qr += count(array_filter($borrables['qr'], fn ($r) => Storage::disk('local')->exists($r)));
            array_push($compartidas, ...$conservadas);
        });

        return ['portadas' => $portadas, 'fotos' => $fotos, 'qr' => $qr, 'compartidas' => array_values(array_unique($compartidas))];
    }

    /**
     * Qué archivos son de esta actividad, por disco.
     *
     * La portada sólo se borra si es un archivo que subió el wizard
     * (`storage/actividades/…`) y nada más lo nombra: ni la biblioteca de
     * medios ni otra columna de imagen. Una portada que apunte a `img/…` es del
     * repositorio y no se toca.
     *
     * @return array{0: array{publico: array<int, string>, privado: array<int, string>, qr: array<int, string>}, 1: array<int, string>}
     */
    private function archivosDe(Activity $actividad): array
    {
        $publico = [];
        $conservadas = [];
        $portada = (string) $actividad->imagen_portada;

        if (str_starts_with($portada, self::PREFIJO_PORTADAS)) {
            if ($this->portadaCompartida($portada, $actividad->id)) {
                $conservadas[] = $portada;
            } else {
                $publico[] = substr($portada, strlen('storage/'));
            }
        }

        $evaluaciones = ActivityEvaluation::where('activity_id', $actividad->id)->get(['id', 'foto_path']);

        // La columna vieja `foto_path` sigue rellena en las evaluaciones de antes
        // de las fotos múltiples; suele ser el mismo archivo que la primera fila.
        $privado = EvaluationPhoto::whereIn('activity_evaluation_id', $evaluaciones->pluck('id'))->pluck('ruta')
            ->merge($evaluaciones->pluck('foto_path'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $qr = $actividad->slug ? ['qr-'.$actividad->slug.'.png'] : [];

        return [['publico' => $publico, 'privado' => $privado, 'qr' => $qr], $conservadas];
    }

    /** Si la portada la nombra algo que no es esta actividad. */
    private function portadaCompartida(string $ruta, int $idActividad): bool
    {
        if (Media::withTrashed()->where('ruta', $ruta)->exists()) {
            return true;
        }

        foreach (Biblioteca::COLUMNAS as $sitio) {
            $consulta = DB::table($sitio['tabla'])->where($sitio['columna'], $ruta);

            // Otra actividad con la misma portada se borra igual: no cuenta.
            if ($sitio['tabla'] !== 'activities' && $consulta->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Borra actividad por actividad: las filas dentro de una transacción y los
     * archivos sólo después de que la transacción se confirme, para no dejar
     * filas apuntando a archivos que ya no existen si algo falla a medias.
     *
     * @return array<string, mixed>
     */
    private function borrar(): array
    {
        $total = [
            'actividades' => 0, 'inscripciones' => 0, 'evaluaciones' => 0, 'fotos' => 0,
            'colaboradores' => 0, 'historial' => 0, 'etiquetas' => 0,
            'archivos_portada' => 0, 'archivos_fotos' => 0, 'archivos_qr' => 0,
            'no_borrados' => [],
        ];

        $barra = $this->output->createProgressBar(Activity::withTrashed()->count());

        Activity::withTrashed()->orderBy('id')->chunkById(50, function ($actividades) use (&$total, $barra) {
            foreach ($actividades as $actividad) {
                [$archivos] = $this->archivosDe($actividad);

                DB::transaction(function () use ($actividad, &$total) {
                    $evaluaciones = ActivityEvaluation::where('activity_id', $actividad->id)->get();

                    foreach ($evaluaciones as $evaluacion) {
                        foreach ($evaluacion->fotos()->get() as $foto) {
                            $foto->delete();
                            $total['fotos']++;
                        }

                        $evaluacion->delete();
                        $total['evaluaciones']++;
                    }

                    foreach (Registration::where('activity_id', $actividad->id)->get() as $inscripcion) {
                        $inscripcion->delete();
                        $total['inscripciones']++;
                    }

                    foreach (ActivityCollaborator::where('activity_id', $actividad->id)->get() as $colaborador) {
                        $colaborador->delete();
                        $total['colaboradores']++;
                    }

                    foreach (ActivityStatusLog::where('activity_id', $actividad->id)->get() as $registro) {
                        $registro->delete();
                        $total['historial']++;
                    }

                    $total['etiquetas'] += $actividad->terms()->detach();

                    $actividad->forceDelete();
                    $total['actividades']++;
                });

                foreach (['publico' => 'public', 'privado' => 'local', 'qr' => 'local'] as $tipo => $disco) {
                    foreach ($archivos[$tipo] as $ruta) {
                        if (! Storage::disk($disco)->exists($ruta)) {
                            continue;
                        }

                        if (Storage::disk($disco)->delete($ruta)) {
                            $total[['publico' => 'archivos_portada', 'privado' => 'archivos_fotos', 'qr' => 'archivos_qr'][$tipo]]++;
                        } else {
                            $total['no_borrados'][] = $disco.': '.$ruta;
                        }
                    }
                }

                $barra->advance();
            }
        });

        $barra->finish();
        $this->newLine();

        return $total;
    }

    /** @param  array<string, int>  $recuento */
    private function tablaDeRecuento(array $recuento, array $archivos): void
    {
        $this->table(['Qué', 'Cuántos'], [
            ['Actividades (incluida la papelera)', $recuento['actividades']],
            ['Inscripciones', $recuento['inscripciones']],
            ['Evaluaciones', $recuento['evaluaciones']],
            ['Fotos de evaluación (filas)', $recuento['fotos']],
            ['Colaboradores', $recuento['colaboradores']],
            ['Historial de estados', $recuento['historial']],
            ['Etiquetas de taxonomía (enlaces)', $recuento['etiquetas']],
            ['Archivos de portada en disco', $archivos['portadas']],
            ['Archivos de fotos de evaluación en disco', $archivos['fotos']],
            ['Archivos de QR de prueba en disco', $archivos['qr']],
        ]);
    }
}
