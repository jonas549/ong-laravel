<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Support\Enlace;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
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
 * **Es idempotente y no pisa nada**: se puede correr dos veces. El listado
 * del cliente no puede cambiarle el tipo a quien ya se registró y lo eligió
 * él mismo (ver más abajo qué se hace con las que ya existen).
 *
 *   php artisan dps:importar-organizaciones ~/listado.csv --logos=~/logos --simular
 *   php artisan dps:importar-organizaciones ~/listado.csv --logos=~/logos
 *
 * `--ejemplo` siembra tres organizaciones inventadas, para probar el circuito
 * sin archivo.
 *
 * Columnas que lee, por su nombre en la primera fila (en cualquier orden, y
 * sin distinguir mayúsculas ni tildes): `nombre` —la única obligatoria—,
 * `tipo`, `detalle_cliente`, `descripcion`, `web`, `correo`,
 * `anios_participacion` y `logo_archivo`.
 *
 * **Los logos van aparte**, en una carpeta que se pasa con `--logos`: cada
 * fila busca ahí el archivo de su `logo_archivo` —por el nombre sin extensión,
 * porque un PDF del cliente llega convertido a PNG— y se copia al disco
 * público como si lo hubiera subido la propia organización. La carpeta ya
 * tiene que venir optimizada: aquí no se reduce nada.
 *
 * Una organización que ya existe **con cuenta** no se toca, salvo para
 * apuntarle de qué ediciones viene si ese dato está vacío: es lo que la mete
 * en la marquesina, y no pisa nada que haya escrito ella. Una que ya existe
 * **sin cuenta** —de una importación anterior— se completa en lo que tenga
 * vacío, sin cambiar lo que ya tenga.
 */
class ImportarOrganizaciones extends Command
{
    protected $signature = 'dps:importar-organizaciones
        {archivo? : Ruta del .xlsx o .csv con el listado}
        {--logos= : Carpeta con los logos ya optimizados, por su logo_archivo}
        {--simular : Dice qué haría, sin escribir nada}
        {--ejemplo : Usa tres organizaciones de muestra en vez de un archivo}';

    protected $description = 'Carga el listado histórico de organizaciones, sin cuenta, para que cada una la reclame';

    /** Lo que se acepta como cabecera de cada columna. */
    private const COLUMNAS = [
        'nombre' => ['nombre', 'organizacion', 'organización', 'institucion', 'institución'],
        'tipo' => ['tipo', 'tipo de organizacion', 'tipo de organización'],
        'detalle' => ['detalle_cliente', 'detalle', 'clasificacion', 'clasificación'],
        'descripcion' => ['descripcion', 'descripción', 'resena', 'reseña'],
        'web' => ['web', 'sitio web', 'sitio_web', 'url', 'pagina web', 'página web'],
        'correo' => ['correo', 'email', 'correo de contacto', 'mail'],
        'anios' => ['anios_participacion', 'años de participación', 'anios de participacion', 'participacion'],
        'logo' => ['logo_archivo', 'logo'],
    ];

    /**
     * La clasificación del cliente que sí dice qué tipo de organización es.
     *
     * El resto —«Socia», «No socia», «Exsocia», «Organizaciones de base»…—
     * habla de su relación con la red, no de qué son, y se queda sin tipo.
     */
    private const TIPO_POR_DETALLE = [
        'instituciones educativas' => 'Institución educativa',
        'sector publico' => 'Municipalidad u organismo público',
        'empresa participante de actividad' => 'Empresa o institución privada',
        'empresa organizadora de actividad' => 'Empresa o institución privada',
    ];

    /** Lo que el sitio acepta como logo, en el orden en que se busca. */
    private const EXTENSIONES_LOGO = ['png', 'jpg', 'jpeg', 'webp'];

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

        $carpetaLogos = $this->option('logos') ? rtrim((string) $this->option('logos'), '/\\') : null;

        if ($carpetaLogos !== null && ! is_dir($carpetaLogos)) {
            $this->error("No encuentro la carpeta de logos: {$carpetaLogos}");

            return self::FAILURE;
        }

