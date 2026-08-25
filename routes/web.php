<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\RegistrationController;
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

Route::get('/publicar-actividad', [PublishController::class, 'create'])->name('publish.create');
Route::post('/publicar-actividad', [PublishController::class, 'store'])->name('publish.store');
Route::get('/publicar-actividad/{activity:slug}/listo', [PublishController::class, 'done'])->name('publish.done');

Route::get('/noticias', [PostController::class, 'index'])->name('posts.index');
Route::get('/noticias/{post:slug}', [PostController::class, 'show'])->name('posts.show');

/*
|--------------------------------------------------------------------------
| Mi cuenta (organizador)
|--------------------------------------------------------------------------
*/

Route::prefix('mi-cuenta')->name('account.')->group(function () {
    Route::get('/login', [Account\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [Account\AuthController::class, 'login'])->name('login.attempt');
    Route::post('/logout', [Account\AuthController::class, 'logout'])->name('logout');

    Route::middleware(['auth', 'role:organizer'])->group(function () {
        Route::get('/', fn () => redirect()->route('account.activities.index'))->name('home');

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

    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        // Moderación
        Route::get('/actividades', [Admin\ActivityController::class, 'index'])->name('activities.index');
        Route::get('/actividades/{activity}', [Admin\ActivityController::class, 'show'])->name('activities.show');
        Route::post('/actividades/{activity}/publicar', [Admin\ActivityController::class, 'approve'])->name('activities.approve');
        Route::post('/actividades/{activity}/ajustes', [Admin\ActivityController::class, 'requestChanges'])->name('activities.changes');
        Route::post('/actividades/{activity}/cancelar', [Admin\ActivityController::class, 'reject'])->name('activities.reject');
        Route::post('/actividades/{activity}/destacar', [Admin\ActivityController::class, 'toggleFeatured'])->name('activities.featured');

        Route::get('/organizaciones', [Admin\OrganizationController::class, 'index'])->name('organizations.index');
        Route::post('/organizaciones/{organization}/verificar', [Admin\OrganizationController::class, 'toggleVerified'])
            ->name('organizations.verify');

        Route::get('/inscripciones', [Admin\RegistrationController::class, 'index'])->name('registrations.index');

        Route::get('/usuarios', [Admin\UserController::class, 'index'])->name('users.index');
        Route::post('/usuarios', [Admin\UserController::class, 'store'])->name('users.store');
        Route::post('/usuarios/{user}/estado', [Admin\UserController::class, 'toggleActive'])->name('users.toggle');

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

        Route::get('/configuracion/smtp', [Admin\SmtpSettingController::class, 'edit'])->name('settings.smtp');
        Route::put('/configuracion/smtp', [Admin\SmtpSettingController::class, 'update'])->name('settings.smtp.update');
        Route::post('/configuracion/smtp/probar', [Admin\SmtpSettingController::class, 'sendTest'])->name('settings.smtp.test');

        // Log de correos
        Route::get('/correos', [Admin\EmailLogController::class, 'index'])->name('emails.index');
        Route::get('/correos/{email}', [Admin\EmailLogController::class, 'show'])->name('emails.show');
    });
});

/*
|--------------------------------------------------------------------------
| Catch-all de páginas — SIEMPRE al final
|--------------------------------------------------------------------------
| Absorbe el HTML que se vaya sumando sin tocar este archivo.
*/

Route::get('/{page:slug}', [PageController::class, 'show'])->name('pages.show');
