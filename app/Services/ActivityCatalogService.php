<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Region;
use App\Models\TaxonomyTerm;

/**
 * Los catálogos que alimentan el formulario de actividad.
 *
 * Los piden dos vistas —el paso 4 del wizard y la pantalla de edición de
 * "Mi cuenta"—, y tienen que ofrecer exactamente las mismas opciones y los
 * mismos topes en ambas: si divergen, un formulario deja guardar algo que
 * el otro rechaza.
 */
class ActivityCatalogService
{
    /** @return array<string, mixed> */
    public function todos(): array
    {
        return [
            'formatos' => Activity::FORMATOS,
            // Solo las encendidas, y sus comunas encendidas: apagar una comuna
            // en el panel tiene que quitarla de este selector, o el interruptor
            // no hace nada visible y es peor que no tenerlo.
            'regiones' => Region::activas()->ordered()->with(['communes' => fn ($q) => $q->activas()])->get(),
            'temas' => $this->terminos('tema'),
            'caracteristicas' => $this->terminos('caracteristica'),
            'publicos' => $this->terminos('publico'),
            'accesos' => $this->terminos('acceso'),
            'limites' => TaxonomyTerm::LIMITES,
        ];
    }

    private function terminos(string $grupo)
    {
        return TaxonomyTerm::grupo($grupo)->activos()->ordered()->get();
    }
}
