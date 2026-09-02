<?php

namespace App\Support;

use Illuminate\Support\MessageBag;

/**
 * Los campos obligatorios del wizard de publicar, con su nombre corto y el paso
 * donde viven.
 *
 * Existe por una razón concreta: el resumen de errores tiene que decir «Público
 * beneficiado», no «publicos», y tiene que decirlo en los DOS caminos —cuando
 * el servidor rechaza el POST (esto se pinta en PHP) y cuando la revisión
 * previa lo corta antes de enviar (el navegador lo lee del `data-etiqueta` del
 * DOM)—. Con una sola tabla, los dos caminos dicen lo mismo por construcción.
 * Con dos, tarde o temprano dicen cosas distintas.
 *
 * **No es una segunda lista de reglas.** Aquí no se decide qué es obligatorio;
 * eso lo decide `PublishActivityRequest`, que sigue siendo el único que valida.
 * Esto es la traducción de un nombre de campo a algo que se pueda leer.
 *
 * El paso es el del wizard, para poder saltar al 3 estando en el 4.
 */
final class CamposDeActividad
{
    /**
     * Campo => [etiqueta que se le enseña a la persona, paso del wizard].
     *
     * El paso es `null` en los campos que sólo existen en el editor de
     * «Mi cuenta», que no tiene pasos. La guía lo tiene en cuenta: sin paso,
     * no intenta cambiar de pantalla antes de saltar al campo.
     */
    public const CAMPOS = [
        // Paso 3 — tu organización
        'org_nombre' => ['Nombre de la organización', 3],
        'org_tipo' => ['Tipo de organización', 2],
        'org_tipo_otro' => ['Descripción de tu organización', 3],
        'org_num_voluntarios' => ['Trabajadores que participan', 3],
        'org_unidad_educativa' => ['Unidad o comunidad educativa', 3],
        'org_logo' => ['Logo de la organización', 3],
        'email' => ['Correo electrónico', 3],
        'password' => ['Contraseña', 3],

        // Paso 4 — tu actividad
        'titulo' => ['Nombre de la actividad', 4],
        'caracteristicas' => ['Características de la actividad', 4],
        'formato' => ['Formato', 4],
        'descripcion' => ['Descripción de la actividad', 4],
        'fecha_inicio' => ['Fecha', 4],
        'hora_inicio' => ['Hora de inicio', 4],
        'hora_termino' => ['Hora de término', 4],
        'region_id' => ['Región', 4],
        'commune_id' => ['Comuna', 4],
        'direccion' => ['Dirección', 4],
        'temas' => ['Tema de la actividad', 4],
        'publicos' => ['Público beneficiado', 4],
        'publico_otro' => ['Cuál es el público beneficiado', 4],
        'participantes_estimados' => ['Cantidad de participantes', 4],
        'cupos_totales' => ['Cupos disponibles', 4],
        'accesibilidad_detalle' => ['Detalle de accesibilidad', 4],
        'correo_contacto' => ['Correo de contacto público', 4],
        'enlace_red_social' => ['Enlace a red social', 4],
        'enlace_web' => ['Enlace a página web', 4],
        'imagen' => ['Imagen de portada', 4],
        'colaboradores' => ['Organizaciones colaboradoras', 4],

        // Sólo en el editor de «Mi cuenta», que no tiene pasos.
        'fecha_termino' => ['Fecha de término', null],
        'cupos_disponibles' => ['Cupos disponibles', null],
        'info_previa' => ['Qué deben saber antes de asistir', null],
        'accesos' => ['Accesibilidad', null],
        'abierta_publico' => ['Actividad abierta al público', null],
    ];

    public static function etiqueta(string $campo): string
    {
        return self::CAMPOS[$campo][0] ?? $campo;
    }

    public static function paso(string $campo): ?int
    {
        return self::CAMPOS[$campo][1] ?? null;
    }

    /** El resumen del wizard, listo para el `x-data`. */
    public static function resumen(MessageBag $errores): array
    {
        return ResumenDeErrores::desde($errores, self::CAMPOS);
    }
}
