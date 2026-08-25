<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que le faltaba al log para poder filtrar y reenviar:
 * de qué plantilla salió, qué adjuntos llevaba y cuándo se reenvió.
 *
 * Se comprueba cada pieza antes de crearla porque MySQL no revierte DDL: si
 * una migración falla a medias, la anterior ya quedó aplicada.
 *
 * No se indexa `to`: es TEXT, MySQL exige longitud de clave, y el buscador
 * usa LIKE '%…%', que no aprovecharía el índice de todos modos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('email_logs', 'plantilla')) {
                $table->string('plantilla')->nullable()->after('mailable');
            }

            if (! Schema::hasColumn('email_logs', 'adjuntos')) {
                $table->json('adjuntos')->nullable()->after('body_html');
            }

            if (! Schema::hasColumn('email_logs', 'reenviado_at')) {
                $table->timestamp('reenviado_at')->nullable()->after('sent_at');
            }
        });

        if (! $this->tieneIndice('email_logs_mailable_index')) {
            Schema::table('email_logs', fn (Blueprint $table) => $table->index('mailable'));
        }

        if (! $this->tieneIndice('email_logs_plantilla_index')) {
            Schema::table('email_logs', fn (Blueprint $table) => $table->index('plantilla'));
        }
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            if ($this->tieneIndice('email_logs_mailable_index')) {
                $table->dropIndex(['mailable']);
            }

            if ($this->tieneIndice('email_logs_plantilla_index')) {
                $table->dropIndex(['plantilla']);
            }
        });

        Schema::table('email_logs', function (Blueprint $table) {
            $columnas = array_values(array_filter(
                ['plantilla', 'adjuntos', 'reenviado_at'],
                fn ($c) => Schema::hasColumn('email_logs', $c),
            ));

            if ($columnas) {
                $table->dropColumn($columnas);
            }
        });
    }

    private function tieneIndice(string $nombre): bool
    {
        return collect(DB::select('SHOW INDEX FROM email_logs'))
            ->pluck('Key_name')
            ->contains($nombre);
    }
};
