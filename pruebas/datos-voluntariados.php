<?php

/*
 * Escenario de /voluntariados con varios voluntariados (15/10), para probar
 * en local el calendario y los filtros: la API real trae hoy uno solo.
 *
 * NO inventa nada que la API no pueda devolver. Cada oportunidad tiene la
 * forma de su contrato (`App\Support\VoluntariadosChile\Contrato`) y entra
 * por la sincronización de verdad, con la respuesta fingida por Http::fake:
 * lo que se guarda es lo que guardaría si llegara así de su API. Valores que
 * usa: los formatos documentados y el «En persona» observado; regiones como
 * texto («Metropolitana», «Región de Valparaíso»…); comunas como texto o
 * vacías; `continuous` y `fixed_range` con fechas sin hora; la categoría real
 * `CAT_ACE` y una de prueba con la misma forma (su catálogo completo no lo
 * tenemos).
 *
 * Las que ya hay en la base (la real de COANIQUEM) se mandan también, tal
 * cual las guarda `crudo`, para que la sincronización no las dé de baja.
 *
 * Las fechas se calculan desde hoy, así que el escenario no envejece.
 *
 *   php artisan tinker --execute="require base_path('pruebas/datos-voluntariados.php');"
 *   php artisan tinker --execute="\$limpiar = true; require base_path('pruebas/datos-voluntariados.php');"
 */

use App\Models\Voluntariado;
use App\Services\SincronizadorVoluntariados;
use App\Support\Fecha;
use Illuminate\Support\Facades\Http;

$prefijo = 'prueba-cal-';

if ($limpiar ?? false) {
    $n = Voluntariado::where('externo_id', 'like', $prefijo.'%')->delete();
    echo "Borrados {$n} voluntariados de prueba.\n";

    return;
}

$hoy = now(Fecha::zona())->startOfDay();
$d = fn (int $dias) => $hoy->copy()->addDays($dias)->toDateString();
$mesQueViene = $hoy->copy()->addMonthNoOverflow()->startOfMonth();

$op = fn (string $sufijo, array $cambios) => array_replace([
    'id' => $prefijo.$sufijo,
    'status' => 'postulación_abierta',
    'updated_at' => now()->toIso8601String(),
    'opportunity_url' => 'https://voluntariadoschile.cl/oportunidades?id='.$prefijo.$sufijo,
    'organization' => ['name' => 'Fundación de prueba', 'logo_url' => null, 'type' => 'Organizaciones sin fines de lucro', 'impact_areas' => [['code' => 'AI_SAL', 'name' => 'Salud']]],
    'title' => 'Voluntariado de prueba',
    'description' => 'Descripción de prueba.',
    'responsibilities' => 'Responsabilidades de prueba.',
    'external_link' => null,
    'cover_image_url' => null,
    'format' => 'Presencial',
    'volunteers_needed' => null,
    'location' => ['regions' => ['Metropolitana'], 'comunas' => []],
    'schedule' => ['type' => 'continuous', 'start_date' => null, 'end_date' => null],
    'category' => ['code' => 'CAT_ACE', 'name' => 'Acción comunitaria y emergencias'],
    'requirements' => [
        'minimum_age' => ['required' => false, 'detail' => null],
        'certificate' => ['required' => false, 'detail' => null],
        'profession_or_study' => ['required' => false, 'detail' => null],
        'prior_experience' => ['required' => false, 'detail' => null],
    ],
    'contact' => ['name' => 'Contacto de prueba', 'email' => 'contacto@prueba.test'],
], $cambios);

$rango = fn (string $desde, string $hasta) => ['type' => 'fixed_range', 'start_date' => $desde, 'end_date' => $hasta];
$categoriaPrueba = ['code' => 'CAT_PRUEBA', 'name' => 'Categoría de prueba'];