        $cuenta = ['creadas' => 0, 'con_logo' => 0, 'libres_completadas' => 0, 'con_cuenta' => 0, 'borradas' => 0, 'repetidas' => 0];
        $sinLogo = [];
        $vistos = [];

        foreach ($filas as $fila) {
            $nombre = $this->limpiar($fila['nombre'] ?? '');

            if ($nombre === '') {
                continue;
            }

            // El mismo nombre dos veces en el propio listado: la segunda sobra.
            $clave = mb_strtolower($nombre);

            if (isset($vistos[$clave])) {
                $cuenta['repetidas']++;
                $this->line("  = repetida en el listado: {$nombre}");

                continue;
            }

            $vistos[$clave] = true;

            $logo = $carpetaLogos ? $this->buscarLogo($carpetaLogos, $fila['logo'] ?? '') : null;
            $anios = $this->limpiar($fila['anios'] ?? '') ?: null;

            /*
             * Se compara sin distinguir mayúsculas y con los espacios ya
             * normalizados. Un listado de Excel trae «  Fundación X » y
             * «Fundación  X» como si fueran dos, y crearía dos filas.
             */
            $existe = Organization::withTrashed()
                ->whereRaw('LOWER(nombre) = ?', [$clave])
                ->first();

            if ($existe?->trashed()) {
                // Borrada desde el panel: alguien decidió quitarla, y el
                // listado no la resucita.
                $cuenta['borradas']++;
                $this->line("  · borrada en el panel, no se toca: {$nombre}");

                continue;
            }

            if ($existe && $existe->user_id) {
                $cuenta['con_cuenta']++;
                $apuntar = $anios && blank($existe->anios_participacion);

                if ($apuntar && ! $simular) {
                    $existe->update(['anios_participacion' => $anios]);
                }

                $this->line("  · con cuenta, no se toca: {$nombre}".($apuntar ? '  (sólo se le apuntan las ediciones)' : ''));

                continue;
            }

            if ($existe) {
                // Libre, de una importación anterior: se completa lo vacío.
                $enlaces = $this->enlaces($fila['web'] ?? '');
                $faltan = array_filter([
                    'tipo' => blank($existe->tipo) ? $this->tipo($fila) : null,
                    'enlace_web' => blank($existe->enlace_web) ? $enlaces['enlace_web'] : null,
                    'enlace_red_social' => blank($existe->enlace_red_social) ? $enlaces['enlace_red_social'] : null,
                    'anios_participacion' => blank($existe->anios_participacion) ? $anios : null,
                    'logo_path' => blank($existe->logo_path) && $logo && ! $simular ? $this->copiarLogo($logo) : null,
                ]);

                if ($faltan && ! $simular) {
                    $existe->update($faltan);
                }

                $cuenta['libres_completadas']++;
                $this->line("  · libre, se completa lo vacío: {$nombre}");

                continue;
            }

            $tipo = $this->tipo($fila);

            if (! $simular) {
                Organization::create([
                    // Sin dueño: eso es lo que la deja reclamable en el wizard.
                    'user_id' => null,
                    'nombre' => $nombre,
                    'tipo' => $tipo,
                    'tipo_otro' => $tipo === 'Otra' ? $this->limpiar($fila['tipo'] ?? '') ?: null : null,
                    'descripcion' => $this->limpiar($fila['descripcion'] ?? '') ?: null,
                    ...$this->enlaces($fila['web'] ?? ''),
                    'correo_contacto' => $this->limpiar($fila['correo'] ?? '') ?: null,
                    'anios_participacion' => $anios,
                    'logo_path' => $logo ? $this->copiarLogo($logo) : null,
                    /*
                     * Sin verificar a propósito. Estar en el listado histórico
                     * dice que participó, no que alguien haya comprobado
                     * quiénes son hoy; la marca de verificada la pone la ONG.
                     */
                    'verificada' => false,
                    'activo' => true,
                ]);
            }

            $cuenta['creadas']++;

            if ($logo) {
                $cuenta['con_logo']++;
            } else {
                $sinLogo[] = $nombre;
            }

            $this->line("  + {$nombre}  [".($tipo ?? 'sin tipo').']'.($logo ? '' : '  (sin logo)'));
        }

