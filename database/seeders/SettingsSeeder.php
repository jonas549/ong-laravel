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
                // Vacío y no un correo de ejemplo: uno inventado acaba en los
                // correos de producción (09/10). Se rellena en el panel.
                'valor' => '',
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
                'descripcion' => 'Apagar esto devuelve TODAS las actividades a revisión; es lo que hay que hacer si llega spam. Encendido, se revisan las primeras de cada cuenta según el número de abajo. Cada organización tiene además su propio interruptor.',
            ],
            /*
             * Varias cuentas por organización. Apagado por defecto: apagado es
             * exactamente lo de antes. La descripción cabe en 255 caracteres
             * (`settings.descripcion` es varchar(255) y pasarse rompe
             * `dps:instalar`, que corre en cada despliegue).
             */
            [
                'grupo' => 'general', 'clave' => 'organizacion_varias_cuentas', 'tipo' => 'bool', 'valor' => '0',
                'label' => 'Permitir que una organización tenga más de una cuenta',
                'descripcion' => 'Encendido, quien elige una organización que ya tiene cuenta se suma a ella y se avisa a su cuenta principal. Apagado, no puede. Apagarlo no saca a quien ya se sumó: sólo deja de admitir nuevas.',
            ],
            [
                'grupo' => 'general', 'clave' => 'kit_difusion_url', 'tipo' => 'texto', 'valor' => '',
                'label' => 'Enlace al kit de difusión',
                'descripcion' => 'La carpeta de Drive con el material de difusión. Sale como botón fijo en el panel del organizador y al terminar de publicar. '
                    .'Vacío no pinta ningún botón: es preferible a uno que no lleve a nada.',
            ],
            [
                // El desvío del paso 1 del wizard: quien necesita convocar
                // voluntarios va a Voluntariados Chile. El prototipo anunciaba
                // la redirección y no la hacía; ahora se hace, y el destino se
                // cambia aquí. Vacío, el aviso no promete ninguna redirección.
                'grupo' => 'general', 'clave' => 'voluntariado_url', 'tipo' => 'texto',
                'valor' => 'https://voluntariadoschile.cl/oportunidades',
                'label' => 'Enlace a Voluntariados Chile',
                'descripcion' => 'A dónde lleva «Sí, necesito voluntarios» en el primer paso de publicar actividad. '
                    .'Vacío, el aviso deja de anunciar la redirección y sólo ofrece volver.',
            ],
            [
                // D1 de la sexta tanda. Se siembra con el diseño de Canva que
                // mandó el cliente; la ONG lo cambia aquí sin tocar el correo.
                'grupo' => 'general', 'clave' => 'guia_organizador_url', 'tipo' => 'texto',
                // El enlace corto del ticket (punto 11 del 23/09). Lleva al
                // mismo diseño que el largo que se sembró antes.
                'valor' => 'https://canva.link/r3abo554dfga5g6',
                'label' => 'Enlace a la guía para organizadores',
                'descripcion' => 'Se manda por correo a cada organización cuando se publica una de sus actividades (plantilla «Guía para organizadores»). '
                    .'Vacío, ese correo no sale.',
            ],
            [
                // Punto 9 del 23/09. El aviso de «una actividad volvió de
                // ajustes» iba a cada administrador activo, y así le llegaba a
                // una persona a su correo personal. El cliente pide el buzón
                // del equipo, y que se pueda cambiar desde aquí.
                'grupo' => 'general', 'clave' => 'avisos_email', 'tipo' => 'texto',
                'valor' => 'diadelpatrimoniosocial@comunidad-org.cl',
                'label' => 'Correo que recibe los avisos de actividades',
                'descripcion' => 'A dónde llegan los avisos al equipo: actividad nueva en revisión, publicada sin revisión, '
                    .'publicada que se editó y corregida tras ajustes. Vacío, van a los administradores.',
            ],
            [
                // Ajustes del 06/10. Vacío es «el banner de siempre», que vive
                // en `Activity::IMAGEN_DE_RESERVA`: quitarla vuelve a él.
                'grupo' => 'general', 'clave' => 'actividad_imagen_defecto', 'tipo' => 'imagen', 'valor' => '',
                'label' => 'Imagen predeterminada de las actividades',
                'descripcion' => 'Se usa en las actividades sin imagen propia: ficha, tarjetas, carrusel e imagen de difusión, en escritorio y en móvil. Sin imagen, se usa el banner del Día del Patrimonio Social.',
            ],
            [
                // Se siembra vacío a propósito: vacío es «el de siempre», que
                // vive en `MensajeCompartir::POR_DEFECTO`. Así un cambio del
                // texto por defecto llega a todas las bases sin migración.
                'grupo' => 'general', 'clave' => 'compartir_mensaje', 'tipo' => 'texto', 'valor' => '',
                'label' => 'Mensaje al compartir una actividad',
                // La columna es de 255: la explicación larga no cabe y rompía
                // `dps:instalar`, que es parte del despliegue.
                'descripcion' => 'Texto propuesto al compartir por WhatsApp. {nombre} = nombre de la actividad; '
                    .'{enlace} = dirección de su ficha (si falta, se añade al final). Vacío: «Súmate a esta actividad de '
                    .'celebración del Día del Patrimonio Social: {nombre} {enlace}».',
            ],
            [
                // El pie de la imagen de difusión de cada actividad. Cambia
                // cada edición, así que se edita aquí y no en el código.
                'grupo' => 'general', 'clave' => 'difusion_fechas', 'tipo' => 'texto',
                'valor' => '4 y 5 de diciembre',
                'label' => 'Fechas de la edición (imagen de difusión)',
                'descripcion' => 'Sale en el pie de la imagen de difusión: «Día del Patrimonio Social - …». Cámbialas cada año.',
            ],
            [
                'grupo' => 'general', 'clave' => 'difusion_hashtag', 'tipo' => 'texto',
                'valor' => '#DarEstaEnNuestraNaturaleza',
                'label' => 'Hashtag de la edición (imagen de difusión)',
                'descripcion' => 'La última línea del pie de la imagen de difusión.',
            ],
            [
                'grupo' => 'general', 'clave' => 'aprobacion_automatica_desde', 'tipo' => 'int', 'valor' => '1',
                'label' => 'Actividades que se revisan antes de publicar sin revisión',
                'descripcion' => 'Cuántas actividades de cada cuenta se revisan antes de que las siguientes se publiquen solas. 1 la primera, 2 las dos primeras, 0 ninguna (salvo la primera de una cuenta sumada, que siempre). Cuentan las publicadas aunque se cancelaran.',
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
                'grupo' => 'general', 'clave' => 'evaluacion_invitacion_cuando', 'tipo' => 'opciones', 'valor' => 'dia_siguiente',
                'label' => 'Cuándo se invita a evaluar por correo',
                'descripcion' => 'A quien se inscribió se le manda el enlace de la encuesta cuando la actividad ya pasó. '
                    .'Aquí se decide si sale el mismo día o al día siguiente. «No enviar» deja sólo el QR del cartel.',
            ],
            [
                'grupo' => 'general', 'clave' => 'evaluacion_max_fotos', 'tipo' => 'int', 'valor' => '3',
                'label' => 'Fotografías que puede subir cada persona',
                'descripcion' => 'Cuántas fotos admite la encuesta de evaluación en una misma respuesta. '
                    .'Un 0 quita el campo de fotografía de la encuesta.',
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

            // Home. Grupo propio: se administra en su pantalla
            // (Páginas → Marquesina de organizaciones), no en Configuración.
            [
                'grupo' => 'home', 'clave' => 'marquesina_automatica', 'tipo' => 'bool', 'valor' => '0',
                'label' => 'Mostrar automáticamente todas las organizaciones registradas',
                'descripcion' => 'Encendido, la marquesina del home se llena sola con todas las organizaciones. Apagado, sale sólo la lista elegida a mano.',
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
