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
            'variables' => ['nombre', 'actividad', 'fecha', 'hora', 'lugar', 'organizacion', 'enlace_actividad', 'enlace_cancelar', 'sitio'],
        ],
        'nueva_inscripcion' => [
            'nombre' => 'Aviso de nueva inscripción',
            'descripcion' => 'Se envía a la organización cuando alguien se inscribe en su actividad.',
            'variables' => ['nombre', 'correo_inscrito', 'actividad', 'fecha', 'cupos_disponibles', 'enlace_participantes', 'sitio'],
        ],
        'recordatorio' => [
            'nombre' => 'Recordatorio antes de la actividad',
            'descripcion' => 'Se envía a las personas inscritas los días previos a la actividad.',
            'variables' => ['nombre', 'actividad', 'fecha', 'hora', 'lugar', 'dias', 'enlace_actividad', 'enlace_cancelar', 'sitio'],
        ],
        'inscripcion_cancelada' => [
            'nombre' => 'Aviso de actividad cancelada',
            'descripcion' => 'Se envía a las personas inscritas cuando la actividad se cancela.',
            'variables' => ['nombre', 'actividad', 'fecha', 'organizacion', 'enlace_actividades', 'sitio'],
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
        return $this->variables ?: (self::CATALOGO[$this->clave]['variables'] ?? []);
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
