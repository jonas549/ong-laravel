<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    /**
     * Las plantillas que el sistema envía solo. La clave es la que usa el
     * código para pedirlas, así que no se toca desde el panel.
     *
     * `variables` es la lista blanca de marcadores que admite cada una: sirve
     * para pintarlas en el editor y para rechazar las que no sabemos resolver.
     */
    public const CATALOGO = [
        'bienvenida' => [
            'nombre' => 'Bienvenida al registrarse',
            'descripcion' => 'Se envía a quien crea una cuenta al publicar su primera actividad.',
            'variables' => ['nombre', 'organizacion', 'correo', 'enlace_cuenta', 'sitio'],
        ],
        'inscripcion_confirmada' => [
            'nombre' => 'Confirmación de inscripción',
            'descripcion' => 'Se envía a la persona que se inscribe en una actividad.',
            'variables' => ['nombre', 'actividad', 'fecha', 'hora', 'lugar', 'organizacion', 'enlace_actividad', 'enlace_cancelar', 'bloque_calendario', 'bloque_contacto', 'bloque_soy_parte', 'sitio'],
        ],
        'nueva_inscripcion' => [
            'nombre' => 'Aviso de nueva inscripción',
            'descripcion' => 'Se envía a la organización cuando alguien se inscribe en su actividad.',
            'variables' => ['nombre', 'correo_inscrito', 'actividad', 'fecha', 'cupos_disponibles', 'enlace_participantes', 'sitio'],
        ],
        'recordatorio' => [
            'nombre' => 'Recordatorio antes de la actividad',
            'descripcion' => 'Se envía a las personas inscritas los días previos a la actividad.',
            'variables' => ['nombre', 'actividad', 'fecha', 'hora', 'lugar', 'dias', 'enlace_actividad', 'enlace_cancelar', 'bloque_calendario', 'sitio'],
        ],
        'actividad_publicada' => [
            'nombre' => 'Aviso de actividad publicada',
            'descripcion' => 'Se envía al organizador cuando su actividad queda publicada, ya sea tras la revisión o por aprobación automática. '
                .'Es el correo que lleva el código QR de la encuesta de evaluación.',
            'variables' => ['nombre', 'organizacion', 'actividad', 'fecha', 'lugar', 'enlace_actividad', 'enlace_qr', 'bloque_qr', 'sitio'],
        ],

        /*
         * P20. Va a quien asistió, el día de la actividad o el siguiente —lo
         * decide la ONG en Configuración → General—, con el enlace a la
         * encuesta. Hasta aquí la encuesta sólo se llegaba por el QR del
         * cartel: quien no lo escaneó ese día no tenía forma de volver.
         */
        'invitacion_evaluacion' => [
            'nombre' => 'Invitación a evaluar la actividad',
            'descripcion' => 'Se envía a las personas inscritas cuando la actividad ya pasó, con el enlace a la encuesta. '
                .'Cuándo sale —el mismo día o el siguiente— se elige en Configuración → General.',
            'variables' => ['nombre', 'actividad', 'fecha', 'lugar', 'organizacion', 'enlace_encuesta', 'enlace_actividad', 'sitio'],
        ],

        'inscripcion_cancelada' => [
            'nombre' => 'Aviso de actividad cancelada',
            'descripcion' => 'Se envía a las personas inscritas cuando la actividad se cancela.',
            'variables' => ['nombre', 'actividad', 'fecha', 'organizacion', 'enlace_actividades', 'sitio'],
        ],

        /*
         * D1 de la sexta tanda. Va a la organización en cuanto registra una
         * actividad, con el enlace a la guía para organizadores (el diseño de
         * Canva), que se cambia en Configuración → General.
         *
         * Es un correo aparte y no un párrafo del de «recibimos tu actividad»:
         * aquél es una vista fija que la ONG no puede editar, y con aprobación
         * automática ni siquiera se envía —la actividad pasa directa a
         * publicada—. Así llega igual por los dos caminos y en el momento, no
         * días después con la revisión.
         */
        'guia_organizador' => [
            'nombre' => 'Guía para organizadores',
            'descripcion' => 'Se envía a la organización cuando se publica cada una de sus actividades, con el enlace a la guía para organizadores. '
                .'El enlace se cambia en Configuración → General; si está vacío, este correo no sale.',
            'variables' => ['nombre', 'organizacion', 'actividad', 'enlace_guia', 'enlace_cuenta', 'sitio'],
        ],

        /*
         * Los tres avisos al equipo (tanda del 05/10). Van al buzón de
         * Configuración → General (`avisos_email`) y, si está vacío, a los
         * administradores activos. Sólo observan lo que ya pasó: no deciden
         * nada sobre el estado de la actividad ni sobre la aprobación
         * automática.
         */
        'equipo_actividad_en_revision' => [
            'nombre' => 'Aviso al equipo: actividad nueva en revisión',
            'descripcion' => 'Se envía al correo de avisos de Configuración → General cuando una organización envía una actividad y queda esperando revisión. '
                .'No sale cuando la actividad vuelve corregida de «necesita ajustes»: ese caso tiene su propio aviso.',
            'variables' => ['actividad', 'organizacion', 'correo_organizacion', 'fecha', 'lugar', 'motivo', 'enlace_revisar', 'sitio'],
        ],
        'equipo_actividad_autopublicada' => [
            'nombre' => 'Aviso al equipo: actividad publicada automáticamente',
            'descripcion' => 'Se envía al correo de avisos de Configuración → General cuando una actividad se publica sola por la aprobación automática, sin que nadie la revise.',
            'variables' => ['actividad', 'organizacion', 'correo_organizacion', 'fecha', 'lugar', 'enlace_revisar', 'enlace_actividad', 'sitio'],
        ],
        'equipo_actividad_editada' => [
            'nombre' => 'Aviso al equipo: actividad publicada que se editó',
            'descripcion' => 'Se envía al correo de avisos de Configuración → General cuando una organización guarda cambios en una actividad ya publicada. '
                .'Como mucho uno por actividad y día, aunque se guarde varias veces.',
            'variables' => ['actividad', 'organizacion', 'correo_organizacion', 'fecha', 'lugar', 'enlace_revisar', 'enlace_actividad', 'sitio'],
        ],

        /*
         * Varias cuentas por organización: quien se suma entra directo, sin
         * aprobación previa, y este aviso es el contrapeso. Va a la cuenta
         * principal; si la organización no tiene una activa, al buzón de
         * avisos del equipo, y `nota` dice por qué.
         */
        'cuenta_sumada' => [
            'nombre' => 'Aviso de cuenta sumada a la organización',
            // `email_templates.descripcion` es varchar(255): pasarse rompe
            // `dps:instalar`, que corre en cada despliegue.
            'descripcion' => 'Se envía a la cuenta principal de una organización cuando otra persona se suma a ella '
                .'(si Configuración → General permite varias cuentas). Sin cuenta principal activa, va al correo de avisos del equipo.',
            'variables' => ['nombre', 'organizacion', 'nombre_cuenta', 'correo_cuenta', 'fecha', 'nota', 'correo_sitio', 'enlace_cuenta', 'sitio'],
        ],
    ];

    protected $fillable = ['clave', 'nombre', 'descripcion', 'asunto', 'cuerpo_html', 'variables', 'activo'];

    protected function casts(): array
    {
        return ['variables' => 'array', 'activo' => 'boolean'];
    }

    public static function porClave(string $clave): ?self
    {
        return static::where('clave', $clave)->where('activo', true)->first();
    }

    /** Los marcadores que admite esta plantilla, con el catálogo como respaldo. */
    public function variablesDisponibles(): array
    {
        /*
         * La UNIÓN de las dos listas, no una o la otra.
         *
         * `variables` se copia del catálogo al crear la plantilla y se queda
         * congelada ahí. Devolver sólo esa dejaba fuera cualquier marcador
         * añadido después: la ONG no lo vería en el panel y, si lo escribía
         * a mano, saldría literal en el correo por no estar en la lista
         * blanca. Con la unión, lo nuevo del catálogo funciona en las
         * plantillas que ya existían sin tocarles el texto.
         */
        return array_values(array_unique(array_merge(
            $this->variables ?: [],
            self::CATALOGO[$this->clave]['variables'] ?? [],
        )));
    }

    /**
     * Marcadores que el catálogo ofrece y esta plantilla todavía no usa.
     *
     * Es lo que se le enseña a la ONG cuando aparece uno nuevo. **No se
     * mete solo en el cuerpo**: el texto de esa plantilla lo escribió
     * alguien y colarle un párrafo sin avisar es peor que no ofrecerlo.
     * Se avisa, y quien lo edita decide dónde va.
     *
     * @return array<int, string>
     */
    public function variablesNuevas(): array
    {
        $delCatalogo = self::CATALOGO[$this->clave]['variables'] ?? [];
        $texto = $this->asunto.' '.$this->cuerpo_html;

        return array_values(array_filter(
            $delCatalogo,
            fn (string $v) => ! in_array($v, $this->variables ?: [], true)
                && ! preg_match('/\{\{\s*'.preg_quote($v, '/').'\s*\}\}/', $texto),
        ));
    }

    /**
     * Marcadores escritos en el cuerpo o el asunto que no están en la lista
     * blanca: se quedarían literales en el correo, así que hay que avisarlo.
     */
    public function variablesDesconocidas(): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/i', $this->asunto . ' ' . $this->cuerpo_html, $m);

        return array_values(array_unique(array_diff($m[1], $this->variablesDisponibles())));
    }
}
