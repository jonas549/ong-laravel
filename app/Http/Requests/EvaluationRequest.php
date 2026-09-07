<?php

namespace App\Http\Requests;

use App\Models\Activity;
use App\Models\ActivityEvaluation;
use App\Rules\CorreoEnviable;
use App\Services\Evaluaciones;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

/**
 * La encuesta de evaluación que se responde tras escanear el QR.
 *
 * ── Por qué el plazo se comprueba aquí y no en el controlador ──
 *
 * Porque **un FormRequest valida ANTES de que corra el método del
 * controlador**, y este proyecto ya lo ha pagado dos veces. Si la comprobación
 * viviera en el controlador, quien enviara la encuesta de una actividad con el
 * plazo vencido recibiría primero los errores de validación de sus campos
 * vacíos —y sólo después, si acertaba a rellenarlos todos, se enteraría de que
 * la encuesta llevaba un mes cerrada.
 *
 * ── Anti-spam: cuatro capas y ningún captcha ──
 *
 * Un captcha es exactamente la barrera que el encargo pide evitar, y además
 * mete a un tercero en una página que recoge nombre, correo y una fotografía.
 * En su lugar:
 *
 * 1. `throttle:5,1` en la ruta (está en routes/web.php).
 * 2. **Campo trampa**: un campo que un humano no ve y un robot rellena.
 * 3. **Tiempo mínimo**: el instante en que se pintó el formulario viaja
 *    cifrado. Menos de cuatro segundos entre pintar y enviar no lo hace una
 *    persona que ha leído cinco preguntas.
 * 4. **El índice único `(activity_id, correo)`**, que es el que de verdad
 *    limita el daño: por mucho que insista, cada correo deja una respuesta.
 *
 * Las dos primeras fallan **en silencio y como si todo hubiera ido bien**, no
 * con un error. A un robot no se le explica qué le delató.
 */
class EvaluationRequest extends FormRequest
{
    /** El campo que un humano no ve. Si viene relleno, no lo rellenó un humano. */
    public const TRAMPA = 'sitio_web';

    /** El instante en que se pintó el formulario, cifrado. */
    public const RELOJ = 'formulario_abierto';

    /** Segundos mínimos entre pintar el formulario y enviarlo. */
    public const SEGUNDOS_MINIMOS = 4;

    /**
     * Aquí sólo se decide si esta encuesta admite respuestas ahora mismo.
     */
    public function authorize(): bool
    {
        return app(Evaluaciones::class)->abierta($this->actividad());
    }

    /**
     * Fuera de plazo no es un 403.
     *
     * Un 403 en una pantalla pública es una puerta cerrada sin explicación, y
     * quien llega aquí tiene un cartel delante y acaba de escanearlo. Se le
     * manda a la misma pantalla que vería entrando por GET, que sí le dice qué
     * pasó.
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            redirect()->route('evaluar.show', $this->actividad())
        );
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email', 'max:255', new CorreoEnviable],

            'experiencia' => ['required', 'integer', 'between:1,5'],
            'significado' => ['required', 'string', 'max:'.ActivityEvaluation::MAX_SIGNIFICADO],
            'motivacion' => ['required', 'integer', 'between:1,5'],

            'como_se_entero' => ['nullable', Rule::in(array_keys(ActivityEvaluation::ORIGENES))],

            /*
             * 5 MB es lo que promete el wireframe, así que es lo que se acepta.
             * Ojo: el navegador reduce la foto antes de subirla (ver
             * resources/js/evaluacion.js), así que en la práctica casi nunca
             * llega nada de este tamaño. La regla está para el caso de que el
             * JavaScript no corra.
             *
             * `mimes` mira el contenido del archivo, no la extensión: un .exe
             * renombrado a .jpg no pasa.
             */
            'foto' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'foto_autorizada' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nombre' => 'el nombre',
            'correo' => 'el correo',
            'experiencia' => 'la valoración de tu experiencia',
            'significado' => 'tu respuesta sobre el Patrimonio Social',
            'motivacion' => 'tu motivación para volver a participar',
            'como_se_entero' => 'cómo te enteraste',
            'foto' => 'la fotografía',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'experiencia.required' => 'Elige una nota del 1 al 5 para tu experiencia.',
            'motivacion.required' => 'Elige una nota del 1 al 5 para tu motivación.',
            'significado.required' => 'Cuéntanos brevemente qué significa para ti el Patrimonio Social.',
            'significado.max' => 'Tu respuesta no puede pasar de :max caracteres.',
            'foto.mimes' => 'La fotografía tiene que ser un archivo JPG o PNG.',
            'foto.max' => 'La fotografía no puede pesar más de 5 MB.',
        ];
    }

    /**
     * ¿Esto lo mandó un robot?
     *
     * Se pregunta aparte de las reglas a propósito: **no es un error de
     * validación**. Un error de validación se le enseña a la persona para que
     * lo corrija, y aquí no hay nada que corregir ni nadie a quien explicárselo.
     * El controlador responde como si todo hubiera ido bien y no guarda nada.
     */
    public function pareceRobot(): bool
    {
        if (filled($this->input(self::TRAMPA))) {
            return true;
        }

        return $this->segundosRellenando() < self::SEGUNDOS_MINIMOS;
    }

    /**
     * Cuánto tardó en rellenarlo.
     *
     * El instante viaja cifrado con la APP_KEY, así que no se puede falsificar
     * desde fuera. Un valor ilegible —una sesión vieja, un copiar y pegar del
     * HTML— se trata como 0 segundos: sospechoso.
     */
    private function segundosRellenando(): int
    {
        try {
            $abierto = (int) Crypt::decryptString((string) $this->input(self::RELOJ));
        } catch (DecryptException) {
            return 0;
        }

        return max(0, time() - $abierto);
    }

    /** La marca de tiempo cifrada que hay que meter en el formulario. */
    public static function relojParaElFormulario(): string
    {
        return Crypt::encryptString((string) time());
    }

    private function actividad(): Activity
    {
        return $this->route('activity');
    }
}
