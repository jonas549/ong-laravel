<?php

namespace App\Support\VoluntariadosChile;

/**
 * Lo que la documentación de Voluntariados Chile dice que devuelve el endpoint
 * («Documentación de API — Día del Patrimonio Social», secciones 5 a 7).
 *
 * Es la única copia del contrato en el código: la usa la pantalla de prueba
 * para decir qué falta, qué viene vacío y qué no estaba documentado, y la
 * usará la sincronización cuando exista. Si ellos cambian el contrato, se
 * cambia aquí y nada más.
 *
 * Cada campo lleva:
 *   - `tipo`: texto, iso (fecha-hora ISO 8601), fecha (YYYY-MM-DD), url,
 *     correo, numero, bool, objeto o lista;
 *   - `nulo`: si la documentación admite null («string o null»);
 *   - `vacio`: si admite lista vacía (sólo las listas);
 *   - `valores`: los valores posibles, si la documentación los fija;
 *   - `revision`: si ellos mismos lo marcan «en revisión».
 */
class Contrato
{
    /** El formato que ellos mismos dejaron «en revisión» (sección 5). */
    public const FORMATOS_DOCUMENTADOS = ['Presencial', 'Online', 'Híbrido'];

    public const FORMATOS_OBSERVADOS = ['En persona', 'Híbrido'];

    /** @return array<string, array<string, mixed>> */
    public static function oportunidad(): array
    {
        $requisito = ['tipo' => 'objeto'];
        $requerido = ['tipo' => 'bool'];
        $detalle = ['tipo' => 'texto', 'nulo' => true];

        return [
            'id' => ['tipo' => 'texto'],
            'status' => ['tipo' => 'texto', 'valores' => ['postulación_abierta']],
            'updated_at' => ['tipo' => 'iso'],
            'opportunity_url' => ['tipo' => 'url'],
            'organization' => ['tipo' => 'objeto'],
            'organization.name' => ['tipo' => 'texto'],
            'organization.logo_url' => ['tipo' => 'url', 'nulo' => true],
            'organization.type' => ['tipo' => 'texto', 'nulo' => true],
            'organization.impact_areas' => ['tipo' => 'lista', 'max' => 3],
            'organization.impact_areas[].code' => ['tipo' => 'texto'],
            'organization.impact_areas[].name' => ['tipo' => 'texto'],
            'title' => ['tipo' => 'texto'],
            'description' => ['tipo' => 'texto'],
            'responsibilities' => ['tipo' => 'texto'],
            'external_link' => ['tipo' => 'url', 'nulo' => true],
            'cover_image_url' => ['tipo' => 'url', 'nulo' => true],
            'format' => ['tipo' => 'texto', 'revision' => true],
            'volunteers_needed' => ['tipo' => 'numero', 'nulo' => true],
            'location' => ['tipo' => 'objeto'],
            'location.regions' => ['tipo' => 'lista'],
            'location.regions[]' => ['tipo' => 'texto'],
            // «puede venir vacío, por ejemplo en voluntariados híbridos u online»
            'location.comunas' => ['tipo' => 'lista', 'vacio' => true],
            'location.comunas[]' => ['tipo' => 'texto'],
            'schedule' => ['tipo' => 'objeto'],
            'schedule.type' => ['tipo' => 'texto', 'valores' => ['continuous', 'fixed_range']],
            'schedule.start_date' => ['tipo' => 'fecha', 'nulo' => true],
            'schedule.end_date' => ['tipo' => 'fecha', 'nulo' => true],
            'category' => ['tipo' => 'objeto'],
            'category.code' => ['tipo' => 'texto'],
            'category.name' => ['tipo' => 'texto'],
            'requirements' => ['tipo' => 'objeto'],
            'requirements.minimum_age' => $requisito,
            'requirements.minimum_age.required' => $requerido,
            'requirements.minimum_age.detail' => $detalle,
            'requirements.certificate' => $requisito,
            'requirements.certificate.required' => $requerido,
            'requirements.certificate.detail' => $detalle,
            'requirements.profession_or_study' => $requisito,
            'requirements.profession_or_study.required' => $requerido,
            'requirements.profession_or_study.detail' => $detalle,
            'requirements.prior_experience' => $requisito,
            'requirements.prior_experience.required' => $requerido,
            'requirements.prior_experience.detail' => $detalle,
            'contact' => ['tipo' => 'objeto'],
            'contact.name' => ['tipo' => 'texto'],
            'contact.email' => ['tipo' => 'correo'],
        ];
    }

    /** El sobre de una respuesta correcta (sección 6). */
    public static function sobre(): array
    {
        return [
            'generated_at' => ['tipo' => 'iso'],
            'pagination' => ['tipo' => 'objeto'],
            'pagination.page' => ['tipo' => 'numero'],
            'pagination.page_size' => ['tipo' => 'numero'],
            'pagination.total' => ['tipo' => 'numero'],
            'data' => ['tipo' => 'lista', 'vacio' => true],
        ];
    }

    /** Los códigos de error documentados (sección 7), por código HTTP. */
    public const ERRORES = [
        400 => 'invalid_parameter',
        401 => 'unauthorized',
        500 => 'internal_error',
    ];
}
