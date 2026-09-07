<?php

/**
 * El correo de «actividad publicada», por los DOS caminos.
 *
 * Esto es lo que pidió Jonas expresamente: **la aprobación automática publica
 * sin pasar por revisión, y el QR tiene que salir igual por ahí.** No hay dos
 * ramas que mantener —los dos caminos acaban en
 * `ActivityModerationService::cambiar($actividad, 'publicada')`— pero eso hay
 * que comprobarlo, no suponerlo.
 *
 * Se envía por el transporte «array», que guarda el mensaje en memoria en vez
 * de mandarlo: así se mira el MIME de verdad, con sus adjuntos, sin depender de
 * que haya un servidor de correo levantado. Y la cola se pone en «sync» porque
 * si no el mensaje se queda encolado y no hay nada que mirar.
 *
 *     php artisan tinker --execute="require base_path('pruebas/correo-publicada.php');"
 */

use App\Models\Activity;
use App\Models\ActivityStatusLog;
use App\Models\Organization;
use App\Services\ActivityModerationService;
use App\Services\AprobacionAutomatica;

config(['mail.default' => 'array', 'queue.default' => 'sync']);

$resultados = [];
$di = function (string $que, bool $bien, string $extra = '') use (&$resultados) {
    $resultados[] = ($bien ? 'OK  ' : 'MAL ').str_pad($que, 56).$extra;
};

$organizacion = Organization::first();
$moderacion = app(ActivityModerationService::class);

/**
 * Lo que hay en el buzón en memoria.
 *
 * Devuelve una colección, no un array: `end()` sobre una colección está
 * obsoleto y devuelve `false`, que es como se cayó la primera versión de esto.
 */
$buzon = fn () => app('mailer')->getSymfonyTransport()->messages();

/** Deja el buzón vacío, para que cada camino mire sólo su propio correo. */
$vaciar = fn () => app('mailer')->getSymfonyTransport()->flush();

/** Lo que se puede decir de un correo recién salido. */
$mirar = function ($mensajes) {
    if ($mensajes->isEmpty()) {
        return null;
    }

    $email = $mensajes->last()->getOriginalMessage();
    $html = (string) $email->getHtmlBody();
    $adjuntos = $email->getAttachments();

    preg_match('/<img[^>]+src="cid:([^"]+)"/', $html, $m);

    $cids = array_map(fn ($a) => $a->getContentId(), $adjuntos);

    return [
        'asunto' => $email->getSubject(),
        'para' => implode(', ', array_map(fn ($d) => $d->getAddress(), $email->getTo())),
        'adjuntos' => count($adjuntos),
        'imagen' => $m[1] ?? null,
        'cuadra' => isset($m[1]) && in_array($m[1], $cids, true),
        'bytes' => $adjuntos ? strlen($adjuntos[0]->getBody()) : 0,
        'boton' => str_contains($html, 'Descargar el QR para imprimir'),
        'titulo' => str_contains($html, 'Tu actividad ya está publicada'),
    ];
};

$crear = fn (string $slug, string $estado) => Activity::create([
    'organization_id' => $organizacion->id,
    'titulo' => 'Correo '.$slug,
    'slug' => 'prueba-correo-'.$slug.'-'.uniqid(),
    'descripcion' => 'Sembrada por pruebas/correo-publicada.php.',
    'formato' => 'Presencial',
    'lugar' => 'Sede central',
    'fecha_inicio' => now()->addDays(20)->toDateString(),
    'estado' => $estado,
]);

// ══ Camino 1: la revisión a mano ═══════════════════════════

$vaciar();

$aMano = $crear('a-mano', 'revision');
$moderacion->cambiar($aMano, 'publicada', comentario: 'Aprobada en la prueba.');

$correo = $mirar($buzon());

$di('El camino de revisión manda el correo', $correo !== null);

