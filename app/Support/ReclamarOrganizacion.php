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
        /*
         * Con sesión abierta y organización propia no se reclama nada: la
         * actividad va a la que ya se tiene.
         *
         * Pero hay cuentas de organizador SIN organización enlazada —las crea
         * Panel → Usuarios, y también quedan así al cambiarle el rol a una de
         * administración o al eliminar su ficha—, y ésas tienen que poder
         * reclamar la suya del listado igual que quien no tiene cuenta. Antes
         * se les negaba por tener sesión, y el nombre de su propia
         * organización les rebotaba como repetido (punto 1 del 30/09).
         */
        if ($this->user()?->organization) {
            return null;
        }

        if ($this->organizacionReclamada !== false) {
            return $this->organizacionReclamada;
        }

        $id = (int) $this->input('org_id');

        /*
         * Libre, o ya con cuenta si el interruptor de varias cuentas está
         * encendido: en ese caso quien la elige se suma a ella. El
         * interruptor se mira aquí, en el servidor y en cada envío: con él
         * apagado, un `org_id` de una organización con cuenta no lleva a
         * ninguna parte, lo diga el navegador o no.
         */
        return $this->organizacionReclamada = $id > 0
            ? Organization::admitenCuentaNueva()->find($id)
            : null;
    }

    /**
     * Si la cuenta se va a sumar a una organización que ya tiene cuenta, en
     * vez de reclamar una libre. Al sumarse no se toca nada de la ficha:
     * es de la organización, y la cambia su cuenta principal.
     */
    public function seSuma(): bool
    {
        return ($org = $this->reclamada()) !== null && ! $org->estaSinReclamar();
    }

    /**
     * Si la ficha de la organización NO se va a escribir con este envío: quien
     * se suma, o quien tiene sesión en una organización de la que no es la
     * cuenta principal. A esos los campos de la organización no se les
     * preguntan, y exigirlos rebotaría un formulario que no los enseña.
     */
    public function fichaFija(): bool
    {
        $cuenta = $this->user();

        if ($cuenta?->organization) {
            return ! $cuenta->editaLaFicha();
        }

        return $this->seSuma();
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

    /**
     * La regla del id: existe, está activa y NO tiene cuenta. Las tres. Con
     * el interruptor de varias cuentas encendido, la tercera sobra: tener
     * cuenta ya no impide llegar a ella, sólo cambia reclamar por sumarse.
     */
    public function reglaDelIdDeOrganizacion(): array
    {
        $existe = Rule::exists('organizations', 'id')
            ->whereNull('deleted_at')
            ->where('activo', true);

        if (! Organization::admiteVariasCuentas()) {
            $existe->whereNull('user_id');
        }

        return ['nullable', 'integer', $existe];
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
    /**
     * El aviso de nombre repetido, según quién lo lea.
     *
     * A quien no tiene sesión se le manda a entrar con la cuenta dueña. A quien
     * ya está dentro, no: era justo lo que le decía a una organizadora con su
     * propia cuenta abierta (punto 1 del 30/09), y no tenía dónde ir.
     */
    public function avisoNombreRepetido(): string
    {
        if ($this->user()) {
            return 'Ya hay otra organización registrada con ese nombre y tiene su propia cuenta. '
                .'Si es la tuya, elígela en la lista de sugerencias; si es una organización distinta, '
                .'escribe un nombre que la diferencie.';
        }

        if (Organization::admiteVariasCuentas()) {
            return 'Ya hay una organización registrada con ese nombre. '
                .'Si es la tuya, elígela en la lista de sugerencias para sumarte a ella. '
                .'Si es otra organización distinta, escribe un nombre que la diferencie.';
        }

        return 'Ya hay una organización registrada con ese nombre. '
            .'Si es la tuya, inicia sesión con la cuenta que la creó y podrás sumar la actividad desde ahí. '
            .'Si es otra organización distinta, escribe un nombre que la diferencie.';
    }

    public function reclamarOCrear(User $usuario, array $campos, array $alReclamar): Organization
    {
        if ($reclamada = $this->reclamada()) {
            // Sumándose no se escribe nada en la ficha (ver `seSuma()`).
            if (! $this->seSuma()) {
                $reclamada->fill($alReclamar)->save();
            }

            $reclamada->enlazarCuenta($usuario);

            return $reclamada;
        }

        $organizacion = Organization::create($campos);
        $organizacion->enlazarCuenta($usuario);

        return $organizacion;
    }
}
