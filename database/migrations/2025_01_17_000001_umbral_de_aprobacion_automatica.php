<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La aprobación automática pasa de un sí/no a un número (punto 26, 11/09).
 *
 * Antes sólo sabía decir «revisa la primera de cada organización». El cliente
 * la quiere abierta: cuántas actividades revisar antes de dejar publicar sola.
 *
 *   0 → todas automáticas, ninguna revisión
 *   1 → lo que ya estaba: se revisa la primera
 *   2 → se revisan las dos primeras, y así
 *
 * Y «exigir revisión de TODAS» se sigue diciendo apagando el interruptor
 * general, que es donde alguien va a buscarlo cuando llegue spam.
 *
 * ── Dos cosas de esta migración que no son de trámite ──
 *
 * 1. **Siembra también `aprobacion_automatica`, que en producción no existe.**
 *    Se escribió el 2026-09-02 sólo en `SettingsSeeder`, y el cron del servidor
 *    corre `migrate` y nunca siembra: la fila nunca llegó. El código lo lee con
 *    `Setting::get('aprobacion_automatica', true)`, así que lleva desde
 *    entonces funcionando por el valor por defecto **y sin que la ONG pueda
 *    apagarlo desde el panel** —la pantalla de Configuración sólo pinta las
 *    filas que existen—. El botón de pánico estaba fuera de alcance.
 *
 * 2. **El valor por defecto del umbral es 1, no 0.** Es lo que ya corre en
 *    producción desde el 2026-09-02. Poner 0 «porque es más abierto» cambiaría
 *    el comportamiento del sitio en el mismo despliegue que añade la opción, y
 *    sin que nadie lo hubiera pedido.
 *
 * Con guarda, como todas las de este tipo: sólo escribe lo que falte, y el
 * rótulo viejo sólo se corrige si nadie lo ha tocado.
 */
return new class extends Migration
{
    private const CLAVE_UMBRAL = 'aprobacion_automatica_desde';

    private const ROTULO_VIEJO = 'Publicar sin revisar a partir de la segunda actividad';

    private const ROTULO_NUEVO = 'Publicar sin revisar automáticamente';

    private const DESCRIPCION_NUEVA = 'Apagar esto devuelve TODAS las actividades a revisión; es lo que hay que hacer si llega spam. Encendido, se revisan las primeras de cada organización según el número de abajo. Cada organización tiene además su propio interruptor.';

    private const AJUSTES = [
        [
            'grupo' => 'general',
            'clave' => 'aprobacion_automatica',
            'tipo' => 'bool',
            'valor' => '1',
            'label' => self::ROTULO_NUEVO,
            'descripcion' => self::DESCRIPCION_NUEVA,
            'orden' => 32,
        ],
        [
            'grupo' => 'general',
            'clave' => self::CLAVE_UMBRAL,
            'tipo' => 'int',
            'valor' => '1',
            'label' => 'Actividades que se revisan antes de publicar sin revisión',
            'descripcion' => 'Cuántas actividades de cada organización se revisan a mano antes de que las siguientes se publiquen solas. 1 revisa sólo la primera (lo de antes), 2 las dos primeras, 0 ninguna. Cuentan las que llegaron a publicarse, aunque se cancelaran.',
            'orden' => 33,
        ],
    ];

    public function up(): void
    {
        foreach (self::AJUSTES as $ajuste) {
            if (! DB::table('settings')->where('clave', $ajuste['clave'])->exists()) {
                DB::table('settings')->insert($ajuste + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        /*
         * El rótulo del interruptor ya no dice la verdad —ahora el «a partir de
         * la segunda» lo decide el número—, pero sólo se corrige si sigue
         * siendo el que dejó la siembra. Si alguien lo reescribió, manda quien
         * lo reescribió.
         */
        DB::table('settings')
            ->where('clave', 'aprobacion_automatica')
            ->where('label', self::ROTULO_VIEJO)
            ->update([
                'label' => self::ROTULO_NUEVO,
                'descripcion' => self::DESCRIPCION_NUEVA,
                'updated_at' => now(),
            ]);

        // Los ajustes se leen de una caché sin caducidad; sin esto el proceso
        // siguiente seguiría viendo la foto de antes.
        cache()->forget(\App\Models\Setting::CACHE_KEY);
    }

    public function down(): void
    {
        DB::table('settings')->where('clave', self::CLAVE_UMBRAL)->delete();

        DB::table('settings')
            ->where('clave', 'aprobacion_automatica')
            ->where('label', self::ROTULO_NUEVO)
            ->update([
                'label' => self::ROTULO_VIEJO,
                'updated_at' => now(),
            ]);

        cache()->forget(\App\Models\Setting::CACHE_KEY);
    }
};
