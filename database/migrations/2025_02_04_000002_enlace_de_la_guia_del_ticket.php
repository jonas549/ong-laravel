<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El enlace de la guía para organizadores pasa a ser el del ticket
 * (punto 11 del 23/09): `https://canva.link/r3abo554dfga5g6`.
 *
 * Se comprobó que los dos llevan al mismo diseño —el corto redirige a
 * `canva.com/design/DAHGZdhU2ZE/daWOTMwxbN7B8xjFdDXANQ/view`, que es el que se
 * sembró—, pero el cliente pide el suyo.
 *
 * **Sólo si sigue el sembrado.** Si la ONG ya lo cambió en Configuración →
 * General, manda lo suyo.
 */
return new class extends Migration
{
    private const SEMBRADO = 'https://www.canva.com/design/DAHGZdhU2ZE/daWOTMwxbN7B8xjFdDXANQ/view';

    private const DEL_TICKET = 'https://canva.link/r3abo554dfga5g6';

    public function up(): void
    {
        DB::table('settings')
            ->where('clave', 'guia_organizador_url')
            ->where('valor', self::SEMBRADO)
            ->update(['valor' => self::DEL_TICKET, 'updated_at' => now()]);

        // Los ajustes viven en caché para siempre; sin esto seguiría el viejo.
        cache()->forget('settings.all');
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('clave', 'guia_organizador_url')
            ->where('valor', self::DEL_TICKET)
            ->update(['valor' => self::SEMBRADO, 'updated_at' => now()]);

        cache()->forget('settings.all');
    }
};
