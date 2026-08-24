<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Ajustes autoadministrables desde el panel.
 *
 * El SMTP vive acá y no en el .env: así el equipo de la ONG puede cambiar
 * el servidor de correo sin tocar archivos ni pedir un deploy.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->data() as $orden => $s) {
            Setting::updateOrCreate(
                ['clave' => $s['clave']],
                [
                    'grupo' => $s['grupo'],
                    'valor' => $s['valor'] ?? null,
                    'tipo' => $s['tipo'],
                    'label' => $s['label'],
                    'descripcion' => $s['descripcion'] ?? null,
                    'orden' => $orden + 1,
                ],
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function data(): array
    {
        return [
            // ── SMTP ────────────────────────────────────────────────
            [
                'grupo' => 'smtp', 'clave' => 'smtp_activo', 'tipo' => 'bool', 'valor' => '0',
                'label' => 'Usar esta configuración',
                'descripcion' => 'Si está apagado, el sistema usa la configuración del archivo .env.',
            ],
            [
                'grupo' => 'smtp', 'clave' => 'smtp_host', 'tipo' => 'string', 'valor' => '',
                'label' => 'Servidor SMTP',
                'descripcion' => 'Por ejemplo mail.tudominio.cl',
            ],
            [
                'grupo' => 'smtp', 'clave' => 'smtp_port', 'tipo' => 'int', 'valor' => '587',
                'label' => 'Puerto',
                'descripcion' => '587 para TLS, 465 para SSL.',
            ],
            [
                'grupo' => 'smtp', 'clave' => 'smtp_encryption', 'tipo' => 'string', 'valor' => 'tls',
                'label' => 'Cifrado',
                'descripcion' => 'tls, ssl o ninguno.',
            ],
            [
                'grupo' => 'smtp', 'clave' => 'smtp_username', 'tipo' => 'string', 'valor' => '',
                'label' => 'Usuario',
            ],
            [
                'grupo' => 'smtp', 'clave' => 'smtp_password', 'tipo' => 'encrypted', 'valor' => null,
                'label' => 'Contraseña',
                'descripcion' => 'Se guarda cifrada con la clave de la aplicación.',
            ],
            [
                'grupo' => 'smtp', 'clave' => 'smtp_from_address', 'tipo' => 'string',
                'valor' => 'no-reply@ong-laravel.test',
                'label' => 'Correo remitente',
            ],
            [
                'grupo' => 'smtp', 'clave' => 'smtp_from_name', 'tipo' => 'string',
                'valor' => 'Día del Patrimonio Social',
                'label' => 'Nombre remitente',
            ],

            // ── General ─────────────────────────────────────────────
            [
                'grupo' => 'general', 'clave' => 'sitio_nombre', 'tipo' => 'string',
                'valor' => 'Día del Patrimonio Social',
                'label' => 'Nombre del sitio',
            ],
            [
                'grupo' => 'general', 'clave' => 'sitio_email_contacto', 'tipo' => 'string',
                'valor' => 'contacto@ong-laravel.test',
                'label' => 'Correo de contacto',
            ],
            [
                'grupo' => 'general', 'clave' => 'inscripciones_abiertas', 'tipo' => 'bool', 'valor' => '1',
                'label' => 'Inscripciones abiertas',
                'descripcion' => 'Apagar cierra la inscripción en todas las actividades a la vez.',
            ],
            [
                'grupo' => 'general', 'clave' => 'publicacion_abierta', 'tipo' => 'bool', 'valor' => '1',
                'label' => 'Publicación de actividades abierta',
                'descripcion' => 'Apagar oculta el formulario público de publicación.',
            ],
        ];
    }
}
