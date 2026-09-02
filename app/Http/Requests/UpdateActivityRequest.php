<?php

namespace App\Http\Requests;

use App\Models\Activity;
use App\Models\ActivityCollaborator;
use App\Models\TaxonomyTerm;
use App\Rules\CorreoEnviable;
use App\Support\FechaEscrita;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Edición de una actividad desde "Mi cuenta".
 *
 * Es el mismo formulario del paso 4 del wizard más los campos que sólo
 * aparecen en la pantalla de edición de mi-cuenta.html (fecha de término,
 * cupos, info previa, tipo de colaborador). No lleva los datos de la
 * organización ni los de la cuenta: eso ya existe cuando se edita.
 *
 * Las fechas y horas llegan como texto, no como input[type=date]: el
 * prototipo usa campos de texto ("26 / 07 / 2026", "10:00") y además los
 * navegadores no dejan pegar en los campos nativos de fecha y hora.
 */
class UpdateActivityRequest extends FormRequest
{
    /**
     * El permiso se comprueba **aquí** y no en el controlador.
     *
     * `authorize()` corre antes que las reglas; `$this->authorize()` dentro del
     * método del controlador corre después. Con la comprobación allá, un
     * organizador que mandara un formulario incompleto contra la actividad de
     * otro recibía los errores de validación de una ficha que no es suya —un
     * 302 de vuelta al formulario— en vez del 403 que corresponde: la
     * autorización no llegaba a ejecutarse nunca. Lo detectó permisos.mjs.
     */
    public function authorize(): Response
    {
        // `inspect` y no `allows`: devuelve la Response de la policy, con su
        // mensaje. Con un bool el 403 saldría en inglés y sin explicar nada.
        return Gate::inspect('update', $this->route('activity'));
    }

    /**
     * Normaliza antes de validar: las fechas y horas del formulario vienen
     * en formato chileno y hay que dejarlas como las espera la base.
     *
     * La lectura vive en `App\Support\FechaEscrita`, compartida con el
     * wizard: estaba copiada en los dos.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'fecha_inicio' => FechaEscrita::fecha($this->input('fecha_inicio')),
            'fecha_termino' => FechaEscrita::fecha($this->input('fecha_termino')),
            'hora_inicio' => FechaEscrita::hora($this->input('hora_inicio')),
            'hora_termino' => FechaEscrita::hora($this->input('hora_termino')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['required', 'string', 'max:1000'],
            'formato' => ['required', Rule::in(Activity::FORMATOS)],

            'sin_fecha_definida' => ['nullable', 'boolean'],
            'fecha_inicio' => ['nullable', 'required_without:sin_fecha_definida', 'date'],
            'fecha_termino' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_termino' => ['nullable', 'date_format:H:i', 'after:hora_inicio'],

            'commune_id' => ['nullable', 'required_without:sin_fecha_definida', 'exists:communes,id'],
            'direccion' => ['nullable', 'required_without:sin_fecha_definida', 'string', 'max:255'],

            'participantes_estimados' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'cupos_totales' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'cupos_disponibles' => ['nullable', 'integer', 'min:0', 'max:100000'],

            'abierta_publico' => ['nullable', 'boolean'],
            'inscripcion_habilitada' => ['nullable', 'boolean'],
            'info_previa' => ['nullable', 'string', 'max:2000'],

            'correo_contacto' => ['nullable', 'email', 'max:255', new CorreoEnviable],
            'enlace_red_social' => ['nullable', 'url', 'max:255'],
            'enlace_web' => ['nullable', 'url', 'max:255'],

            // 2 MB y 1200×600 recomendado, como dice el propio formulario.
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'temas' => ['required', 'array', 'min:1', 'max:'.TaxonomyTerm::limiteDe('tema')],
            'temas.*' => ['exists:taxonomy_terms,id'],
            'caracteristicas' => ['nullable', 'array', 'max:'.TaxonomyTerm::limiteDe('caracteristica')],
            'caracteristicas.*' => ['exists:taxonomy_terms,id'],
            'publicos' => ['required', 'array', 'min:1'],
            'publicos.*' => ['exists:taxonomy_terms,id'],
            'accesos' => ['nullable', 'array'],
            'accesos.*' => ['exists:taxonomy_terms,id'],

            'colaboradores' => ['nullable', 'array', 'max:20'],
            'colaboradores.*.nombre' => ['nullable', 'string', 'max:255'],
            'colaboradores.*.tipo' => ['nullable', Rule::in(ActivityCollaborator::TIPOS)],
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
            'fecha_inicio.required_without' => 'Indica la fecha, o marca que está disponible de forma permanente.',
            'fecha_inicio.date' => 'Escribe la fecha como día / mes / año.',
            'fecha_termino.date' => 'Escribe la fecha como día / mes / año.',
            'fecha_termino.after_or_equal' => 'La fecha de término no puede ser anterior a la de inicio.',
            'hora_inicio.date_format' => 'Escribe la hora como HH:MM, por ejemplo 10:00.',
            'hora_termino.date_format' => 'Escribe la hora como HH:MM, por ejemplo 13:30.',
            'hora_termino.after' => 'La hora de término debe ser posterior a la de inicio.',
            'commune_id.required_without' => 'Elige la comuna donde ocurre la actividad.',
            'direccion.required_without' => 'Escribe la dirección, o marca que está disponible de forma permanente.',
            'imagen.max' => 'La imagen no puede pesar más de 2 MB.',
            'imagen.mimes' => 'La imagen debe ser JPG, PNG o WEBP.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'titulo' => 'nombre de la actividad',
            'descripcion' => 'la descripción',
            'commune_id' => 'comuna',
            'direccion' => 'dirección',
            'imagen' => 'imagen de la actividad',
        ];
    }
}
