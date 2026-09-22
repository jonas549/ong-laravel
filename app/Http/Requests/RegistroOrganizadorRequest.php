<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Rules\CorreoEnviable;
use App\Support\ReclamarOrganizacion;
use App\Support\ReglasDeCampo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear cuenta de organizador desde `/mi-cuenta/registro`.
 *
 * Las reglas de la organización son **las mismas** que las del paso 3 del
 * wizard, y por eso vienen del trait `ReclamarOrganizacion` en vez de estar
 * escritas otra vez: cuando estaban repetidas, esta pantalla se quedó sin el
 * buscador ni el reclamar durante una tanda entera (C1).
 *
 * Estaba como `$request->validate()` dentro del controlador. Se sacó aquí
 * porque ahora hay reglas que dependen de una consulta —la organización que se
 * reclama— y eso no cabe en una lista de cadenas.
 */
class RegistroOrganizadorRequest extends FormRequest
{
    use ReclamarOrganizacion;

    /**
     * Cualquiera puede registrarse: es una pantalla pública.
     *
     * Quien ya tiene sesión no llega aquí —el controlador lo devuelve a su
     * panel antes— y esa comprobación se queda allí a propósito: no es un
     * permiso, es una redirección.
     */
    public function authorize(): bool
    {
        return true;
    }

    /** Espacios de más en el nombre, fuera, antes de comprobar si se repite. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('org_nombre'))) {
            $this->merge([
                'org_nombre' => preg_replace('/\s+/u', ' ', trim($this->input('org_nombre'))),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /*
         * Reclamando una organización del listado, su tipo ya está decidido y
         * el formulario ni lo pinta. Exigirlo rebotaría pidiendo un campo que
         * no está en pantalla, que es el peor error de todos (bloque K).
         */
        $reclamando = $this->reclamada() !== null;

        return [
            'org_nombre' => ['required', 'string', 'max:255', $this->organizacionSinRepetir()],
            'org_id' => $this->reglaDelIdDeOrganizacion(),

            'org_tipo' => $reclamando
                ? ['nullable', Rule::in(Organization::TIPOS)]
                : ['required', Rule::in(Organization::TIPOS)],
            'org_tipo_otro' => ['nullable', 'required_if:org_tipo,Otra', 'string', 'max:255'],
            'org_unidad_educativa' => ['nullable', 'required_if:org_tipo,Institución educativa', 'string', 'max:255'],

            /*
             * B5/B6: el logo se exige en la pantalla —salvo en «Otra» y salvo
             * en el teléfono— y aquí se queda `nullable`. El servidor no sabe
             * desde qué pantalla se envía, y exigirlo rebotaría a quien se
             * registra desde el móvil pidiéndole un campo que no se le pidió.
             */
            'org_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:500'],

            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', new CorreoEnviable, Rule::unique('users', 'email')],
            /*
             * El tope de 72 no es capricho: bcrypt ignora lo que pase de ahí.
             * Sin ese límite alguien podía elegir una contraseña de 100
             * caracteres y entrar después con los primeros 72, sin enterarse.
             */
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            // El mismo texto que el wizard, y por la misma constante: los
            // enlaces de debajo del campo se pintan reconociéndolo.
            'email.unique' => ReglasDeCampo::CORREO_YA_EXISTE,
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.max' => 'La contraseña no puede pasar de 72 caracteres.',
            'org_tipo_otro.required_if' => 'Especifica qué tipo de organización es.',
            'org_unidad_educativa.required_if' => 'Indica el nombre de la unidad educativa.',
            'org_logo.max' => 'El logo no puede pesar más de 500 KB.',
            'org_nombre.unique' => 'Ya hay una organización registrada con ese nombre. '
                .'Si es la tuya, inicia sesión con la cuenta que la creó. '
                .'Si es otra distinta, escribe un nombre que la diferencie.',
            'org_id.exists' => 'Esa organización ya tiene una cuenta, o ya no está disponible. '
                .'Si es la tuya, inicia sesión para publicar con ella.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'org_nombre' => 'el nombre de la organización',
            'org_tipo' => 'el tipo de organización',
            'org_logo' => 'el logo de la organización',
            'name' => 'el nombre',
            'email' => 'el correo',
            'password' => 'la contraseña',
        ];
    }
}
