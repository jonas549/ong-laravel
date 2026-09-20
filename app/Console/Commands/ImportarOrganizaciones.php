<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;
use OpenSpout\Reader\CSV\Options as OpcionesCsv;
use OpenSpout\Reader\CSV\Reader as LectorCsv;
use OpenSpout\Reader\XLSX\Reader as LectorXlsx;
use Throwable;

/**
 * Carga el listado histórico de organizaciones participantes.
 *
 * Las crea **sin cuenta**: cada organización llega después al wizard, se
 * encuentra a sí misma en el buscador del paso 3 y se pone su propia
 * contraseña. Es lo que pidió el cliente —«que cada una cree su contraseña, no
 * que se las asignemos a mano»— y por eso `organizations.user_id` pasó a
 * admitir nulos.
 *
 * **Es idempotente y no pisa nada.** Una organización que ya existe se deja
 * como está, tenga cuenta o no: el listado del cliente no puede cambiarle el
 * tipo a quien ya se registró y lo eligió él mismo. Sólo se crean las que
 * faltan.
 *
 * El archivo del cliente todavía no ha llegado, así que esto se prueba con
 * `--ejemplo`, que siembra tres organizaciones inventadas y deja el circuito
 * entero funcionando. Cuando llegue el Excel no hay que tocar código:
 *
 *   php artisan dps:importar-organizaciones ~/listado.xlsx
 *   php artisan dps:importar-organizaciones ~/listado.csv --simular
 *
 * Columnas que lee, por su nombre en la primera fila (en cualquier orden, y
 * sin distinguir mayúsculas ni tildes): `nombre` —la única obligatoria—,
 * `tipo`, `descripcion`, `web`, `correo`.
 */
class ImportarOrganizaciones extends Command
{
    protected $signature = 'dps:importar-organizaciones
        {archivo? : Ruta del .xlsx o .csv con el listado}
        {--simular : Dice qué haría, sin escribir nada}
        {--ejemplo : Usa tres organizaciones de muestra en vez de un archivo}';

    protected $description = 'Carga el listado histórico de organizaciones, sin cuenta, para que cada una la reclame';

    /** Lo que se acepta como cabecera de cada columna. */
    private const COLUMNAS = [
        'nombre' => ['nombre', 'organizacion', 'organización', 'institucion', 'institución'],
        'tipo' => ['tipo', 'tipo de organizacion', 'tipo de organización'],
        'descripcion' => ['descripcion', 'descripción', 'resena', 'reseña'],
        'web' => ['web', 'sitio web', 'url', 'pagina web', 'página web'],
        'correo' => ['correo', 'email', 'correo de contacto', 'mail'],
    ];

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');

        try {
            $filas = $this->option('ejemplo') ? $this->deEjemplo() : $this->delArchivo();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($filas === []) {
            $this->warn('El listado no trae ninguna fila con nombre.');

            return self::SUCCESS;
        }

        $creadas = 0;
        $saltadas = 0;

        foreach ($filas as $fila) {
            $nombre = $this->limpiar($fila['nombre'] ?? '');

            if ($nombre === '') {
                continue;
            }

            /*
             * Se compara sin distinguir mayúsculas y con los espacios ya
             * normalizados. Un listado de Excel trae «  Fundación X » y
             * «Fundación  X» como si fueran dos, y crearía dos filas.
             */
            $existe = Organization::withTrashed()
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                ->first();

            if ($existe) {
                $saltadas++;
                $this->line("  · ya estaba: {$nombre}".($existe->user_id ? ' (con cuenta)' : ' (libre)'));

                continue;
            }

            $tipo = $this->tipoValido($fila['tipo'] ?? '');

            if (! $simular) {
                Organization::create([
                    // Sin dueño: eso es lo que la deja reclamable en el wizard.
                    'user_id' => null,
                    'nombre' => $nombre,
                    'tipo' => $tipo,
                    'tipo_otro' => $tipo === 'Otra' ? $this->limpiar($fila['tipo'] ?? '') ?: null : null,
                    'descripcion' => $this->limpiar($fila['descripcion'] ?? '') ?: null,
                    'enlace_web' => $this->enlace($fila['web'] ?? ''),
                    'correo_contacto' => $this->limpiar($fila['correo'] ?? '') ?: null,
                    /*
                     * Sin verificar a propósito. Estar en el listado histórico
                     * dice que participó, no que alguien haya comprobado
                     * quiénes son hoy; la marca de verificada la pone la ONG.
                     */
                    'verificada' => false,
                    'activo' => true,
                ]);
            }

            $creadas++;
            $this->line("  + {$nombre}  [{$tipo}]");
        }

        $this->newLine();
        $this->info($simular
            ? "Simulación: se crearían {$creadas} organizaciones y se saltarían {$saltadas}."
            : "Listo: {$creadas} organizaciones nuevas, {$saltadas} que ya estaban.");

        if ($creadas > 0 && ! $simular) {
            $this->line('Cada una puede reclamarse desde el buscador del paso 3 de «Publica tu actividad».');
        }

        return self::SUCCESS;
    }

    /* ── Lectura ─────────────────────────────────────────── */

