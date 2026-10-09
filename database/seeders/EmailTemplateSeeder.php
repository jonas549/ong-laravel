<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Textos de partida de las plantillas. Son editables desde el panel, así que
 * este seeder sólo crea las que falten: no pisa lo que la ONG haya cambiado.
 */
class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->plantillas() as $clave => $datos) {
            $meta = EmailTemplate::CATALOGO[$clave];

            EmailTemplate::firstOrCreate(
                ['clave' => $clave],
                [
                    'nombre' => $meta['nombre'],
                    'descripcion' => $meta['descripcion'],
                    'variables' => $meta['variables'],
                    'activo' => true,
                ] + $datos,
            );
        }
    }

    /** @return array<string, array{asunto: string, cuerpo_html: string}> */
    private function plantillas(): array
    {
        return [
            'bienvenida' => [
                'asunto' => 'Bienvenida a {{ sitio }}',
                'cuerpo_html' => $this->cuerpo(
                    'Te damos la bienvenida',
                    '<p style="margin:0 0 14px;">Hola {{ nombre }}, ya tienes cuenta en <strong>{{ sitio }}</strong>.</p>
                     <p style="margin:0 0 14px;">Desde tu cuenta puedes editar las actividades de <strong>{{ organizacion }}</strong>, revisar en qué estado están y ver quién se inscribe.</p>
                     <p style="margin:0 0 14px;">Tu acceso es <strong>{{ correo }}</strong>.</p>',
                    'Ir a mi cuenta',
                    '{{ enlace_cuenta }}',
                ),
            ],

            'inscripcion_confirmada' => [
                'asunto' => 'Te esperamos en {{ actividad }}',
                'cuerpo_html' => $this->cuerpo(
                    'Inscripción confirmada',
                    '<p style="margin:0 0 14px;">Hola {{ nombre }}, guardamos tu inscripción en <strong>{{ actividad }}</strong>.</p>
                     <p style="margin:0 0 6px;"><strong>Cuándo:</strong> {{ fecha }}, {{ hora }}</p>
                     <p style="margin:0 0 6px;"><strong>Dónde:</strong> {{ lugar }}</p>
                     <p style="margin:0 0 14px;"><strong>Organiza:</strong> {{ organizacion }}</p>
                     <p style="margin:0 0 14px;">Si al final no puedes ir, avísanos para liberar tu cupo: <a href="{{ enlace_cancelar }}" style="color:#cc6600;">cancelar mi inscripción</a>.</p>
                     {{ bloque_calendario }}
                     {{ bloque_soy_parte }}',
                    'Ver la actividad',
                    '{{ enlace_actividad }}',
                ),
            ],

            'nueva_inscripcion' => [
                'asunto' => 'Nueva inscripción en {{ actividad }}',
                'cuerpo_html' => $this->cuerpo(
                    'Alguien se inscribió',
                    '<p style="margin:0 0 14px;"><strong>{{ nombre }}</strong> ({{ correo_inscrito }}) se inscribió en <strong>{{ actividad }}</strong> ({{ fecha }}).</p>
                     <p style="margin:0 0 14px;">Quedan <strong>{{ cupos_disponibles }}</strong> cupos disponibles.</p>',
                    'Ver participantes',
                    '{{ enlace_participantes }}',
                ),
            ],

            'recordatorio' => [
                'asunto' => 'Faltan {{ dias }} días para {{ actividad }}',
                'cuerpo_html' => $this->cuerpo(
                    'Nos vemos pronto',
                    '<p style="margin:0 0 14px;">Hola {{ nombre }}, te recordamos que en {{ dias }} días es <strong>{{ actividad }}</strong>.</p>
                     <p style="margin:0 0 6px;"><strong>Cuándo:</strong> {{ fecha }}, {{ hora }}</p>
                     <p style="margin:0 0 14px;"><strong>Dónde:</strong> {{ lugar }}</p>
                     <p style="margin:0 0 14px;">Si ya no puedes asistir, <a href="{{ enlace_cancelar }}" style="color:#cc6600;">cancela tu inscripción</a> para que otra persona pueda ocupar tu cupo.</p>
                     {{ bloque_calendario }}',
                    'Ver la actividad',
                    '{{ enlace_actividad }}',
                ),
            ],

            'invitacion_evaluacion' => [
                'asunto' => '¿Cómo te fue en {{ actividad }}?',
                'cuerpo_html' => $this->cuerpo(
                    '¿Nos cuentas cómo te fue?',
                    '<p style="margin:0 0 14px;">Hola {{ nombre }}, gracias por participar en <strong>{{ actividad }}</strong> con {{ organizacion }}.</p>
                     <p style="margin:0 0 14px;">Nos ayudaría mucho saber cómo lo viviste. Son dos minutos y las respuestas sirven para preparar las próximas ediciones del Día del Patrimonio Social.</p>',
                    'Responder la encuesta',
                    '{{ enlace_encuesta }}',
                ),
            ],

            'actividad_publicada' => [
                'asunto' => 'Tu actividad ya está publicada',
                'cuerpo_html' => $this->cuerpo(
                    'Tu actividad ya está publicada',
                    '<p style="margin:0 0 14px;">Hola {{ nombre }}, <strong>{{ actividad }}</strong> ya es parte del Día del Patrimonio Social y aparece en el calendario público.</p>
                     <p style="margin:0 0 6px;"><strong>Cuándo:</strong> {{ fecha }}</p>
                     <p style="margin:0 0 14px;"><strong>Dónde:</strong> {{ lugar }}</p>
                     <p style="margin:0 0 14px;">Abajo tienes el código QR de tu actividad. Al escanearlo, quien haya participado llega a una encuesta breve para contarte cómo le fue; sus respuestas las verás junto al resto de la edición.</p>
                     {{ bloque_qr }}',
                    'Ver la actividad publicada',
                    '{{ enlace_actividad }}',
                ),
            ],

            'inscripcion_cancelada' => [
                'asunto' => '{{ actividad }} fue cancelada',
                'cuerpo_html' => $this->cuerpo(
                    'La actividad se canceló',
                    '<p style="margin:0 0 14px;">Hola {{ nombre }}, lamentamos avisarte que <strong>{{ actividad }}</strong>, prevista para el {{ fecha }}, fue cancelada por {{ organizacion }}.</p>
                     <p style="margin:0 0 14px;">Tu inscripción queda sin efecto y no tienes que hacer nada.</p>
                     <p style="margin:0 0 14px;">Hay muchas otras actividades a las que sumarte.</p>',
                    'Ver otras actividades',
                    '{{ enlace_actividades }}',
                ),
            ],

            'guia_organizador' => [
                'asunto' => 'La guía para organizar {{ actividad }}',
                'cuerpo_html' => $this->cuerpo(
                    'Tu guía para organizar',
                    '<p style="margin:0 0 14px;">Hola {{ nombre }}, gracias por sumar <strong>{{ actividad }}</strong> al Día del Patrimonio Social.</p>
                     <p style="margin:0 0 14px;">Preparamos una guía para organizadores con lo que conviene tener en cuenta antes, durante y después de la actividad: cómo difundirla, cómo recibir a quienes se inscriben y cómo contar lo que pasó.</p>
                     <p style="margin:0 0 14px;">Tu actividad ya está publicada en el calendario. Puedes revisarla y editarla cuando quieras desde <a href="{{ enlace_cuenta }}" style="color:#cc6600;">tu cuenta</a>.</p>',
                    'Abrir la guía para organizadores',
                    '{{ enlace_guia }}',
                ),
            ],

            'equipo_actividad_en_revision' => [
                'asunto' => 'Actividad para revisar: {{ actividad }}',
                'cuerpo_html' => $this->cuerpo(
                    'Hay una actividad esperando revisión',
                    '<p style="margin:0 0 14px;"><strong>{{ organizacion }}</strong> ({{ correo_organizacion }}) envió <strong>{{ actividad }}</strong> y está esperando revisión.</p>
                     <p style="margin:0 0 6px;"><strong>Cuándo:</strong> {{ fecha }}</p>
                     <p style="margin:0 0 14px;"><strong>Dónde:</strong> {{ lugar }}</p>
                     <p style="margin:0 0 14px;">Por qué pasa por revisión: {{ motivo }}</p>',
                    'Revisar la actividad',
                    '{{ enlace_revisar }}',
                ),
            ],

            'equipo_actividad_autopublicada' => [
                'asunto' => 'Publicada sin revisión: {{ actividad }}',
                'cuerpo_html' => $this->cuerpo(
                    'Se publicó una actividad sin revisión',
                    '<p style="margin:0 0 14px;"><strong>{{ actividad }}</strong>, de <strong>{{ organizacion }}</strong> ({{ correo_organizacion }}), se publicó sola por la aprobación automática y ya está en el calendario.</p>
                     <p style="margin:0 0 6px;"><strong>Cuándo:</strong> {{ fecha }}</p>
                     <p style="margin:0 0 14px;"><strong>Dónde:</strong> {{ lugar }}</p>
                     <p style="margin:0 0 14px;">Puedes verla publicada <a href="{{ enlace_actividad }}" style="color:#cc6600;">en el sitio</a> o revisarla desde el panel.</p>',
                    'Revisar en el panel',
                    '{{ enlace_revisar }}',
                ),
            ],

            'equipo_actividad_editada' => [
                'asunto' => 'Cambios en una actividad publicada: {{ actividad }}',
                'cuerpo_html' => $this->cuerpo(
                    'Una actividad publicada cambió',
                    '<p style="margin:0 0 14px;"><strong>{{ organizacion }}</strong> ({{ correo_organizacion }}) guardó cambios en <strong>{{ actividad }}</strong>, que ya está publicada. Los cambios ya se ven en el sitio.</p>
                     <p style="margin:0 0 6px;"><strong>Cuándo:</strong> {{ fecha }}</p>
                     <p style="margin:0 0 14px;"><strong>Dónde:</strong> {{ lugar }}</p>
                     <p style="margin:0 0 14px;">Este aviso sale una vez al día por actividad: si la siguen editando hoy, no llegarán más.</p>',
                    'Revisar en el panel',
                    '{{ enlace_revisar }}',
                ),
            ],

            'cuenta_sumada' => [
                'asunto' => 'Una nueva cuenta se sumó a {{ organizacion }}',
                'cuerpo_html' => $this->cuerpo(
                    'Una nueva cuenta en tu organización',
                    '<p style="margin:0 0 14px;">Hola {{ nombre }}:</p>
                     <p style="margin:0 0 14px;"><strong>{{ nombre_cuenta }}</strong> ({{ correo_cuenta }}) creó una cuenta en {{ sitio }} y se sumó a <strong>{{ organizacion }}</strong> el {{ fecha }}. Desde ahora puede publicar actividades con el nombre de la organización.</p>
                     <p style="margin:0 0 14px;">Cada cuenta ve y edita sólo sus propias actividades, y su primera actividad pasará por revisión antes de publicarse.</p>
                     <p style="margin:0 0 14px;">Si no conoces a esta persona o no debería publicar en nombre de {{ organizacion }}, escríbenos a {{ correo_sitio }}.</p>
                     <p style="margin:0 0 14px;">{{ nota }}</p>',
                    'Ir a mi cuenta',
                    '{{ enlace_cuenta }}',
                ),
            ],
        ];
    }

    /** Mismo esqueleto para todas: título, cuerpo y un botón. */
    private function cuerpo(string $titulo, string $parrafos, string $cta, string $enlace): string
    {
        return trim(<<<HTML
        <h1 style="font-family:'Raleway',Arial,sans-serif;font-size:22px;font-weight:800;margin:0 0 14px;color:#33363a;">{$titulo}</h1>

        {$parrafos}

        <p style="margin:22px 0 0;">
            <a href="{$enlace}" style="display:inline-block;background:#e57200;color:#ffffff;font-weight:600;font-size:14px;padding:12px 22px;border-radius:999px;text-decoration:none;">{$cta}</a>
        </p>
        HTML);
    }
}