$sembradas = [
    // Con día, este mes y el que viene.
    $op('huerta', ['title' => 'Huerta comunitaria (prueba)', 'format' => 'Presencial',
        'location' => ['regions' => ['Metropolitana'], 'comunas' => ['Recoleta']],
        'schedule' => $rango($d(2), $d(2))]),
    $op('ropero', ['title' => 'Ropero solidario (prueba)', 'format' => 'En persona',
        'organization' => ['name' => 'Corporación Ropero de prueba', 'logo_url' => null, 'type' => null, 'impact_areas' => []],
        'location' => ['regions' => ['Región de Valparaíso'], 'comunas' => ['Viña del Mar']],
        'schedule' => $rango($d(3), $d(6))]),
    $op('tutorias', ['title' => 'Tutorías en línea (prueba)', 'format' => 'Online',
        'location' => ['regions' => ['Metropolitana', 'Biobío'], 'comunas' => []],
        'category' => $categoriaPrueba,
        'schedule' => $rango($d(2), $d(2))]),
    $op('limpieza', ['title' => 'Limpieza de playa (prueba)', 'format' => 'Híbrido',
        'location' => ['regions' => ['Biobío'], 'comunas' => []],
        'schedule' => $rango($d(2), $d(2))]),
    $op('acopio', ['title' => 'Centro de acopio (prueba)', 'format' => 'Presencial',
        'location' => ['regions' => ['Metropolitana'], 'comunas' => ['Santiago']],
        'schedule' => $rango($d(2), $d(2))]),
    // Cruza de este mes al que viene.
    $op('campamento', ['title' => 'Campamento de invierno (prueba)', 'format' => 'Presencial',
        'location' => ['regions' => ['Región de Los Lagos'], 'comunas' => ['Puerto Montt']],
        'schedule' => $rango($mesQueViene->copy()->subDays(2)->toDateString(), $mesQueViene->copy()->addDays(2)->toDateString())]),
    $op('feria', ['title' => 'Feria de reciclaje (prueba)', 'format' => 'Híbrido',
        'category' => $categoriaPrueba,
        'location' => ['regions' => ['Región de Valparaíso'], 'comunas' => []],
        'schedule' => $rango($mesQueViene->copy()->addDays(9)->toDateString(), $mesQueViene->copy()->addDays(9)->toDateString())]),
    // Empezó y sigue: sale, desde hoy.
    $op('en-curso', ['title' => 'Acompañamiento en curso (prueba)', 'format' => 'Presencial',
        'schedule' => $rango($d(-10), $d(5))]),
    // Convocatoria permanente: a la franja de arriba.
    $op('permanente-online', ['title' => 'Mentorías permanentes (prueba)', 'format' => 'Online',
        'location' => ['regions' => ['Metropolitana'], 'comunas' => []]]),
    // No deben salir: ya terminó, y cerrada.
    $op('terminada', ['title' => 'Ya terminó (prueba)', 'schedule' => $rango($d(-20), $d(-1))]),
    $op('cerrada', ['title' => 'Postulación cerrada (prueba)', 'status' => 'postulación_cerrada',
        'schedule' => $rango($d(4), $d(4))]),
];

// Las que ya estaban (la real), tal como llegaron, para que sigan activas.
$yaEstaban = Voluntariado::where('activo', true)->where('externo_id', 'not like', $prefijo.'%')
    ->get()->pluck('crudo')->filter()->values()->all();

$data = array_merge($yaEstaban, $sembradas);

Http::fake(fn () => Http::response([
    'generated_at' => now()->toIso8601String(),
    'pagination' => ['page' => 1, 'page_size' => SincronizadorVoluntariados::POR_PAGINA, 'total' => count($data)],
    'data' => $data,
]));

config(['services.voluntariados_chile.key' => config('services.voluntariados_chile.key') ?: 'clave-de-prueba']);

$r = app(SincronizadorVoluntariados::class)->sincronizar();

echo "Sincronización {$r->resultado}: {$r->recibidas} recibidas, {$r->altas} altas, {$r->cambios} cambios, {$r->bajas} bajas.\n";
echo 'Públicos ahora: '.Voluntariado::publicos()->count()."\n";
