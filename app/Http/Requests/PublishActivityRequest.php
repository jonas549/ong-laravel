<?php

namespace App\Http\Requests;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\TaxonomyTerm;
use App\Rules\CorreoEnviable;
use App\Support\FechaEscrita;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Las fechas y horas del paso 4 son campos de texto, como en el
     * prototipo, así que llegan en formato chileno y hay que normalizarlas.
     *
     * La lectura vive en `App\Support\FechaEscrita`, compartida con el
     * editor de «Mi cuenta»: estaba copiada en los dos, y dos copias de una
     * regla de lectura acaban entendiendo cosas distintas.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'fecha_inicio' => FechaEscrita::fecha($this->input('fecha_inicio')),
            'hora_inicio' => FechaEscrita::hora($this->input('hora_inicio')),
            'hora_termino' => FechaEscrita::hora($this->input('hora_termino')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Paso 3 — organización y acceso. El prototipo no pide descripción
            // de la organización, así que acá tampoco es obligatoria.
            'org_nombre' => ['required', 'string', 'max:255'],
            'org_tipo' => ['required', Rule::in(Organization::TIPOS)],
            'org_tipo_otro' => ['nullable', 'required_if:org_tipo,Otra', 'string', 'max:255'],
            'org_descripcion' => ['nullable', 'string', 'max:2000'],
            'org_num_voluntarios' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'org_unidad_educativa' => ['nullable', 'required_if:org_tipo,Institución educativa', 'string', 'max:255'],
            'org_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:500'],
            /*
             * Correo y contraseña sólo se piden a quien no tiene cuenta. Con la
             * sesión abierta el wizard no pinta ese bloque y la actividad va a
             * la cuenta que ya existe, así que exigirlos rebotaría un
             * formulario que no tiene dónde rellenarlos, y el `unique` daría
             * por repetido el correo del propio dueño.
             */
            'email' => $this->user()
                ? ['prohibited']
                : ['required', 'email', 'max:255', new CorreoEnviable, Rule::unique('users', 'email')],
            // El tope de 72 no es capricho: bcrypt ignora lo que pase de ahí.
            'password' => $this->user()
                ? ['prohibited']
                : ['required', 'string', 'min:8', 'max:72', 'confirmed'],

            // Paso 4 — la actividad
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:1000'],
            'formato' => ['required', Rule::in(Activity::FORMATOS)],
            'sin_fecha_definida' => ['nullable', 'boolean'],
            'fecha_inicio' => ['nullable', 'required_without:sin_fecha_definida', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_termino' => ['nullable', 'date_format:H:i', 'after:hora_inicio'],
            'region_id' => ['nullable', 'required_without:sin_fecha_definida', 'exists:regions,id'],
            'commune_id' => ['nullable', 'required_without:sin_fecha_definida', 'exists:communes,id'],
            'direccion' => ['nullable', 'required_without:sin_fecha_definida', 'string', 'max:255'],

            'participantes_estimados' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'cupos_totales' => ['nullable', 'integer', 'min:0', 'max:100000'],

            'inscripcion_habilitada' => ['nullable', 'boolean'],
            'tiene_accesibilidad' => ['nullable', 'boolean'],
            'accesibilidad_detalle' => ['nullable', 'string', 'max:2000'],

            'usar_correo_cuenta' => ['nullable', 'boolean'],
            'correo_contacto' => ['nullable', 'email', 'max:255', new CorreoEnviable],
            'enlace_red_social' => ['nullable', 'url', 'max:255'],
            'enlace_web' => ['nullable', 'url', 'max:255'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // Los topes vienen del prototipo (3 temas, 5 características).
            'temas' => ['required', 'array', 'min:1', 'max:'.TaxonomyTerm::limiteDe('tema')],
            'temas.*' => ['exists:taxonomy_terms,id'],
            'caracteristicas' => ['required', 'array', 'min:1', 'max:'.TaxonomyTerm::limiteDe('caracteristica')],
            'caracteristicas.*' => ['exists:taxonomy_terms,id'],
            'publicos' => ['required', 'array', 'min:1'],
            'publicos.*' => ['exists:taxonomy_terms,id'],
            'publico_otro' => ['nullable', 'string', 'max:255'],

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
            'caracteristicas.required' => 'Marca al menos una característica de tu actividad.',
            'caracteristicas.max' => 'Puedes elegir hasta 5 características.',
            'publicos.required' => 'Indica a qué público está dirigida la actividad.',
            'fecha_inicio.required_without' => 'Indica la fecha, o marca que está disponible de forma permanente.',
            'fecha_inicio.date' => 'Escribe la fecha como día / mes / año.',
            'fecha_inicio.after_or_equal' => 'La fecha no puede ser anterior a hoy.',
            'hora_inicio.date_format' => 'Escribe la hora como HH:MM, por ejemplo 09:00.',
            'hora_termino.date_format' => 'Escribe la hora como HH:MM, por ejemplo 13:00.',
            'hora_termino.after' => 'La hora de término debe ser posterior a la de inicio.',
            'region_id.required_without' => 'Elige la región donde ocurre la actividad.',
            'direccion.required_without' => 'Escribe la dirección, o marca que está disponible de forma permanente.',
            'commune_id.required_without' => 'Elige la comuna donde ocurre la actividad.',
            'email.unique' => 'Ya existe una cuenta con ese correo. Inicia sesión para publicar otra actividad.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'org_tipo_otro.required_if' => 'Especifica qué tipo de organización es.',
            'org_unidad_educativa.required_if' => 'Indica qué unidad o comunidad educativa organiza.',
            'org_logo.max' => 'El logo no puede pesar más de 500 KB.',
            'imagen.max' => 'La imagen de portada no puede pesar más de 2 MB.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'org_nombre' => 'el nombre de la organización',
            'org_tipo' => 'el tipo de organización',
            'org_logo' => 'logo de la organización',
            'email' => 'el correo',
            'password' => 'la contraseña',
            'titulo' => 'nombre de la actividad',
            'descripcion' => 'la descripción',
            'region_id' => 'región',
            'commune_id' => 'comuna',
            'direccion' => 'dirección',
            'imagen' => 'imagen de portada',
        ];
    }
}