    /** @return array<int, array<string, string>> */
    private function delArchivo(): array
    {
        $ruta = (string) $this->argument('archivo');

        if ($ruta === '') {
            throw new \RuntimeException(
                'Falta la ruta del archivo. Para probar el circuito sin él: php artisan dps:importar-organizaciones --ejemplo'
            );
        }

        if (! is_file($ruta)) {
            throw new \RuntimeException("No encuentro el archivo: {$ruta}");
        }

        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

        $lector = match ($extension) {
            'csv' => new LectorCsv($this->opcionesCsv($ruta)),
            'xlsx' => new LectorXlsx,
            default => throw new \RuntimeException("No sé leer un .{$extension}. Guarda el listado como .xlsx o .csv."),
        };

        $lector->open($ruta);

        $filas = [];
        $mapa = null;

        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                // `toArray()` y no `getCells()`: esta versión de OpenSpout no
                // expone las celdas, sólo sus valores ya resueltos.
                $celdas = array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $fila->toArray());

                // La primera fila con contenido son las cabeceras.
                if ($mapa === null) {
                    $mapa = $this->mapear($celdas);

                    if (! isset($mapa['nombre'])) {
                        $lector->close();

                        throw new \RuntimeException(
                            'La primera fila tiene que traer una columna «nombre». Encontré: '.implode(' · ', $celdas)
                        );
                    }

                    continue;
                }

                $registro = [];

                foreach ($mapa as $campo => $indice) {
                    $registro[$campo] = $celdas[$indice] ?? '';
                }

                $filas[] = $registro;
            }

            // Sólo la primera hoja: un listado repartido en varias es otra cosa.
            break;
        }

        $lector->close();

        return $filas;
    }

    /**
     * Coma o punto y coma, según lo que traiga el archivo.
     *
     * No es un detalle: Excel en español guarda con punto y coma, que es
     * también con lo que exporta este panel, y OpenSpout lee con coma por
     * defecto. Sin mirarlo, un CSV del cliente llega con la fila entera metida
     * en la primera celda y el comando se queja de que falta la columna
     * «nombre» cuando está ahí delante.
     */
    private function opcionesCsv(string $ruta): OpcionesCsv
    {
        $primera = '';
        $mango = fopen($ruta, 'rb');

        if ($mango) {
            $primera = (string) fgets($mango, 8192);
            fclose($mango);
        }

        return new OpcionesCsv(
            FIELD_DELIMITER: substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ','
        );
    }

    /**
     * Qué columna es cada una, por su cabecera.
     *
     * @param  array<int, string>  $cabeceras
     * @return array<string, int>
     */
    private function mapear(array $cabeceras): array
    {
        $mapa = [];

        foreach ($cabeceras as $indice => $cabecera) {
            $limpia = $this->sinTildes(mb_strtolower(trim($cabecera)));

            foreach (self::COLUMNAS as $campo => $alias) {
                if (isset($mapa[$campo])) {
                    continue;
                }

                foreach ($alias as $nombre) {
                    if ($limpia === $this->sinTildes($nombre)) {
                        $mapa[$campo] = $indice;

                        break 2;
                    }
                }
            }
        }

        return $mapa;
    }

    /** @return array<int, array<string, string>> */
    private function deEjemplo(): array
    {
        $this->warn('Modo ejemplo: tres organizaciones de muestra, para probar el circuito sin el archivo del cliente.');
        $this->newLine();

        return [
            ['nombre' => 'Fundación Manos del Sur', 'tipo' => 'Organización sin fines de lucro', 'web' => 'manosdelsur.cl'],
            ['nombre' => 'Corporación Raíces de Barrio', 'tipo' => 'Organización sin fines de lucro', 'web' => ''],
            ['nombre' => 'Liceo Bicentenario Los Andes', 'tipo' => 'Institución educativa', 'web' => ''],
        ];
    }

    /* ── Limpieza ────────────────────────────────────────── */

    private function limpiar(string $valor): string
    {
        return trim(preg_replace('/\s+/u', ' ', $valor) ?? '');
    }

    /**
     * El tipo, si coincide con uno de los cinco; si no, «Otra».
     *
     * No se inventa ni se adivina: un listado de Excel trae cosas como «ONG» o
     * «Junta de vecinos», y meterlas como si fueran uno de los tipos del
     * formulario rompería los filtros del panel.
     */
    private function tipoValido(string $valor): string
    {
        $limpio = $this->sinTildes(mb_strtolower($this->limpiar($valor)));

        foreach (Organization::TIPOS as $tipo) {
            if ($this->sinTildes(mb_strtolower($tipo)) === $limpio) {
                return $tipo;
            }
        }

        return 'Otra';
    }

    /** Un enlace con esquema, o nada. El sitio nunca pinta un href a medias. */
    private function enlace(string $valor): ?string
    {
        $limpio = $this->limpiar($valor);

        if ($limpio === '') {
            return null;
        }

        return str_starts_with($limpio, 'http://') || str_starts_with($limpio, 'https://')
            ? $limpio
            : 'https://'.$limpio;
    }

    private function sinTildes(string $texto): string
    {
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }
}
