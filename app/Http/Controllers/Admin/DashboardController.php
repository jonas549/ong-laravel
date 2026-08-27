<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DiagnosticoCorreo;
use App\Services\ResumenPanel;

/**
 * La portada del panel.
 *
 * Delgado a propósito: los números y sus definiciones viven en ResumenPanel,
 * porque cada uno lleva detrás una decisión —qué cuenta como organización
 * activa, desde cuándo se mide una espera— y esas decisiones tienen que poder
 * leerse juntas y comprobarse sin pasar por una petición HTTP.
 */
class DashboardController extends Controller
{
    public function index(ResumenPanel $resumen, DiagnosticoCorreo $correo)
    {
        return view('admin.dashboard', $resumen->kpis() + [
            'evolucion' => $resumen->evolucion(),
            'pendientesDeRevision' => $resumen->pendientesDeRevision(),
            'ultimasInscripciones' => $resumen->ultimasInscripciones(),
            'alertas' => $resumen->alertas(),
            // El estado del correo lo cuenta su propio aviso, que ya distingue
            // un transporte que no entrega de una cola sin worker.
            'salud' => $correo->salud(),
        ]);
    }
}
