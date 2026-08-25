<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\MailTestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SmtpSettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings.smtp', [
            'ajustes' => Setting::grupo('smtp')->ordered()->get(),
            'valores' => Setting::todos(),
        ]);
    }

    public function update(Request $request)
    {
        $datos = $request->validate([
            'smtp_activo' => ['nullable', 'boolean'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl,none'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_from_address' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
        ], [], [
            'smtp_host' => 'servidor SMTP',
            'smtp_port' => 'puerto',
            'smtp_from_address' => 'correo remitente',
        ]);

        // En una transacción: si un valor revienta a mitad del bucle, la
        // configuración quedaba guardada a medias y el correo dejaba de salir.
        DB::transaction(function () use ($request, $datos) {
            Setting::set('smtp_activo', $request->boolean('smtp_activo'));

            foreach (['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_from_address', 'smtp_from_name'] as $clave) {
                Setting::set($clave, $datos[$clave] ?? null);
            }

            // La contraseña en blanco significa "no la cambies": si la
            // sobrescribiéramos, guardar cualquier otro campo la borraría.
            if (filled($datos['smtp_password'] ?? null)) {
                Setting::set('smtp_password', $datos['smtp_password']);
            }
        });

        return back()->with('ok', 'Configuración SMTP guardada.');
    }

    public function sendTest(Request $request, MailTestService $prueba)
    {
        $datos = $request->validate([
            'destino' => ['required', 'email'],
        ], [
            'destino.required' => 'Escribe a qué dirección enviamos la prueba.',
            'destino.email' => 'Esa dirección no parece válida.',
        ], ['destino' => 'destino']);

        $resultado = $prueba->enviar($datos['destino']);

        return back()->with($resultado['ok'] ? 'ok' : 'error', $resultado['mensaje'])
            ->with('detalle_smtp', $resultado['detalle']);
    }
}
