<?php

namespace App\Support\VoluntariadosChile;

use App\Models\Commune;
use App\Models\Region;
use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Compara una respuesta de la API de Voluntariados Chile con su contrato.
 *
 * Responde a lo que hace falta saber en una reunión con quien la mantiene:
 * qué campos documentados no llegan, cuáles llegan vacíos, cuáles llegan y no
 * estaban documentados, y qué valores trae de verdad lo que todavía no está
 * cerrado —el formato, las categorías, las áreas, las regiones— comparados con
 * los catálogos de este sitio.
 *
 * No corrige nada ni decide nada: describe. Un campo que el contrato admite
 * vacío y viene vacío no es un problema, pero se cuenta, porque «¿cuántas
 * vienen sin imagen?» es justo el tipo de pregunta que hay que llevar.
 */
class RevisorDeRespuesta
{
    /** @var array<string, array<string, mixed>> */
    private array $campos = [];

    private array $noDocumentados = [];

    private array $coherencia = [];

    private array $catalogos = [];

    private array $sobre = [];

    /**
     * @param  int|null  $http  el código HTTP de la respuesta
     * @param  mixed  $json  el cuerpo ya decodificado (array asociativo), o null si no era JSON
     */
    public function revisar(?int $http, mixed $json): array
    {
        $this->campos = $this->noDocumentados = $this->coherencia = $this->catalogos = $this->sobre = [];

        if (! is_array($json)) {
            $this->sobre[] = ['grave' => true, 'texto' => 'La respuesta no es JSON, o no se pudo leer como JSON.'];

            return $this->informe(0);
        }

        if ($http !== 200 || array_key_exists('error', $json)) {
            $this->revisarError($http, $json);

            return $this->informe(0);
        }

        $this->revisarObjeto($json, Contrato::sobre(), '', null);
        $this->noDocumentadosEn($json, Contrato::sobre(), '', ['data'], null);

        $datos = is_array($json['data'] ?? null) && array_is_list($json['data']) ? $json['data'] : [];
        $this->revisarPaginacion($json, $datos);

        $ids = [];
        foreach ($datos as $i => $item) {
            if (! is_array($item) || array_is_list($item)) {
                $this->sobre[] = ['grave' => true, 'texto' => "El elemento {$i} de data no es un objeto."];

                continue;
            }

            $id = is_string($item['id'] ?? null) ? $item['id'] : "#{$i}";
            $ids[] = $id;

            $this->revisarObjeto($item, Contrato::oportunidad(), '', $id);
            $this->noDocumentadosEn($item, Contrato::oportunidad(), '', [], $id);
            $this->revisarCoherencia($item, $id);
            $this->apuntarCatalogos($item);
        }

        foreach (array_count_values($ids) as $id => $veces) {
            if ($veces > 1) {
                $this->sobre[] = ['grave' => true, 'texto' => "El id {$id} viene {$veces} veces en la misma página."];
            }
        }

        return $this->informe(count($datos));
    }

    /* ───────────────────────────────────────────────────── el error ── */

    private function revisarError(?int $http, array $json): void
    {
        $error = $json['error'] ?? null;

        if (! is_array($error) || ! is_string($error['code'] ?? null) || ! is_string($error['message'] ?? null)) {
            $this->sobre[] = ['grave' => true, 'texto' => 'Es un error, pero no trae el formato documentado { "error": { "code", "message" } }.'];

            return;
        }

        $esperado = Contrato::ERRORES[$http] ?? null;

        if ($esperado === null) {
            $this->sobre[] = ['grave' => false, 'texto' => "El código HTTP {$http} no está entre los documentados (400, 401, 500)."];
        } elseif ($error['code'] !== $esperado) {
            $this->sobre[] = ['grave' => false, 'texto' => "Con HTTP {$http} la documentación dice code «{$esperado}» y llegó «{$error['code']}»."];
        } else {
            $this->sobre[] = ['grave' => false, 'texto' => "Error con el formato documentado: HTTP {$http}, code «{$error['code']}»."];
        }

        $extra = array_diff(array_keys($error), ['code', 'message']);
        if ($extra) {
            $this->sobre[] = ['grave' => false, 'texto' => 'El error trae campos no documentados: '.implode(', ', $extra).'.'];
        }
    }

    /* ─────────────────────────────────────────── campo por campo ── */

