<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Quién sale en la marquesina «Organizaciones e instituciones participantes».
 *
 * Dos modos, y un interruptor en el panel (Páginas → Marquesina de
 * organizaciones) que elige entre ellos:
 *
 *   - **A mano** (por defecto): las que haya elegido el administrador, en su
 *     orden. Es `organizations.marquesina_orden`.
 *   - **Automática**: todas las organizaciones participantes —las que han
 *     publicado y las del listado histórico del cliente—, por nombre.
 *
 * La lista manual no se toca al encender el automático: vive en su columna y
 * vuelve tal cual al apagarlo.
 */
final class Marquesina
{
    public const AJUSTE = 'marquesina_automatica';

    /** Sin fila del ajuste, apagado: la lista manual es lo seguro. */
    public static function automatica(): bool
    {
        return (bool) Setting::get(self::AJUSTE, false);
    }

    /**
     * Lo que se pinta en el home.
     *
     * Con el automático y ninguna organización se cae a las pastillas de
     * `partners`, como antes: una instalación recién sembrada no puede
     * quedarse con un hueco. Con la lista manual vacía, en cambio, no sale
     * nada: si el administrador la ha vaciado, es lo que ha elegido.
     *
     * @param  Collection<int, mixed>  $pastillas
     */
    public static function paraElHome(Collection $pastillas): Collection
    {
        if (! self::automatica()) {
            return self::sinRepetir(self::elegidas()->where('activo', true));
        }

        $todas = self::todas();

        return $todas->isNotEmpty() ? $todas : $pastillas;
    }

    /**
     * Todas las participantes, para el modo automático (P12 y la importación
     * del 21/09): activas, con actividad publicada o del listado histórico.
     */
    public static function todas(): Collection
    {
        return self::sinRepetir(Organization::query()
            ->where('activo', true)
            ->where(fn ($q) => $q
                ->whereHas('activities', fn ($a) => $a->where('estado', 'publicada'))
                ->orWhereNotNull('anios_participacion'))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'logo_path', 'activo']));
    }

    /**
     * La lista manual, en su orden. Incluye las desactivadas: el panel tiene
     * que enseñarlas —marcadas— para que se puedan quitar; el home las salta.
     */
    public static function elegidas(): Collection
    {
        return Organization::query()
            ->whereNotNull('marquesina_orden')
            ->orderBy('marquesina_orden')
            ->orderBy('id')
            ->get(['id', 'nombre', 'logo_path', 'activo', 'marquesina_orden']);
    }

    /**
     * Guarda la lista manual: las que vienen, en ese orden, y ninguna más.
     *
     * @param  array<int, int>  $ids
     */
    public static function guardar(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        DB::transaction(function () use ($ids) {
            Organization::query()->whereNotNull('marquesina_orden')->update(['marquesina_orden' => null]);

            foreach ($ids as $posicion => $id) {
                Organization::query()->whereKey($id)->update(['marquesina_orden' => $posicion + 1]);
            }
        });
    }

    /**
     * Por nombre normalizado: «Mi  Fundación» y «mi fundación» son la misma.
     * En producción hay nombres repetidos de antes de que el wizard lo
     * impidiera, y salían una detrás de otra: repetición de verdad.
     */
    private static function sinRepetir(Collection $organizaciones): Collection
    {
        return $organizaciones
            ->unique(fn (Organization $o) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($o->nombre))))
            ->values();
    }
}
