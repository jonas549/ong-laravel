<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

/**
 * Verificación del correo.
 *
 * Verificar NO bloquea el uso de la cuenta: en el prototipo se publica y se
 * entra sin ningún paso intermedio, y meter un muro ahí cambiaría ese
 * recorrido. Se avisa con un banner en el perfil y en el listado, y queda como
 * decisión pendiente si en algún momento debe bloquear.
 */
class VerificacionController extends Controller
{
    /** Pantalla de "revisa tu correo". */
    public function notice(Request $request)
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route($this->destino($request))
            : view('account.auth.verificar');
    }

    /** El enlace del correo. La firma la valida el propio request de Laravel. */
    public function verify(EmailVerificationRequest $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($this->destino($request))
                ->with('ok', 'Tu correo ya estaba confirmado.');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->route($this->destino($request))
            ->with('ok', 'Listo, tu correo quedó confirmado.');
    }

    public function send(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return back()->with('ok', 'Tu correo ya estaba confirmado.');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('ok', 'Te reenviamos el correo de confirmación.');
    }

    private function destino(Request $request): string
    {
        return $request->user()->esAdmin() ? 'admin.dashboard' : 'account.activities.index';
    }
}
