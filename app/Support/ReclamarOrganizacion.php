<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Reclamar una organización del listado histórico, en el servidor.
 *
 * Es la mitad de servidor de lo que `resources/js/organizaciones.js` hace en
 * el navegador, y está aquí por el mismo motivo: **las dos pantallas donde
 * nace una organización** —el paso 3 del wizard y crear cuenta de
 * organizador— tienen que decidir lo mismo. Estaba escrito sólo en el
 * `PublishActivityRequest`, y la pantalla de registro se quedó sin nada
 * durante una tanda entera sin que nadie lo notara (C1).
 *
 * **Al añadir un sitio nuevo que cree organizaciones**, usa este trait. No
 * copies las reglas.
 *
 * Lo que resuelve son dos cosas que se pelean entre sí:
 *
 * 1. El punto 19 de la tanda del 11/09 prohíbe repetir el nombre de una
 *    organización. Sin lo de abajo, el buscador ofrece una que ya existe, la
 *    persona la elige, y el formulario la rechaza por repetida.
 * 2. Reclamar no es duplicar: es ponerle dueño a una fila que no lo tenía. Así
 *    que su propio nombre no puede ser motivo de rechazo.
 */
trait ReclamarOrganizacion
{
    /** `false` significa «todavía no se ha mirado»; `null`, «no hay». */
    private Organization|null|false $organizacionReclamada = false;

    /**
     * La organización del listado que se está reclamando, si es reclamable.
     *
     * **Se relee de la base entera.** El id viaja en un campo oculto, así que
     * decidir con lo que diga el navegador dejaría reclamar una organización
     * que ya tiene dueño sólo con cambiar el número: el resultado sería una
     * cuenta nueva colgada de una organización ajena, con sus actividades
     * dentro. Aquí se exige que siga libre y activa.
     *
     * Se memoriza porque la piden la regla del nombre, la del id y el
     * controlador, y son tres consultas para lo mismo dentro de una petición.
     */
    public function reclamada(): ?Organization
    {
        // Con sesión abierta no se reclama nada: la actividad va a la
        // organización que ya se tiene.
        if ($this->user()) {
            return null;
        }

        if ($this->organizacionReclamada !== false) {
            return $this->organizacionReclamada;
        }

        $id = (int) $this->input('org_id');

        return $this->organizacionReclamada = $id > 0
            ? Organization::sinReclamar()->where('activo', true)->find($id)
            : null;
    }

    /**
     * La regla del nombre: único, salvo el propio y salvo el que se reclama.
     *
     * Las borradas en blando no cuentan: su nombre vuelve a estar libre.
     */
    public function organizacionSinRepetir(): Unique
    {
        $regla = Rule::unique('organizations', 'nombre')->whereNull('deleted_at');

        /*
         * Se ignora la propia: quien ya tiene cuenta reusa su organización al
         * publicar una segunda actividad, y el formulario le devuelve su
         * propio nombre. Sin esto no podría volver a publicar nunca.
         */
        if ($propia = $this->user()?->organization) {
            $regla->ignore($propia->id);
        }

        if ($reclamada = $this->reclamada()) {
            $regla->ignore($reclamada->id);
        }

        return $regla;
    }

    /** La regla del id: existe, está activa y NO tiene cuenta. Las tres. */
    public function reglaDelIdDeOrganizacion(): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('organizations', 'id')
                ->whereNull('deleted_at')
                ->whereNull('user_id')
                ->where('activo', true),
        ];
    }

    /**
     * Pone dueño a la organización reclamada, o crea una nueva.
     *
     * De los campos del formulario sólo se aplican a la reclamada los que la
     * persona sí ha podido rellenar: el nombre, el tipo y el logo son los de
     * la ONG y no se pisan con lo que venga, que para eso se le han dejado de
     * pedir.
     *
     * @param  array<string, mixed>  $campos      para una organización nueva
     * @param  array<string, mixed>  $alReclamar  lo que sí se escribe si se reclama
     */
    public function reclamarOCrear(User $usuario, array $campos, array $alReclamar): Organization
    {
        if ($reclamada = $this->reclamada()) {
            $reclamada->fill($alReclamar + ['user_id' => $usuario->id])->save();

            return $reclamada;
        }

        return Organization::create($campos + ['user_id' => $usuario->id]);
    }
}
