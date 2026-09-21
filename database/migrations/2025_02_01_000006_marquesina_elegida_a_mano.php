<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La marquesina del home, elegida a mano desde el panel.
 *
 * `marquesina_orden` es el sitio de cada organización en la lista manual;
 * nulo, que no está. Vive en la propia organización y no en el ajuste del
 * interruptor, así que **encender «mostrar todas» no toca la lista**: al
 * apagarlo vuelve tal como estaba.
 *
 * **Arranca con las que salen hoy**, para que desplegar no cambie el home: las
 * activas con alguna actividad publicada, por nombre y sin repetir nombre, que
 * es exactamente lo que pintaba `HomeController::paraLaMarquesina` hasta aquí.
 * Las importadas del listado del cliente no entran: ninguna ha publicado.
 *
 * El interruptor se siembra apagado en `SettingsSeeder` (`marquesina_automatica`),
 * y quien lo lea sin fila lo da por apagado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $tabla) {
            $tabla->unsignedInteger('marquesina_orden')->nullable()->after('anios_participacion');
            $tabla->index('marquesina_orden');
        });

        $hoy = DB::table('organizations')
            ->where('activo', true)
            ->whereNull('deleted_at')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('activities')
                ->whereColumn('activities.organization_id', 'organizations.id')
                ->where('activities.estado', 'publicada')
                ->whereNull('activities.deleted_at'))
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->unique(fn ($o) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($o->nombre))))
            ->values();

        foreach ($hoy as $posicion => $organizacion) {
            DB::table('organizations')->where('id', $organizacion->id)->update(['marquesina_orden' => $posicion + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $tabla) {
            $tabla->dropIndex(['marquesina_orden']);
            $tabla->dropColumn('marquesina_orden');
        });
    }
};
