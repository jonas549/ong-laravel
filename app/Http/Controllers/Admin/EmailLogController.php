<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Listeners\LogSentMail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Services\DiagnosticoCorreo;
use App\Services\SmtpConfigService;
use App\Support\Filtro;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailLogController extends Controller
{
    public function index(Request $request)
    {
        $filtros = [
            'status' => Filtro::texto($request, 'status'),
            'plantilla' => Filtro::texto($request, 'plantilla'),
            'q' => Filtro::texto($request, 'q'),
            'desde' => Filtro::texto($request, 'desde'),
            'hasta' => Filtro::texto($request, 'hasta'),
        ];

        $correos = EmailLog::query()
            ->when($filtros['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($filtros['plantilla'], fn ($q, $p) => $p === 'sin_plantilla'
                ? $q->whereNull('plantilla')
                : $q->where('plantilla', $p))
            ->when($filtros['desde'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filtros['hasta'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($filtros['q'], function ($q, $b) {
                $b = Filtro::like($b);

                // El buscador cubre destinatario, asunto y cuerpo.
                $q->where(function ($w) use ($b) {
                    $w->where('to', 'like', "%{$b}%")
                        ->orWhere('subject', 'like', "%{$b}%")
                        ->orWhere('body_html', 'like', "%{$b}%");
                });
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.emails.index', [
            'correos' => $correos,
            'filtros' => $filtros,
            'enviados' => EmailLog::enviados()->count(),
            'fallidos' => EmailLog::fallidos()->count(),
            'enCola' => EmailLog::enCola()->count(),
            'plantillas' => EmailTemplate::orderBy('nombre')->pluck('nombre', 'clave'),
            // Sin esto, una cola parada o un mailer que no entrega se leían en
            // esta pantalla como "todavía no se ha enviado nada".
            'salud' => $this->salud(),
        ]);
    }

    /**
     * Lo que hay que saber antes de mirar la tabla: si el transporte entrega de
     * verdad y si la cola avanza. Son los dos fallos que dejaban al panel
     * enseñando una lista tranquilizadora mientras no salía un solo correo.
     *
     * @return array<string, mixed>
     */
    private function salud(): array
    {
        $diagnostico = app(DiagnosticoCorreo::class);

        return [
            'transporte' => $diagnostico->transporte(),
            'cola' => $diagnostico->cola(),
            'plantillas' => $diagnostico->plantillas(),
        ];
    }

    public function show(EmailLog $email)
    {
        return view('admin.emails.show', compact('email'));
    }

    /**
     * Reenvía un correo fallido.
     *
     * Se reenvía el HTML tal como quedó registrado, no se reconstruye el
     * mailable: reconstruirlo exigiría que el modelo relacionado siguiera
     * existiendo y en el mismo estado, y no siempre es así.
     */
    public function resend(EmailLog $email, SmtpConfigService $smtp)
    {
        if (blank($email->to)) {
            return back()->with('error', 'Ese registro no tiene destinatario.');
        }

        try {
            $smtp->aplicar();

            $destinos = array_filter(array_map('trim', explode(',', $email->to)));
            $asunto = $email->subject ?: '(sin asunto)';

            // La cabecera le dice al log que actualice esta misma fila en vez
            // de crear una nueva: si no, cada reenvío dejaba un registro
            // huérfano y el original seguía marcado como fallido.
            Mail::html($email->body_html ?? '', function ($mensaje) use ($destinos, $asunto, $email) {
                $mensaje->to($destinos)->subject($asunto);
                $mensaje->getSymfonyMessage()->getHeaders()
                    ->addTextHeader(LogSentMail::CAB_REENVIO, (string) $email->getKey());
            });

            $email->update(['attempts' => $email->attempts + 1]);

            return back()->with('ok', "Reenviado a {$email->to}.");
        } catch (Throwable $e) {
            return back()
                ->with('error', 'No se pudo reenviar.')
                ->with('detalle_smtp', $e->getMessage());
        }
    }
}
