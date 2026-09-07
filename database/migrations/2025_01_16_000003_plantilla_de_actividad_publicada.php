<?php

use App\Models\EmailTemplate;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Crea la plantilla editable «Aviso de actividad publicada».
 *
 * Ese correo existía desde el bloque A, pero como vista fija
 * (`App\Mail\ActivityPublished`): la ONG no podía tocarlo. Ahora es una
 * plantilla del panel como las otras cinco, porque es el correo que lleva el
 * código QR de la encuesta y su texto tiene que poder reescribirse sin
 * desplegar.
 *
 * **Va en una migración y no sólo en el seeder por la lección de siempre: el
 * cron del servidor corre `migrate` y nunca siembra.** Y aquí no es cosmético
 * —como con un ajuste que no se ve— sino funcional: sin la fila, el aviso de
 * publicación se caería al mailable de respaldo y saldría sin QR, que es justo
 * lo que este bloque viene a añadir.
 *
 * Con guarda, como todas: `EmailTemplateSeeder` usa `firstOrCreate`, así que
 * volver a correrla no pisa el texto que la ONG haya escrito.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new EmailTemplateSeeder)->run();
    }

    public function down(): void
    {
        EmailTemplate::where('clave', 'actividad_publicada')->delete();
    }
};