    /**
     * @param  array<string, array<string, mixed>>  $contrato
     */
    private function revisarObjeto(array $objeto, array $contrato, string $base, ?string $id): void
    {
        foreach ($contrato as $ruta => $regla) {
            if (str_contains($ruta, '[]')) {
                continue; // los elementos de una lista se miran con la lista
            }

            $padre = Str::contains($ruta, '.') ? Str::beforeLast($ruta, '.') : null;

            // Si falta el padre, el aviso ya está en el padre: no se repite
            // en cada uno de sus hijos.
            if ($padre !== null && ! is_array(data_get($objeto, $padre))) {
                continue;
            }

            [$existe, $valor] = $this->leer($objeto, $ruta);

            if (! $existe) {
                $this->anotar($base.$ruta, 'falta', $id);

                continue;
            }

            if ($this->vacio($valor)) {
                $permitido = ($regla['nulo'] ?? false) || (($regla['vacio'] ?? false) && $valor === []);
                $this->anotar($base.$ruta, $permitido ? 'vacio_permitido' : 'vacio', $id);

                continue;
            }

            if (! $this->esDelTipo($valor, $regla['tipo'])) {
                $this->anotar($base.$ruta, 'tipo', $id, 'se esperaba '.$regla['tipo'].', llegó '.$this->tipoDe($valor));

                continue;
            }

            if (isset($regla['valores']) && ! in_array($valor, $regla['valores'], true)) {
                $this->anotar($base.$ruta, 'valor', $id, '«'.$this->corto($valor).'» no es ninguno de: '.implode(', ', $regla['valores']));
            }

            if ($regla['tipo'] === 'lista') {
                if (isset($regla['max']) && count($valor) > $regla['max']) {
                    $this->anotar($base.$ruta, 'valor', $id, count($valor).' elementos; la documentación dice hasta '.$regla['max']);
                }

                $this->revisarElementos($valor, $contrato, $ruta, $id);
            }
        }
    }

    private function revisarElementos(array $lista, array $contrato, string $ruta, ?string $id): void
    {
        $directo = $contrato[$ruta.'[]'] ?? null;

        $hijos = [];
        foreach ($contrato as $r => $regla) {
            if (str_starts_with($r, $ruta.'[].')) {
                $hijos[substr($r, strlen($ruta.'[].'))] = $regla;
            }
        }

        foreach ($lista as $elemento) {
            if ($directo) {
                if ($this->vacio($elemento)) {
                    $this->anotar($ruta.'[]', 'vacio', $id);
                } elseif (! $this->esDelTipo($elemento, $directo['tipo'])) {
                    $this->anotar($ruta.'[]', 'tipo', $id, 'se esperaba '.$directo['tipo'].', llegó '.$this->tipoDe($elemento));
                }
            }

            if ($hijos && is_array($elemento)) {
                $this->revisarObjeto($elemento, $hijos, $ruta.'[].', $id);
            }
        }
    }

    /**
     * Lo que llega y el contrato no menciona, a cualquier profundidad.
     *
     * @param  array<int, string>  $saltar  ramas que se revisan aparte (data)
     */
    private function noDocumentadosEn(array $objeto, array $contrato, string $base, array $saltar, ?string $id): void
    {
        foreach ($objeto as $clave => $valor) {
            $ruta = $base.$clave;

            if (in_array($ruta, $saltar, true)) {
                continue;
            }

            $documentada = array_key_exists($ruta, $contrato);

            if (! $documentada) {
                $this->noDocumentados[$ruta] ??= ['ruta' => $ruta, 'veces' => 0, 'ejemplo' => $this->corto($valor)];
                $this->noDocumentados[$ruta]['veces']++;

                continue;
            }

            if (is_array($valor) && ! array_is_list($valor)) {
                $this->noDocumentadosEn($valor, $contrato, $ruta.'.', $saltar, $id);
            } elseif (is_array($valor)) {
                foreach ($valor as $elemento) {
                    if (is_array($elemento) && ! array_is_list($elemento)) {
                        $this->noDocumentadosEn($elemento, $contrato, $ruta.'[].', $saltar, $id);
                    }
                }
            }
        }
    }

    /* ──────────────────────────────────── lo que no es de tipos ── */

    private function revisarPaginacion(array $json, array $datos): void
    {
        $p = $json['pagination'] ?? null;

        if (! is_array($p)) {
            return;
        }

        $pagina = $p['page'] ?? null;
        $tam = $p['page_size'] ?? null;
        $total = $p['total'] ?? null;

        if (is_int($tam) && count($datos) > $tam) {
            $this->sobre[] = ['grave' => true, 'texto' => 'Llegan '.count($datos)." oportunidades con page_size {$tam}."];
        }

        if (is_int($pagina) && is_int($tam) && is_int($total)) {
            $esperadas = max(0, min($tam, $total - ($pagina - 1) * $tam));

            if (count($datos) !== $esperadas) {
                $this->sobre[] = ['grave' => true, 'texto' => "Con total {$total}, página {$pagina} y page_size {$tam} tendrían que llegar {$esperadas}, y llegan ".count($datos).'.'];
            }

            $paginas = (int) ceil($total / max(1, $tam));
            $this->sobre[] = ['grave' => false, 'texto' => "Total {$total} oportunidades: ".($paginas <= 1 ? 'caben en una sola página.' : "{$paginas} páginas de {$tam}.")];
        }
    }

