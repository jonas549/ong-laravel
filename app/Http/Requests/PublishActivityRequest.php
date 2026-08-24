<?php

namespace App\Http\Requests;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\TaxonomyTerm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Paso 3 — organización y cuenta
            'org_nombre' => ['required', 'string', 'max:255'],
            'org_tipo' => ['required', Rule::in(Organization::TIPOS)],
            'org_tipo_otro' => ['nullable', 'required_if:org_tipo,Otra', 'string', 'max:255'],
            'org_descripcion' => ['required', 'string', 'max:2000'],
            'org_num_voluntarios' => ['nullable', 'required_if:org_tipo,Empresa o institución privada', 'integer', 'min:0', 'max:100000'],
            'org_unidad_educativa' => ['nullable', 'required_if:org_tipo,Institución educativa', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],

            // Paso 4 — actividad
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:1000'],
            'formato' => ['required', Rule::in(Activity::FORMATOS)],
            'sin_fecha_definida' => ['nullable', 'boolean'],
            'fecha_inicio' => ['nullable', 'required_without:sin_fecha_definida', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_termino' => ['nullable', 'date_format:H:i', 'after:hora_inicio'],
            'commune_id' => ['nullable', 'required_without:sin_fecha_definida', 'exists:communes,id'],
            'direccion' => ['nullable', 'string', 'max:255'],

            'participantes_estimados' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'cupos_totales' => ['nullable', 'integer', 'min:0', 'max:100000'],

            'abierta_publico' => ['nullable', 'boolean'],
            'inscripcion_habilitada' => ['nullable', 'boolean'],
            'tiene_accesibilidad' => ['nullable', 'boolean'],

            'correo_contacto' => ['nullable', 'email', 'max:255'],
            'enlace_red_social' => ['nullable', 'url', 'max:255'],
            'enlace_web' => ['nullable', 'url', 'max:255'],

            // Taxonomías: los topes vienen del prototipo (3 temas, 5 características).
            'temas' => ['required', 'array', 'min:1', 'max:' . TaxonomyTerm::limiteDe('tema')],
            'temas.*' => ['exists:taxonomy_terms,id'],
            'caracteristicas' => ['nullable', 'array', 'max:' . TaxonomyTerm::limiteDe('caracteristica')],
            'caracteristicas.*' => ['exists:taxonomy_terms,id'],
            'publicos' => ['required', 'array', 'min:1'],
            'publicos.*' => ['exists:taxonomy_terms,id'],
            'accesos' => ['nullable', 'array'],
            'accesos.*' => ['exists:taxonomy_terms,id'],

            'colaboradores' => ['nullable', 'array', 'max:20'],
            'colaboradores.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'temas.max' => 'Puedes elegir hasta 3 temas principales.',
            'temas.required' => 'Elige al menos un tema.',
            'caracteristicas.max' => 'Puedes elegir hasta 5 características.',
            'publicos.required' => 'Indica a qué público está dirigida la actividad.',
            'fecha_inicio.required_without' => 'Indica la fecha, o marca que todavía no está definida.',
            'fecha_inicio.after_or_equal' => 'La fecha no puede ser anterior a hoy.',
            'commune_id.required_without' => 'Elige la comuna donde ocurre la actividad.',
            'hora_termino.after' => 'La hora de término debe ser posterior a la de inicio.',
            'email.unique' => 'Ya existe una cuenta con ese correo. Inicia sesión para publicar otra actividad.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'org_tipo_otro.required_if' => 'Especifica qué tipo de organización es.',
            'org_num_voluntarios.required_if' => 'Indica cuántos trabajadores participan como voluntarios.',
            'org_unidad_educativa.required_if' => 'Indica qué unidad o comunidad educativa organiza.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'org_nombre' => 'nombre de la organización',
            'org_tipo' => 'tipo de organización',
            'org_descripcion' => 'descripción de la organización',
            'email' => 'correo',
            'password' => 'contraseña',
            'titulo' => 'nombre de la actividad',
            'descripcion' => 'descripción',
            'commune_id' => 'comuna',
            'direccion' => 'dirección',
        ];
    }
}
