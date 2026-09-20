<?php

namespace App\Http\Requests;

use App\Models\Activity;
use App\Models\Organization;
use App\Support\ReglasDeCampo;
use App\Models\TaxonomyTerm;
use App\Rules\CorreoEnviable;
use App\Support\FechaEscrita;
use Illuminate\Foundation\Http\FormRequest;
use App\Support\ArchivosRetenidos;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

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

            /*
             * El nombre de la organizacion se normaliza ANTES de comprobar si
             * esta repetido. Sin esto, «Fundacion X», «Fundacion  X» y
             * «Fundacion X » son tres cadenas distintas para la comprobacion y
             * la misma entidad para cualquier persona, que es justo como se
             * cuelan los duplicados. Las mayusculas ya las iguala el cotejo de
             * MySQL; los espacios de sobra, no.
             */
            'org_nombre' => is_string($this->input('org_nombre'))
                ? preg_replace('/\s+/u', ' ', trim($this->input('org_nombre')))
                : $this->input('org_nombre'),
        ]);
    }

    /**
     * «Ese nombre de organizacion ya esta tomado».
     *
     * Punto 19 de la tanda del 11/09: en produccion hay dos organizaciones
     * llamadas «deltadigital.cl» con correos distintos, creadas por el wizard
     * sin que nada lo impidiera.
     *
     * Dos decisiones que hay que conocer:
     *
     * 1. **Va en el formulario y NO como indice unico en la base.** Lo decidio
     *    Jonas el 18/09: mientras existan los duplicados de hoy, una migracion
     *    que anada el indice se caeria al aplicarse. Queda como deuda, anotada
     *    en BACKLOG-BACKEND.md.
     * 2. **Se ignora la organizacion propia.** Quien ya tiene cuenta reusa su
     *    organizacion al publicar una segunda actividad, y el formulario le
     *    devuelve su propio nombre: sin el `ignore` no podria volver a
     *    publicar nunca.
     *
     * Las borradas en blando no cuentan: su nombre vuelve a estar libre.
     */
    private function organizacionSinRepetir(): Unique
    {
        $regla = Rule::unique('organizations', 'nombre')->whereNull('deleted_at');

        if ($propia = $this->user()?->organization) {
            $regla->ignore($propia->id);
        }

        /*
         * Y se ignora también la que se esté reclamando del listado histórico.
         *
         * Sin esto, P9 y el punto 19 se pelean: el buscador ofrece una
         * organización que ya existe, la persona la elige, y el formulario la
         * rechaza por repetida. Reclamar no es crear un duplicado —es ponerle
         * dueño a una fila que no lo tenía—, así que su propio nombre no puede
         * ser motivo de rechazo.
         *
         * Se comprueba contra la base y no contra lo que llegue en el POST:
         * `reclamada()` sólo devuelve la organización si de verdad está libre.
         */
        if ($reclamada = $this->reclamada()) {
            $regla->ignore($reclamada->id);
        }

        return $regla;
    }

    /**
     * La organización del listado que se está reclamando, si es reclamable.
     *
     * **Se relee de la base entera.** El id viaja en un campo oculto, así que
     * decidir con lo que diga el navegador dejaría reclamar una organización
     * que ya tiene dueño sólo con cambiar el número: el resultado sería una
     * cuenta nueva colgada de una organización ajena, con sus actividades
     * dentro. Aquí se exige que siga libre y activa.
     *
     * Se memoriza porque la piden la regla del nombre, la del id y el
     * controlador, y son tres consultas para lo mismo dentro de una petición.
     */
    public function reclamada(): ?Organization
    {
        if ($this->user()) {
            return null;
        }

        if ($this->organizacionReclamada !== false) {
            return $this->organizacionReclamada;
        }

        $id = (int) $this->input('org_id');

        return $this->organizacionReclamada = $id > 0
            ? Organization::sinReclamar()->where('activo', true)->find($id)
            : null;
    }

    /** `false` significa «todavía no se ha mirado»; `null`, «no hay». */
    private Organization|null|false $organizacionReclamada = false;

    /**
     * Antes de rechazar, conserva los archivos que SI venian bien.
     *
     * Un `<input type="file">` no se puede rellenar desde el servidor, asi que
     * sin esto cada rebote del formulario tira lo que la persona ya habia
     * subido — y como el logo es opcional, la organizacion se creaba sin el sin
     * que nadie se enterara. El porque completo, en `ArchivosRetenidos`.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        ArchivosRetenidos::guardar($this, $validator, ['org_logo', 'imagen']);

        parent::failedValidation($validator);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Paso 3 — organización y acceso. El prototipo no pide descripción
            // de la organización, así que acá tampoco es obligatoria.
            'org_nombre' => ['required', 'string', 'max:255', $this->organizacionSinRepetir()],
            /*
             * El id de la organización que se reclama del listado histórico.
             * Existe, está activa y NO tiene cuenta: las tres cosas, porque
             * este campo lo escribe el navegador.
             */
            'org_id' => [
                'nullable',
                'integer',
                Rule::exists('organizations', 'id')->whereNull('deleted_at')->whereNull('user_id')->where('activo', true),
            ],
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
            'direccion' => ['nullable', Rule::requiredIf(
                // Punto 5 de la tanda del 11/09: una actividad ONLINE no tiene
                // dirección física que escribir. Va como `requiredIf` y no como
                // dos reglas `required_*` sueltas porque Laravel las evalúa por
                // separado: cada una que dispare hace obligatorio el campo, así
                // que sumarlas daría un O y lo que hace falta es un Y —sólo se
                // pide si NO es permanente y ADEMÁS no es online—.
                fn () => ! $this->boolean('sin_fecha_definida') && $this->input('formato') !== 'Online'
            ), 'string', 'max:255'],

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
            'direccion.required' => 'Escribe la dirección, o marca que está disponible de forma permanente.',
            'commune_id.required_without' => 'Elige la comuna donde ocurre la actividad.',
            /*
             * P11. Antes decía qué pasaba pero no a dónde ir, y quien no
             * recuerda la contraseña se quedaba igual de atascado.
             *
             * El texto va en plano y los dos enlaces los pinta el paso 3, justo
             * debajo del campo: el resumen de errores de arriba escribe los
             * mensajes con `x-text`, que escapa el HTML, así que unas etiquetas
             * metidas aquí se leerían literales. La frase se reconoce por
             * `ReglasDeCampo::CORREO_YA_EXISTE` para no comparar cadenas
             * sueltas en dos archivos.
             */
            'email.unique' => ReglasDeCampo::CORREO_YA_EXISTE,
            'org_id.exists' => 'Esa organización ya tiene una cuenta, o ya no está disponible. '
                .'Si es la tuya, inicia sesión para publicar con ella.',
            'org_nombre.unique' => 'Ya hay una organización registrada con ese nombre. '
                .'Si es la tuya, inicia sesión con la cuenta que la creó y podrás sumar la actividad desde ahí. '
                .'Si es otra organización distinta, escribe un nombre que la diferencie.',
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