    private function revisarCoherencia(array $item, string $id): void
    {
        $tipo = data_get($item, 'schedule.type');
        $inicio = data_get($item, 'schedule.start_date');
        $fin = data_get($item, 'schedule.end_date');

        if ($tipo === 'fixed_range' && (! $inicio || ! $fin)) {
            $this->avisar($id, 'Es «fixed_range» pero le falta la fecha de inicio o la de término.', true);
        }

        if ($tipo === 'continuous' && ($inicio || $fin)) {
            $this->avisar($id, 'Es «continuous» y aun así trae fechas.', false);
        }

        if (is_string($inicio) && is_string($fin) && $fin < $inicio) {
            $this->avisar($id, "Termina ({$fin}) antes de empezar ({$inicio}).", true);
        }

        if ($tipo === 'fixed_range' && is_string($fin) && $fin < now()->toDateString()) {
            $this->avisar($id, "Su rango terminó el {$fin} y sigue saliendo como abierta.", false);
        }

        foreach (['minimum_age', 'certificate', 'profession_or_study', 'prior_experience'] as $req) {
            $requerido = data_get($item, "requirements.{$req}.required");
            $detalle = data_get($item, "requirements.{$req}.detail");

            if ($requerido === false && filled($detalle)) {
                $this->avisar($id, "requirements.{$req}: dice que no se exige y trae detalle «{$this->corto($detalle)}». ¿Se muestra o no?", false);
            }
        }

        $url = data_get($item, 'opportunity_url');
        if (is_string($url) && is_string($item['id'] ?? null) && ! str_contains($url, $item['id'])) {
            $this->avisar($id, 'El opportunity_url no contiene el id de la oportunidad.', false);
        }

        $texto = mb_strtolower(trim(data_get($item, 'title', '').' '.data_get($item, 'description', '')));
        if (preg_match('/\b(prueba|test|demo)\b/u', $texto)) {
            $this->avisar($id, 'Parece un registro de prueba («'.$this->corto(data_get($item, 'title')).'»), y llega por el endpoint de producción.', false);
        }
    }

    /** Los valores reales de lo que todavía no está cerrado, para llevarlos a la reunión. */
    private function apuntarCatalogos(array $item): void
    {
        $cuenta = function (string $grupo, ?string $clave, ?string $etiqueta = null) {
            if ($clave === null || $clave === '') {
                return;
            }
            $this->catalogos[$grupo][$clave] ??= ['valor' => $clave, 'nombre' => $etiqueta, 'veces' => 0];
            $this->catalogos[$grupo][$clave]['veces']++;
        };

        $formato = data_get($item, 'format');
        if (is_string($formato)) {
            $cuenta('format', $formato, match (true) {
                in_array($formato, Contrato::FORMATOS_OBSERVADOS, true) => 'ya observado',
                in_array($formato, Contrato::FORMATOS_DOCUMENTADOS, true) => 'documentado',
                default => 'NUEVO',
            });
        }

        $cuenta('schedule.type', is_string(data_get($item, 'schedule.type')) ? data_get($item, 'schedule.type') : null);

        if (is_string(data_get($item, 'category.code'))) {
            $cuenta('category', data_get($item, 'category.code'), (string) data_get($item, 'category.name'));
        }

        foreach ((array) data_get($item, 'organization.impact_areas', []) as $area) {
            if (is_array($area) && is_string($area['code'] ?? null)) {
                $cuenta('impact_areas', $area['code'], (string) ($area['name'] ?? ''));
            }
        }

        if (is_string(data_get($item, 'organization.type'))) {
            $cuenta('organization.type', data_get($item, 'organization.type'));
        }

        foreach ((array) data_get($item, 'location.regions', []) as $region) {
            if (is_string($region)) {
                $cuenta('regions', $region, $this->regionNuestra($region));
            }
        }

        foreach ((array) data_get($item, 'location.comunas', []) as $comuna) {
            if (is_string($comuna)) {
                $cuenta('comunas', $comuna, $this->comunaNuestra($comuna));
            }
        }
    }

    /* ───────────────────────────────────── contra lo que tenemos ── */

    private ?array $regiones = null;

    private ?array $comunas = null;

    private function normal(string $texto): string
    {
        $t = Str::of($texto)->ascii()->lower()->replaceMatches('/[^a-z0-9 ]+/', ' ')->squish()->value();

        return trim(preg_replace('/^region (de |del )?/', '', $t));
    }

