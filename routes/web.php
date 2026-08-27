<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\RegistrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Públicas
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/actividades', [ActivityController::class, 'index'])->name('activities.index');
Route::get('/actividades/{activity:slug}', [ActivityController::class, 'show'])->name('activities.show');
Route::post('/actividades/{activity:slug}/inscribirse', [RegistrationController::class, 'store'])
    ->name('registrations.store');
Route::get('/inscripcion/{token}/cancelar', [RegistrationController::class, 'cancel'])
    ->name('registrations.cancel');

// El wizard es sólo para quien no tiene cuenta: crea una nueva y entra con
// ella, así que a quien ya está dentro se le manda a sumar la actividad desde
// su cuenta en vez de dejar la anterior huérfana.
$avisoWizard = 'invitado:Ya tienes la sesión abierta. Suma la actividad desde aquí, con "Sumar nueva actividad", y así queda en tu misma cuenta.';

Route::get('/publicar-actividad', [PublishController::class, 'create'])
    ->middleware($avisoWizard)
    ->name('publish.create');

// El mismo freno que el registro: este POST también crea cuenta y dispara
// tres correos, así que sin límite era la vía para saltarse el del registro.
Route::post('/publicar-actividad', [PublishController::class, 'store'])
    ->middleware([$avisoWizard, 'throttle:10,1'])
    ->name('publish.store');
Route::get('/publicar-actividad/{activity:slug}/listo', [PublishController::class, 'done'])->name('publish.done');

Route::get('/noticias', [PostController::class, 'index'])->name('posts.index');
Route::get('/noticias/{post:slug}', [PostController::class, 'show'])->name('posts.show');

/*
|--------------------------------------------------------------------------
| Verificación de correo
|--------------------------------------------------------------------------
| Los nombres son los estándar (verification.*) porque son los que usa el
| enlace firmado que arma la notificación del framework.
*/

