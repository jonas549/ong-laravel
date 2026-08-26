<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PlantillaMail;
use App\Models\EmailTemplate;
use App\Services\EmailTemplateRenderer;
use App\Services\SmtpConfigService;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailTemplateController extends Controller
{
    public function __construct(
        private EmailTemplateRenderer $renderer,
        private SmtpConfigService $smtp,
    ) {
    }

    public function index()
    {
        $plantillas = EmailTemplate::orderBy('nombre')->get();

        return view('admin.templates.index', [
            'plantillas' => $plantillas,
            // Las del catálogo que no están en la base de datos. Si falta
            // alguna, el correo que la usa no se envía y no deja rastro: es un
            // fallo mudo, así que esta pantalla tiene que enseñarlo.
            'faltan' => collect(EmailTemplate::CATALOGO)
                ->reject(fn ($meta, $clave) => $plantillas->contains('clave', $clave))
                ->all(),
        ]);
    }

    /**
     * Vuelve a crear las plantillas del catálogo que falten.
     *
     * Las claves las fija el código (`EmailTemplate::CATALOGO`) porque
     * `CorreoTransaccional` pide cada una por su nombre: una plantilla
     * inventada a mano desde el panel no la enviaría nadie, y por eso aquí se
     * restaura en vez de crear libremente. Lo que ya existe no se toca: esos
     * textos son de la ONG.
     */
    public function restaurar()
    {
        $antes = EmailTemplate::pluck('clave')->all();

        (new EmailTemplateSeeder)->run();

        $creadas = EmailTemplate::whereNotIn('clave', $antes ?: ['ninguna'])->pluck('nombre');

        return back()->with(
            $creadas->isEmpty() ? 'aviso' : 'ok',
            $creadas->isEmpty()
                ? 'No faltaba ninguna: las cinco plantillas ya estaban.'
                : 'Restauradas '.$creadas->count().': '.$creadas->implode(', ').'.',
        );
    }

    public function edit(EmailTemplate $template)
    {
        return view('admin.templates.form', [
            'plantilla' => $template,
            'variables' => $template->variablesDisponibles(),
            'desconocidas' => $template->variablesDesconocidas(),
        ]);
    }

    public function update(Request $request, EmailTemplate $template)
    {
        $datos = $this->validar($request);

        $template->update($datos);

        $desconocidas = $template->fresh()->variablesDesconocidas();

        return redirect()
            ->route('admin.templates.edit', $template)
            ->with('ok', 'Plantilla guardada.')
            ->with($desconocidas ? 'aviso' : 'nada',
                'Hay marcadores que no se sustituirán y saldrán tal cual en el correo: {{ '
                . implode(' }}, {{ ', $desconocidas) . ' }}');
    }

    /**
     * Vista previa con datos de ejemplo. Se renderiza sobre una copia en
     * memoria, así que se puede ver cómo queda antes de guardar.
     */
    public function preview(Request $request, EmailTemplate $template)
    {
        $datos = $this->validar($request);

        $copia = $template->replicate()->forceFill($datos);
        $render = $this->renderer->render($copia, $this->renderer->datosDeEjemplo($copia));

        return response()->json([
            'asunto' => $render['asunto'],
            'html' => view('emails.plantilla', ['cuerpo' => $render['html']])->render(),
            'desconocidas' => $copia->variablesDesconocidas(),
        ]);
    }

    /** Envío de prueba de esta plantilla concreta, con datos de ejemplo. */
    public function test(Request $request, EmailTemplate $template)
    {
        $destino = $request->validate(
            ['destino' => ['required', 'email']],
            [],
            ['destino' => 'correo de destino'],
        )['destino'];

        try {
            $this->smtp->aplicar();

            Mail::to($destino)->send(
                new PlantillaMail($template, $this->renderer->datosDeEjemplo($template))
            );

            return back()->with('ok', "Correo de prueba encolado hacia {$destino}.");
        } catch (Throwable $e) {
            return back()
                ->with('error', 'No se pudo enviar la prueba.')
                ->with('detalle_smtp', $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
    {
        return $request->validate([
            'asunto' => ['required', 'string', 'max:255'],
            'cuerpo_html' => ['required', 'string', 'max:60000'],
            'activo' => ['nullable', 'boolean'],
        ], [], [
            'asunto' => 'asunto',
            'cuerpo_html' => 'cuerpo del correo',
        ]) + ['activo' => $request->boolean('activo')];
    }
}