    /** «igual: X», «parecida: X» o «sin equivalente». */
    private function regionNuestra(string $suya): string
    {
        $this->regiones ??= Region::query()->pluck('nombre')->all();
        $n = $this->normal($suya);

        foreach ($this->regiones as $nuestra) {
            if ($this->normal($nuestra) === $n) {
                return "igual a la nuestra «{$nuestra}»";
            }
        }

        foreach ($this->regiones as $nuestra) {
            $m = $this->normal($nuestra);
            if ($n !== '' && (str_contains($m, $n) || str_contains($n, $m))) {
                return "parecida a la nuestra «{$nuestra}» (hace falta equivalencia)";
            }
        }

        return 'SIN EQUIVALENTE en nuestro catálogo';
    }

    private function comunaNuestra(string $suya): string
    {
        $this->comunas ??= Commune::query()->pluck('nombre')->mapWithKeys(fn ($c) => [$this->normal($c) => $c])->all();

        return isset($this->comunas[$this->normal($suya)])
            ? "igual a la nuestra «{$this->comunas[$this->normal($suya)]}»"
            : 'SIN EQUIVALENTE en nuestro catálogo';
    }

    /* ─────────────────────────────────────────────── utilidades ── */

    /** @return array{0: bool, 1: mixed} */
    private function leer(array $objeto, string $ruta): array
    {
        $actual = $objeto;

        foreach (explode('.', $ruta) as $parte) {
            if (! is_array($actual) || ! array_key_exists($parte, $actual)) {
                return [false, null];
            }
            $actual = $actual[$parte];
        }

        return [true, $actual];
    }

    private function vacio(mixed $v): bool
    {
        return $v === null || $v === [] || (is_string($v) && trim($v) === '');
    }

    private function esDelTipo(mixed $v, string $tipo): bool
    {
        return match ($tipo) {
            'texto' => is_string($v),
            'numero' => is_int($v) || is_float($v),
            'bool' => is_bool($v),
            'objeto' => is_array($v) && ! array_is_list($v),
            'lista' => is_array($v) && array_is_list($v),
            'url' => is_string($v) && filter_var($v, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $v),
            'correo' => is_string($v) && filter_var($v, FILTER_VALIDATE_EMAIL) !== false,
            'fecha' => is_string($v) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]),
            'iso' => is_string($v)
                && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/', $v)
                && (bool) $this->fechaHora($v),
            default => true,
        };
    }

    private function fechaHora(string $v): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($v);
        } catch (\Throwable) {
            return null;
        }
    }

    private function tipoDe(mixed $v): string
    {
        return match (true) {
            is_string($v) => 'texto «'.$this->corto($v).'»',
            is_bool($v) => 'bool',
            is_int($v), is_float($v) => 'número',
            is_array($v) && array_is_list($v) => 'lista',
            is_array($v) => 'objeto',
            default => gettype($v),
        };
    }

    private function corto(mixed $v): string
    {
        $t = is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return Str::limit((string) $t, 60);
    }

    private function anotar(string $ruta, string $que, ?string $id, ?string $detalle = null): void
    {
        $this->campos[$ruta] ??= ['ruta' => $ruta, 'falta' => 0, 'vacio' => 0, 'vacio_permitido' => 0, 'tipo' => 0, 'valor' => 0, 'ids' => [], 'detalles' => []];
        $this->campos[$ruta][$que]++;

        if ($id !== null && count($this->campos[$ruta]['ids']) < 5 && ! in_array($id, $this->campos[$ruta]['ids'], true)) {
            $this->campos[$ruta]['ids'][] = $id;
        }

        if ($detalle !== null && count($this->campos[$ruta]['detalles']) < 3 && ! in_array($detalle, $this->campos[$ruta]['detalles'], true)) {
            $this->campos[$ruta]['detalles'][] = $detalle;
        }
    }

    private function avisar(string $id, string $texto, bool $grave): void
    {
        $this->coherencia[] = ['id' => $id, 'texto' => $texto, 'grave' => $grave];
    }

    private function informe(int $oportunidades): array
    {
        $campos = array_values($this->campos);
        usort($campos, fn ($a, $b) => ($b['falta'] + $b['vacio'] + $b['tipo'] + $b['valor']) <=> ($a['falta'] + $a['vacio'] + $a['tipo'] + $a['valor']) ?: strcmp($a['ruta'], $b['ruta']));

        $problemas = array_sum(array_map(fn ($c) => $c['falta'] + $c['vacio'] + $c['tipo'] + $c['valor'], $campos))
            + count(array_filter($this->sobre, fn ($s) => $s['grave']))
            + count(array_filter($this->coherencia, fn ($s) => $s['grave']));

        $catalogos = [];
        foreach ($this->catalogos as $grupo => $valores) {
            $catalogos[$grupo] = array_values($valores);
        }

        return [
            'oportunidades' => $oportunidades,
            'problemas' => $problemas,
            'sobre' => $this->sobre,
            'campos' => $campos,
            'no_documentados' => array_values($this->noDocumentados),
            'coherencia' => $this->coherencia,
            'catalogos' => $catalogos,
        ];
    }
}