        $this->newLine();
        $this->info(($simular ? 'Simulación: se crearían' : 'Listo:')
            ." {$cuenta['creadas']} organizaciones nuevas ({$cuenta['con_logo']} con logo)."
            ." Ya estaban: {$cuenta['con_cuenta']} con cuenta, {$cuenta['libres_completadas']} libres,"
            ." {$cuenta['borradas']} borradas. Repetidas en el listado: {$cuenta['repetidas']}.");

        if ($carpetaLogos !== null && $sinLogo !== []) {
            $this->warn('Nuevas sin logo ('.count($sinLogo).'): '.implode(' · ', $sinLogo));
        }

        if ($cuenta['creadas'] > 0 && ! $simular) {
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

    /**
     * El tipo de la fila: el de la columna `tipo` si viene, y si no, el que
     * se deduzca de la clasificación del cliente. Si ninguna de las dos lo
     * dice, ninguno —ver `TIPO_POR_DETALLE`—.
     */
    private function tipo(array $fila): ?string
    {
        if ($this->limpiar($fila['tipo'] ?? '') !== '') {
            return $this->tipoValido($fila['tipo']);
        }

        $detalle = $this->sinTildes(mb_strtolower($this->limpiar($fila['detalle'] ?? '')));

        return self::TIPO_POR_DETALLE[$detalle] ?? null;
    }

    /**
     * Un enlace con esquema, o nada. El sitio nunca pinta un href a medias.
     *
     * Con la misma normalización que los formularios (`Enlace`), y lo que
     * aun así no sea una dirección web se descarta en vez de guardarse: aquí
     * no hay un campo donde enseñar el error.
     */
    private function enlace(string $valor): ?string
    {
        $enlace = Enlace::normalizar($this->limpiar($valor));

        return is_string($enlace) && preg_match('#^https?://[^\s/.]+\.[^\s]+#i', $enlace) ? $enlace : null;
    }

    /**
     * El enlace en su campo: un Instagram o un Facebook escrito como «sitio
     * web» es la red social de la organización, y la ficha la pinta con su
     * propio botón.
     *
     * @return array{enlace_web: ?string, enlace_red_social: ?string}
     */
    private function enlaces(string $valor): array
    {
        $enlace = $this->enlace($valor);
        $red = $enlace && preg_match('#^https?://([a-z0-9-]+\.)*(instagram|facebook|fb|linkedin|tiktok|twitter|x)\.com/#i', $enlace);

        return [
            'enlace_web' => $red ? null : $enlace,
            'enlace_red_social' => $red ? $enlace : null,
        ];
    }

    /** El archivo del logo en la carpeta, por su nombre sin extensión. */
    private function buscarLogo(string $carpeta, string $archivo): ?string
    {
        $base = pathinfo($this->limpiar($archivo), PATHINFO_FILENAME);

        if ($base === '') {
            return null;
        }

        foreach (self::EXTENSIONES_LOGO as $extension) {
            $ruta = "{$carpeta}/{$base}.{$extension}";

            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    /**
     * Copia el logo al disco público, donde deja los suyos el wizard, y
     * devuelve la ruta tal como la guarda `logo_path`.
     *
     * Con el nombre legible del listado, y un sufijo si ya hay uno igual: un
     * nombre que choque no puede pisar el logo de otra organización.
     */
    private function copiarLogo(string $ruta): string
    {
        $disco = Storage::disk('public');
        $base = pathinfo($ruta, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $nombre = "{$base}.{$extension}";

        for ($n = 2; $disco->exists("organizaciones/{$nombre}"); $n++) {
            $nombre = "{$base}-{$n}.{$extension}";
        }

        $disco->putFileAs('organizaciones', new File($ruta), $nombre);

        return "storage/organizaciones/{$nombre}";
    }

    private function sinTildes(string $texto): string
    {
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }
}
