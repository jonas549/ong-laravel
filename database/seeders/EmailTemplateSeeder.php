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
                     <p style="margin:0 0 14px;">Si al final no puedes ir, avísanos para liberar tu cupo: <a href="{{ enlace_cancelar }}" style="color:#cc6600;">cancelar mi inscripción</a>.</p>',
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
                     <p style="margin:0 0 14px;">Si ya no puedes asistir, <a href="{{ enlace_cancelar }}" style="color:#cc6600;">cancela tu inscripción</a> para que otra persona pueda ocupar tu cupo.</p>',
                    'Ver la actividad',
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
