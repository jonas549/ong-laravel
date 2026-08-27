<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Lo que necesita un listado del panel: ordenar por columna y paginar.
 *
 * El compañero de `Filtro`, que ya resuelve la lectura de los parámetros.
 *
 * **La lista de columnas ordenables es obligatoria y no tiene valor por
 * defecto.** Un `orderBy($request->input('orden'))` mete lo que llegue por la
 * URL dentro de la consulta, y eso no es un listado con orden: es una inyección
 * SQL con formulario. Aquí, lo que no está en la lista blanca no ordena nada y
 * se cae al orden de siempre, en silencio y sin romper la pantalla.
 */
class Listado
{
    /** Cuántas filas por página cuando nadie dice otra cosa. */
    public const POR_PAGINA = 25;

    /** Los tamaños de página que se admiten desde la URL. */
    private const TAMANOS = [10, 25, 50, 100];

    /**
     * Ordena una consulta por lo que pida la URL, dentro de lo permitido.
     *
     * `$permitidas` mapea el nombre que viaja en la URL a la columna real, para
     * que la tabla no tenga que exponer sus nombres de columna. Una lista sin
     * claves —`['titulo', 'orden']`— vale cuando coinciden.
     *
     * @param  array<int|string, string>  $permitidas
     */
    public static function ordenar(Builder $consulta, Request $request, array $permitidas, string $porDefecto, string $direccionPorDefecto = 'asc'): Builder
    {
        [$campo, $direccion] = static::orden($request, $permitidas, $porDefecto, $direccionPorDefecto);

        $columna = static::columnaReal($permitidas, $campo) ?? static::columnaReal($permitidas, $porDefecto) ?? $porDefecto;

        /*
         * Se desempata siempre por `id`. Sin esto, ordenar por una columna con
         * valores repetidos —el `orden` de las secciones, un estado— deja el
         * reparto entre páginas a criterio del motor, y una misma fila puede
         * salir en la página 1 y en la 2, o en ninguna.
         */
        return $consulta->orderBy($columna, $direccion)->orderBy(
            $consulta->getModel()?->getQualifiedKeyName() ?? 'id',
            'asc',
        );
    }

    /**
     * El campo y la dirección que pide la URL, ya validados.
     *
     * Se devuelve aparte de `ordenar()` porque la vista los necesita para
     * dibujar la flechita y para construir el enlace del encabezado.
     *
     * @param  array<int|string, string>  $permitidas
     * @return array{0: string, 1: string}
     */
    public static function orden(Request $request, array $permitidas, string $porDefecto, string $direccionPorDefecto = 'asc'): array
    {
        $pedido = Filtro::texto($request, 'orden');
        $campo = static::columnaReal($permitidas, $pedido) !== null ? $pedido : $porDefecto;

        $direccion = mb_strtolower(Filtro::texto($request, 'dir'));

        if (! in_array($direccion, ['asc', 'desc'], true)) {
            $direccion = $campo === $porDefecto ? $direccionPorDefecto : 'asc';
        }

        return [$campo, $direccion];
    }

    /** Cuántas filas por página, dentro de los tamaños admitidos. */
    public static function porPagina(Request $request, int $porDefecto = self::POR_PAGINA): int
    {
        $pedido = (int) Filtro::texto($request, 'filas');

        return in_array($pedido, self::TAMANOS, true) ? $pedido : $porDefecto;
    }

    /** @return array<int, int> */
    public static function tamanos(): array
    {
        return self::TAMANOS;
    }

    /**
     * Los ids que llegan de una selección múltiple, ya limpios.
     *
     * Devuelve enteros y descarta lo que no lo sea: la casilla de una tabla es
     * una entrada de usuario como cualquier otra, y llega en bloque.
     *
     * @return array<int, int>
     */
    public static function ids(Request $request, string $campo = 'ids', int $tope = 500): array
    {
        $crudos = $request->input($campo, []);

        if (! is_array($crudos)) {
            return [];
        }

        return collect($crudos)
            ->filter(fn ($v) => is_scalar($v) && ctype_digit(ltrim((string) $v, '-')) && (int) $v > 0)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->take($tope)
            ->values()
            ->all();
    }

    /**
     * La columna real de un campo permitido, o null si no está en la lista.
     *
     * @param  array<int|string, string>  $permitidas
     */
    private static function columnaReal(array $permitidas, string $campo): ?string
    {
        if ($campo === '') {
            return null;
        }

        foreach ($permitidas as $clave => $columna) {
            if (is_int($clave) && $columna === $campo) {
                return $columna;
            }

            if ($clave === $campo) {
                return $columna;
            }
        }

        return null;
    }
}
