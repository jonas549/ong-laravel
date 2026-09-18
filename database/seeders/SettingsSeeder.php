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
            $existente = Setting::where('clave', $s['clave'])->first();

            $meta = [
                'grupo' => $s['grupo'],
                'tipo' => $s['tipo'],
                'label' => $s['label'],
                'descripcion' => $s['descripcion'] ?? null,
                'orden' => $orden + 1,
            ];

            if ($existente) {
                // Sólo se refresca la metadatos. El valor NO se toca: volver a
                // correr el seeder en producción borraría la contraseña SMTP y
                // cualquier ajuste que haya cambiado la ONG.
                $existente->update($meta);

                continue;
            }

            Setting::create($meta + [
                'clave' => $s['clave'],
                'valor' => $s['valor'] ?? null,
            ]);
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
                'grupo' => 'general', 'clave' => 'recordatorio_dias', 'tipo' => 'int', 'valor' => '3',
                'label' => 'Días de antelación del recordatorio',
                'descripcion' => 'Cuántos días antes de la actividad se avisa a las personas inscritas.',
            ],
            [
                'grupo' => 'general', 'clave' => 'publicacion_abierta', 'tipo' => 'bool', 'valor' => '1',
                'label' => 'Publicación de actividades abierta',
                'descripcion' => 'Apagar oculta el formulario público de publicación.',
            ],
            [
                'grupo' => 'general', 'clave' => 'aprobacion_automatica', 'tipo' => 'bool', 'valor' => '1',
                'label' => 'Publicar sin revisar automáticamente',
                'descripcion' => 'Apagar esto devuelve TODAS las actividades a revisión; es lo que hay que hacer si llega spam. Encendido, se revisan las primeras de cada organización según el número de abajo. Cada organización tiene además su propio interruptor.',
            ],
            [
                'grupo' => 'general', 'clave' => 'kit_difusion_url', 'tipo' => 'texto', 'valor' => '',
                'label' => 'Enlace al kit de difusión',
                'descripcion' => 'La carpeta de Drive con el material de difusión. Sale como botón fijo en el panel del organizador y al terminar de publicar. '
                    .'Vacío no pinta ningún botón: es preferible a uno que no lleve a nada.',
            ],
            [
                'grupo' => 'general', 'clave' => 'aprobacion_automatica_desde', 'tipo' => 'int', 'valor' => '1',
                'label' => 'Actividades que se revisan antes de publicar sin revisión',
                'descripcion' => 'Cuántas actividades de cada organización se revisan a mano antes de que las siguientes se publiquen solas. 1 revisa sólo la primera (lo de antes), 2 las dos primeras, 0 ninguna. Cuentan las que llegaron a publicarse, aunque se cancelaran.',
            ],
            [
                'grupo' => 'general', 'clave' => 'alerta_revision_dias', 'tipo' => 'int', 'valor' => '3',
                'label' => 'Días antes de avisar de una revisión pendiente',
                'descripcion' => 'La portada del panel avisa cuando una actividad lleva más de estos días esperando revisión.',
            ],
            [
                'grupo' => 'general', 'clave' => 'evaluacion_apertura', 'tipo' => 'opciones', 'valor' => 'publicacion',
                'label' => 'Cuándo se abre la encuesta de evaluación',
                'descripcion' => 'El QR de una actividad lleva a su encuesta. Aquí se decide desde cuándo se puede responder: '
                    .'desde que la actividad se publica (así el organizador puede probar su propio QR antes del día) '
                    .'o sólo desde el día en que ocurre.',
            ],
            [
                'grupo' => 'general', 'clave' => 'evaluacion_dias_abierta', 'tipo' => 'int', 'valor' => '30',
                'label' => 'Días que la encuesta sigue abierta tras la actividad',
                'descripcion' => 'Pasados estos días desde que termina la actividad, el QR sigue funcionando pero enseña un aviso de encuesta cerrada. '
                    .'Un 0 la deja abierta para siempre.',
            ],
            [
                'grupo' => 'general', 'clave' => 'acceso_intentos', 'tipo' => 'int', 'valor' => '5',
                'label' => 'Intentos de acceso antes de bloquear',
                'descripcion' => 'Cuántas contraseñas erróneas seguidas se admiten antes de cerrar el acceso a esa cuenta desde esa IP.',
            ],
            [
                'grupo' => 'general', 'clave' => 'acceso_bloqueo_minutos', 'tipo' => 'int', 'valor' => '15',
                'label' => 'Duración del bloqueo, en minutos',
                'descripcion' => 'Cuánto dura el bloqueo una vez agotados los intentos.',
            ],

            // SEO
            [
                'grupo' => 'seo', 'clave' => 'seo_titulo', 'tipo' => 'string',
                'valor' => 'Día del Patrimonio Social — 4 y 5 de diciembre, Chile 2026',
                'label' => 'Título por defecto',
                'descripcion' => 'El que sale en la pestaña del navegador y en Google cuando la página no trae uno propio.',
            ],
            [
                'grupo' => 'seo', 'clave' => 'seo_descripcion', 'tipo' => 'string',
                'valor' => 'Dos días para poner en valor lo que las organizaciones sociales construyen todo el año. Suma tu actividad o participa en una cerca de ti.',
                'label' => 'Descripción por defecto',
                'descripcion' => 'Entre 120 y 160 caracteres es lo que Google suele mostrar entero.',
            ],
            [
                'grupo' => 'seo', 'clave' => 'seo_imagen', 'tipo' => 'string',
                'valor' => 'img/dps-logo-header.png',
                'label' => 'Imagen para redes sociales',
                'descripcion' => 'La que se ve al compartir un enlace. Ruta dentro de public/, por ejemplo img/portada.png.',
            ],
            [
                'grupo' => 'seo', 'clave' => 'seo_indexable', 'tipo' => 'bool', 'valor' => '1',
                'label' => 'Permitir que los buscadores indexen el sitio',
                'descripcion' => 'Apagar añade noindex en todas las páginas. Útil mientras el sitio no está listo.',
            ],
        ];
    }
}