if ($correo) {
    $di('  Con el asunto correcto', $correo['asunto'] === 'Tu actividad ya está publicada', $correo['asunto']);
    $di('  Al organizador', $correo['para'] !== '', $correo['para']);
    $di('  Lleva el QR incrustado', $correo['adjuntos'] === 1, $correo['adjuntos'].' adjunto(s)');
    $di('  El <img> apunta a ese adjunto', $correo['cuadra'] === true, $correo['imagen'] ?? '(sin img)');
    $di('  Y el PNG tiene contenido', $correo['bytes'] > 200, $correo['bytes'].' bytes');
    $di('  Con el botón de descarga por si no cargan imágenes', $correo['boton'] === true);
    $di('  Y el texto de la plantilla del panel', $correo['titulo'] === true);
}

// ══ Camino 2: la aprobación automática ═════════════════════
//
// Es el que importa comprobar. Se monta el escenario que la dispara: una
// organización que YA publicó antes (el `published_at` de la anterior) y sin
// correcciones pendientes.

$aprobacion = app(AprobacionAutomatica::class);

/*
 * Hay que montar el escenario, no darlo por hecho.
 *
 * La aprobación automática se pausa mientras la organización tenga alguna
 * actividad esperando correcciones, y la base sembrada tiene una. Eso es el
 * servicio funcionando bien: si no se aparta, lo que se prueba aquí es que la
 * pausa funciona, no que el QR salga por el camino automático.
 *
 * Se apartan y se devuelven al final, para no dejar la base cambiada.
 */
$enAjustes = Activity::where('organization_id', $organizacion->id)
    ->where('estado', 'ajustes')
    ->pluck('id');

Activity::whereIn('id', $enAjustes)->update(['estado' => 'borrador']);

$di('La organización ya publicó antes', $aprobacion->yaPublico($organizacion));
$di('Así que la aprobación automática aplica', $aprobacion->aplica($organizacion),
    $aprobacion->motivoDeRevision($organizacion) ?? 'sin motivo de revisión');

[$estado, $motivo] = $aprobacion->estadoAlEnviar($organizacion);
$di('Y una actividad enviada nace publicada', $estado === 'publicada', $estado.' — '.$motivo);

$vaciar();

$sola = $crear('automatica', 'borrador');
$moderacion->cambiar($sola, $estado, comentario: $motivo, automatica: true);

$correoAuto = $mirar($buzon());

$di('**La aprobación automática TAMBIÉN manda el correo**', $correoAuto !== null);

if ($correoAuto) {
    $di('  Con su QR incrustado igual', $correoAuto['adjuntos'] === 1 && $correoAuto['cuadra'],
        $correoAuto['adjuntos'].' adjunto(s)');
    $di('  Y el mismo botón de descarga', $correoAuto['boton'] === true);
}

$sola->refresh();
$di('Queda marcada como publicada sin revisar', $sola->publicada_automaticamente === true);
$di('Y el historial guarda el motivo',
    ActivityStatusLog::where('activity_id', $sola->id)->where('a_estado', 'publicada')->value('comentario') !== null);

// ══ Y el QR de las dos apunta a su propia encuesta ═════════

$qr = app(App\Services\CodigoQr::class);
$di('Cada actividad tiene su propia dirección',
    $qr->destino($aMano) !== $qr->destino($sola),
    $qr->destino($sola));

// ══ Limpieza ═══════════════════════════════════════════════

foreach ([$aMano, $sola] as $borrar) {
    ActivityStatusLog::where('activity_id', $borrar->id)->delete();
    $borrar->forceDelete();
}

// Las que se apartaron para poder probar el camino automático.
Activity::whereIn('id', $enAjustes)->update(['estado' => 'ajustes']);

echo implode(PHP_EOL, $resultados).PHP_EOL;
echo 'CORREO-PUBLICADA '.count(array_filter($resultados, fn ($r) => str_starts_with($r, 'OK'))).'/'.count($resultados).PHP_EOL;
