<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los dos ajustes que gobiernan la encuesta de evaluación.
 *
 * Van en una migración además de en `SettingsSeeder` por la lección que este
 * proyecto ya pagó tres veces el 2026-09-04: **el cron del servidor corre
 * `migrate` y nunca siembra**. Un ajuste que sólo viva en el seeder no existe
 * en producción hasta que alguien entre por SSH a correr `dps:instalar`, y
 * mientras tanto la pantalla de Configuración no lo enseña: la ONG no puede
 * cambiar lo que no ve.
 *
 * Con guarda, como todas las de este tipo: sólo escribe lo que falte. Volver a
 * correrla no pisa lo que la ONG haya cambiado.
 */
return new class extends Migration
{
    private const AJUSTES = [
        [
            'grupo' => 'general',
            'clave' => 'evaluacion_apertura',
            'tipo' => 'opciones',
            'valor' => 'publicacion',
            'label' => 'Cuándo se abre la encuesta de evaluación',
            'descripcion' => 'El QR de una actividad lleva a su encuesta. Aquí se decide desde cuándo se puede responder: '
                .'desde que la actividad se publica (así el organizador puede probar su propio QR antes del día) '
                .'o sólo desde el día en que ocurre.',
            'orden' => 30,
        ],
        [
            'grupo' => 'general',
            'clave' => 'evaluacion_dias_abierta',
            'tipo' => 'int',
            'valor' => '30',
            'label' => 'Días que la encuesta sigue abierta tras la actividad',
            'descripcion' => 'Pasados estos días desde que termina la actividad, el QR sigue funcionando pero enseña un aviso de encuesta cerrada. '
                .'Un 0 la deja abierta para siempre.',
            'orden' => 31,
        ],
    ];

    public function up(): void
    {
        foreach (self::AJUSTES as $ajuste) {
            if (DB::table('settings')->where('clave', $ajuste['clave'])->exists()) {
                continue;
            }

            DB::table('settings')->insert($ajuste + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Los ajustes se leen de una caché sin caducidad; sin esto el proceso
        // siguiente seguiría viendo la foto de antes.
        cache()->forget(\App\Models\Setting::CACHE_KEY);
    }

    public function down(): void
    {
        DB::table('settings')
            ->whereIn('clave', array_column(self::AJUSTES, 'clave'))
            ->delete();

        cache()->forget(\App\Models\Setting::CACHE_KEY);
    }
};