Route::middleware('auth')->group(function () {
    Route::get('/correo/verificar', [Account\VerificacionController::class, 'notice'])
        ->name('verification.notice');
    Route::get('/correo/verificar/{id}/{hash}', [Account\VerificacionController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/correo/verificar/reenviar', [Account\VerificacionController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Mi cuenta (organizador)
|--------------------------------------------------------------------------
*/

Route::prefix('mi-cuenta')->name('account.')->group(function () {
    Route::get('/login', [Account\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [Account\AuthController::class, 'login'])->name('login.attempt');
    Route::post('/logout', [Account\AuthController::class, 'logout'])->name('logout');

    Route::get('/registro', [Account\RegistroController::class, 'create'])->name('registro');
    Route::post('/registro', [Account\RegistroController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('registro.store');

    Route::middleware(['auth', 'role:organizer'])->group(function () {
        Route::get('/', fn () => redirect()->route('account.activities.index'))->name('home');

        // Perfil: mismo controlador que el del admin, distinto layout.
        Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil');
        Route::put('/perfil', [PerfilController::class, 'update'])
            ->middleware('throttle:12,1')
            ->name('perfil.update');
        // El freno es por la contraseña actual: con una sesión ya abierta se
        // podía probar sin límite, que es justo lo que hace falta para
        // aprovechar una sesión robada.
        Route::post('/perfil/contrasena', [PerfilController::class, 'password'])
            ->middleware('throttle:6,1')
            ->name('perfil.password');
        Route::post('/perfil/sesiones/cerrar', [PerfilController::class, 'cerrarSesion'])->name('perfil.sesiones.cerrar');
        Route::post('/perfil/sesiones/otras', [PerfilController::class, 'cerrarOtras'])->name('perfil.sesiones.otras');

        Route::get('/actividades', [Account\MyActivityController::class, 'index'])->name('activities.index');
        Route::get('/actividades/{activity}/editar', [Account\MyActivityController::class, 'edit'])
            ->name('activities.edit');
        Route::put('/actividades/{activity}', [Account\MyActivityController::class, 'update'])
            ->name('activities.update');
        Route::get('/actividades/{activity}/guardado', [Account\MyActivityController::class, 'saved'])
            ->name('activities.saved');
        Route::post('/actividades/{activity}/duplicar', [Account\MyActivityController::class, 'duplicate'])
            ->name('activities.duplicate');
        Route::post('/actividades/{activity}/enviar', [Account\MyActivityController::class, 'submitForReview'])
            ->name('activities.submit');
        Route::post('/actividades/{activity}/cancelar', [Account\MyActivityController::class, 'cancel'])
            ->name('activities.cancel');

        Route::get('/actividades/{activity}/participantes', [Account\ParticipantController::class, 'index'])
            ->name('participants.index');
        Route::get('/actividades/{activity}/participantes/exportar', [Account\ParticipantController::class, 'export'])
            ->name('participants.export');
        Route::patch('/actividades/{activity}/cupos', [Account\ParticipantController::class, 'updateCupos'])
            ->name('participants.cupos');
    });
});

/*
|--------------------------------------------------------------------------
| Recuperación de contraseña
|--------------------------------------------------------------------------
| Los nombres son los estándar de Laravel (password.*) porque son los que
| arma el broker al construir el enlace del correo.
*/

Route::prefix('mi-cuenta')->group(function () {
    Route::get('/recuperar-contrasena', [Account\PasswordResetController::class, 'request'])
        ->name('password.request');
    Route::post('/recuperar-contrasena', [Account\PasswordResetController::class, 'email'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [Account\PasswordResetController::class, 'reset'])
        ->name('password.reset');
    Route::post('/restablecer-contrasena', [Account\PasswordResetController::class, 'update'])
        ->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Panel administrativo
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [Admin\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [Admin\AuthController::class, 'login'])->name('login.attempt');
    Route::post('/logout', [Admin\AuthController::class, 'logout'])->name('logout');

    // Entrada a la recuperación desde el panel: el formulario y el correo son
    // los mismos, sólo cambia el aspecto de la pantalla.
    Route::get('/recuperar-contrasena', [Account\PasswordResetController::class, 'request'])
        ->name('password.request');

    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil');
        Route::put('/perfil', [PerfilController::class, 'update'])
            ->middleware('throttle:12,1')
            ->name('perfil.update');
        // El freno es por la contraseña actual: con una sesión ya abierta se
        // podía probar sin límite, que es justo lo que hace falta para
        // aprovechar una sesión robada.
        Route::post('/perfil/contrasena', [PerfilController::class, 'password'])
            ->middleware('throttle:6,1')
            ->name('perfil.password');
        Route::post('/perfil/sesiones/cerrar', [PerfilController::class, 'cerrarSesion'])->name('perfil.sesiones.cerrar');
        Route::post('/perfil/sesiones/otras', [PerfilController::class, 'cerrarOtras'])->name('perfil.sesiones.otras');

        // Moderación. Cada estado es un nodo del menú, así que tiene su ruta.
        Route::get('/actividades', [Admin\ActivityController::class, 'index'])->name('activities.index');
        Route::get('/actividades/pendientes', fn (Request $r, Admin\ActivityController $c) => $c->index($r, 'revision'))
            ->name('activities.pendientes');
        Route::get('/actividades/publicadas', fn (Request $r, Admin\ActivityController $c) => $c->index($r, 'publicada'))
            ->name('activities.publicadas');
        Route::get('/actividades/canceladas', fn (Request $r, Admin\ActivityController $c) => $c->index($r, 'cancelada'))
            ->name('activities.canceladas');
        Route::get('/actividades/{activity}', [Admin\ActivityController::class, 'show'])->name('activities.show');
        Route::post('/actividades/{activity}/publicar', [Admin\ActivityController::class, 'approve'])->name('activities.approve');
        Route::post('/actividades/{activity}/ajustes', [Admin\ActivityController::class, 'requestChanges'])->name('activities.changes');
        Route::post('/actividades/{activity}/cancelar', [Admin\ActivityController::class, 'reject'])->name('activities.reject');
        Route::post('/actividades/{activity}/destacar', [Admin\ActivityController::class, 'toggleFeatured'])->name('activities.featured');

        Route::get('/organizaciones', [Admin\OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('/organizaciones/verificacion', [Admin\OrganizationController::class, 'verificacion'])
            ->name('organizations.verificacion');
        Route::post('/organizaciones/{organization}/verificar', [Admin\OrganizationController::class, 'toggleVerified'])
            ->name('organizations.verify');

        Route::get('/inscripciones', [Admin\RegistrationController::class, 'index'])->name('registrations.index');
        Route::get('/inscripciones/exportar', [Admin\RegistrationController::class, 'exportar'])->name('registrations.exportar');
        Route::get('/inscripciones/exportar/descargar', [Admin\RegistrationController::class, 'descargar'])->name('registrations.descargar');

        Route::get('/usuarios', [Admin\UserController::class, 'index'])->name('users.index');
        Route::post('/usuarios', [Admin\UserController::class, 'store'])->name('users.store');
        Route::get('/usuarios/{user}/editar', [Admin\UserController::class, 'edit'])->name('users.edit');
        Route::put('/usuarios/{user}', [Admin\UserController::class, 'update'])->name('users.update');
        // Asignar contraseña a otra persona. Con freno: es la acción con más
        // alcance del panel, y sin límite una sesión de admin robada podría
        // recorrer la lista de usuarios entera.
        Route::post('/usuarios/{user}/contrasena', [Admin\UserController::class, 'cambiarContrasena'])
            ->middleware('throttle:10,1')
            ->name('users.password');
        Route::post('/usuarios/{user}/estado', [Admin\UserController::class, 'toggleActive'])->name('users.toggle');

        // Buscador del panel
        Route::get('/buscar', Admin\BuscadorController::class)->name('buscar');

        /*
         * Editor de contenido del home (bloque F). El orden importa: la ruta
         * de la vista previa tiene que declararse ANTES que `/{seccion}`, o el
         * comodín se tragaría «vista-previa» como si fuera una sección.
         */
        Route::get('/paginas/home', [Admin\HomeSectionController::class, 'index'])->name('home.index');
        Route::post('/paginas/home/orden', [Admin\HomeSectionController::class, 'reordenar'])->name('home.orden');
        Route::get('/paginas/home/vista-previa', [Admin\HomeSectionController::class, 'vistaPrevia'])->name('home.vista-previa');

        Route::get('/paginas/home/{seccion}', [Admin\HomeSectionController::class, 'edit'])->name('home.editar');
        Route::put('/paginas/home/{seccion}', [Admin\HomeSectionController::class, 'update'])->name('home.actualizar');
        Route::post('/paginas/home/{seccion}/estado', [Admin\HomeSectionController::class, 'alternar'])->name('home.alternar');
        // El autoguardado dispara cada pocos segundos mientras se escribe, así
        // que lleva su propio freno, más ancho que el de un formulario normal.
        Route::post('/paginas/home/{seccion}/borrador', [Admin\HomeSectionController::class, 'borrador'])
            ->middleware('throttle:120,1')
            ->name('home.borrador');
        Route::delete('/paginas/home/{seccion}/borrador', [Admin\HomeSectionController::class, 'descartarBorrador'])->name('home.borrador.descartar');
        Route::post('/paginas/home/{seccion}/versiones/{version}/restaurar', [Admin\HomeSectionController::class, 'restaurar'])->name('home.restaurar');
        Route::get('/paginas/privacidad', [Admin\PaginaLegalController::class, 'privacidad'])->name('paginas.privacidad');

        // Regiones y comunas (sólo consulta)
        Route::get('/regiones', [Admin\RegionController::class, 'index'])->name('regiones.index');

        // Taxonomías
        Route::get('/taxonomias', [Admin\TaxonomyController::class, 'index'])->name('taxonomies.index');
        Route::post('/taxonomias', [Admin\TaxonomyController::class, 'store'])->name('taxonomies.store');
        Route::put('/taxonomias/{term}', [Admin\TaxonomyController::class, 'update'])->name('taxonomies.update');
        Route::delete('/taxonomias/{term}', [Admin\TaxonomyController::class, 'destroy'])->name('taxonomies.destroy');

        // Contenido editable (CRUD genérico)
        Route::get('/contenido/{tipo}', [Admin\ContentController::class, 'index'])->name('content.index');
        Route::get('/contenido/{tipo}/nuevo', [Admin\ContentController::class, 'create'])->name('content.create');
        Route::post('/contenido/{tipo}', [Admin\ContentController::class, 'store'])->name('content.store');
        Route::get('/contenido/{tipo}/{id}/editar', [Admin\ContentController::class, 'edit'])->name('content.edit');
        Route::put('/contenido/{tipo}/{id}', [Admin\ContentController::class, 'update'])->name('content.update');
        Route::delete('/contenido/{tipo}/{id}', [Admin\ContentController::class, 'destroy'])->name('content.destroy');

        // Configuración
        Route::get('/configuracion', [Admin\SettingController::class, 'edit'])->name('settings.general');
        Route::put('/configuracion', [Admin\SettingController::class, 'update'])->name('settings.general.update');

        Route::get('/configuracion/seo', [Admin\SeoController::class, 'edit'])->name('settings.seo');
        Route::put('/configuracion/seo', [Admin\SeoController::class, 'update'])->name('settings.seo.update');

        Route::get('/configuracion/smtp', [Admin\SmtpSettingController::class, 'edit'])->name('settings.smtp');
        Route::put('/configuracion/smtp', [Admin\SmtpSettingController::class, 'update'])->name('settings.smtp.update');
        Route::post('/configuracion/smtp/probar', [Admin\SmtpSettingController::class, 'sendTest'])->name('settings.smtp.test');

        // Plantillas de correo. Las claves las fija el código, así que no hay
        // un "crear" libre: lo que hace falta es restaurar las que falten.
        Route::get('/plantillas', [Admin\EmailTemplateController::class, 'index'])->name('templates.index');
        Route::post('/plantillas/restaurar', [Admin\EmailTemplateController::class, 'restaurar'])->name('templates.restore');
        Route::get('/plantillas/{template}', [Admin\EmailTemplateController::class, 'edit'])->name('templates.edit');
        Route::put('/plantillas/{template}', [Admin\EmailTemplateController::class, 'update'])->name('templates.update');
        Route::post('/plantillas/{template}/previa', [Admin\EmailTemplateController::class, 'preview'])->name('templates.preview');
        Route::post('/plantillas/{template}/prueba', [Admin\EmailTemplateController::class, 'test'])->name('templates.test');

        // Log de correos
        Route::get('/correos', [Admin\EmailLogController::class, 'index'])->name('emails.index');
        Route::get('/correos/{email}', [Admin\EmailLogController::class, 'show'])->name('emails.show');
        Route::post('/correos/{email}/reenviar', [Admin\EmailLogController::class, 'resend'])->name('emails.resend');

        // Log de accesos
        Route::get('/accesos', [Admin\AccessLogController::class, 'index'])->name('accesos.index');
        Route::post('/accesos/desbloquear', [Admin\AccessLogController::class, 'desbloquear'])->name('accesos.desbloquear');
    });
});

/*
|--------------------------------------------------------------------------
| Catch-all de páginas — SIEMPRE al final
|--------------------------------------------------------------------------
| Absorbe el HTML que se vaya sumando sin tocar este archivo.
*/

Route::get('/{page:slug}', [PageController::class, 'show'])->name('pages.show');
