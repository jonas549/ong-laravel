<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;

/**
 * Decide si una actividad sale publicada directamente o pasa por revisión.
 *
 * La regla que pidió el cliente: **la primera actividad de una organización se
 * revisa a mano; de la segunda en adelante, se publica sola.** La idea es que
 * revisar sirve para conocer a quien publica, y una vez conocido el trámite
 * sobra.
 *
 * Las cuatro decisiones de detalle están acordadas con quien encarga el
 * proyecto, y cada una tiene su porqué:
 *
 * 1. **«Ya publicó antes» se mide por `published_at`, no por el estado.** Una
 *    actividad cancelada estuvo publicada, y lo que da confianza es que la ONG
 *    aprobó ese contenido, no en qué estado está hoy. `estado = 'publicada'`
 *    pierde ese dato en cuanto se cancela.
 * 2. **Un «necesita ajustes» abierto lo pausa, pero no reinicia la cuenta.**
 *    Que haya una corrección sin resolver es la única señal viva de que a esa
 *    organización hay que mirarla; una corrección de hace un año, no. Se pausa
 *    mientras dure y se reanuda sola al resolverse.
 * 3. **Una actividad reenviada tras ajustes NUNCA se auto-aprueba.** Eso se
 *    decide en quien llama, no aquí: si la ONG pidió cambios, quiere verlos.
 * 4. **Dos interruptores.** Uno global en Configuración, que es el botón de
 *    pánico si llega spam, y uno por organización, que es el que sirve de
 *    verdad: apaga a quien haya que apagar sin castigar a las demás.
 *
 * **Desde el bloque de varias cuentas, lo que se cuenta es por CUENTA, no por
 * organización.** Con una sola cuenta por organización era lo mismo. Con
 * varias, contar por organización dejaba publicar sin revisión a cualquiera
 * que se sumara a una organización con historial: justo la puerta para que
 * alguien se presente como «Fundación X» y publique en su nombre. Ahora cada
 * cuenta se gana su confianza, y la primera actividad de una cuenta sumada —
 * la que no es la principal— se revisa **siempre**, aunque el umbral sea 0.
 * La marca de «revisión siempre» y la pausa por ajustes pendientes siguen
 * siendo de la organización entera.
 *
 * Al migrar, cada cuenta quedó como autora de todo lo de su organización, así
 * que con los datos de entonces el resultado no cambió para nadie.
 */
class AprobacionAutomatica
{
    /**
     * El interruptor general de Configuración → General.
     *
     * Apagado equivale a «exigir revisión de TODAS», que es una de las
     * opciones que pidió el cliente el 11/09. Se deja como interruptor y no
     * como un valor más del número porque es el botón de pánico: si llega
     * spam, lo que se busca es un sitio donde decir «todo a revisión», no
     * pensar qué número significa eso.
     */
    public function activa(): bool
    {
        return (bool) Setting::get('aprobacion_automatica', true);
    }

    /**
     * A partir de cuántas actividades ya publicadas se publica sin revisar.
     *
     * Punto 26 de la tanda del 11/09: antes era un sí/no que sólo sabía
     * expresar «revisa la primera». Ahora es un número, y el sentido es
     * literal —cuántas publicadas hay que llevar para que la siguiente salga
     * sola—:
     *
     *   0 → todas automáticas, ninguna revisión
     *   1 → lo de antes: se revisa la primera, de la segunda en adelante sola
     *   2 → se revisan las dos primeras
     *   N → se revisan las N primeras
     *
     * El valor por defecto es 1 a propósito: es lo que ya estaba corriendo en
     * producción desde el 2026-09-02, y un cambio de comportamiento silencioso
     * al desplegar sería peor que el problema.
     */
    public function umbral(): int
    {
        return max(0, (int) Setting::get('aprobacion_automatica_desde', 1));
    }

    /**
     * ¿Quien envía esto se ha ganado publicar sin pasar por revisión?
     *
     * Con una actividad se mira la cuenta que la envía (`responsable()`); con
     * una organización a secas, su cuenta principal. Lo segundo es lo que
     * había antes de las varias cuentas y lo siguen usando las pruebas.
     */
    public function aplica(Activity|Organization|null $que): bool
    {
        return $this->motivoDeRevision($que) === null;
    }

    /** @return array{0: ?Organization, 1: ?User} */
    private function deQuien(Activity|Organization|null $que): array
    {
        if ($que instanceof Activity) {
            return [$que->organization, $que->responsable()];
        }

        return [$que, $que?->user];
    }

    /**
     * Por qué esta actividad SÍ tiene que revisarse, o null si no hace falta.
     *
     * Devuelve el motivo y no un booleano a secas para que quede escrito en el
     * historial: dentro de seis meses, «por qué esta pasó por revisión y
     * aquélla no» es una pregunta que alguien va a hacer.
     */
    public function motivoDeRevision(Activity|Organization|null $que): ?string
    {
        if (! $this->activa()) {
            return 'la aprobación automática está desactivada';
        }

        [$organizacion, $cuenta] = $this->deQuien($que);

        if (! $organizacion) {
            return 'la actividad no tiene organización';
        }

        if ($organizacion->requiere_revision) {
            return 'esta organización está marcada para revisión siempre';
        }

        if (! $cuenta) {
            return 'la actividad no tiene una cuenta responsable';
        }

        $umbral = $this->umbral();
        $publicadas = $this->cuantasPublico($cuenta);

        // La primera de una cuenta sumada, siempre a revisión (ver arriba).
        if ($publicadas === 0 && ! $cuenta->esPrincipal()) {
            return 'es la primera actividad de una cuenta que se sumó a esta organización';
        }

        if ($publicadas < $umbral) {
            return $umbral === 1
                ? 'es la primera actividad de esta cuenta'
                : 'la cuenta lleva '.$publicadas.' de las '.$umbral.' actividades publicadas que se piden antes de publicar sin revisión';
        }

        if ($this->tieneAjustesPendientes($organizacion)) {
            return 'la organización tiene una actividad esperando correcciones';
        }

        return null;
    }

    /**
     * El estado en el que debe quedar una actividad que se envía.
     *
     * @return array{0: string, 1: string}  el estado y el comentario del historial
     */
    public function estadoAlEnviar(Activity|Organization|null $que): array
    {
        $motivo = $this->motivoDeRevision($que);

        return $motivo === null
            ? ['publicada', 'Publicada automáticamente: la cuenta lleva '.$this->cuantasPublico($this->deQuien($que)[1]).' actividad(es) publicada(s), y el umbral es '.$this->umbral().'.']
            : ['revision', 'A revisión: '.$motivo.'.'];
    }

    /**
     * ¿Tiene alguna actividad que llegara a publicarse?
     *
     * `published_at` y no el estado, por lo dicho arriba: una cancelada cuenta,
     * porque en su momento la ONG la aprobó. Un borrador cancelado sin publicar
     * nunca tuvo `published_at`, así que no cuenta, que es lo correcto.
     */
    public function yaPublico(Organization|User $quien): bool
    {
        return $this->cuantasPublico($quien instanceof Organization ? $quien->user : $quien) > 0;
    }

    /**
     * Cuántas actividades de esta organización llegaron a publicarse.
     *
     * Se cuenta por `published_at` y no por el estado, por lo dicho arriba: una
     * cancelada cuenta, porque en su momento la ONG aprobó ese contenido. Un
     * borrador que nunca se publicó no tiene `published_at` y no cuenta, que es
     * lo correcto. `withTrashed` para que borrar una actividad publicada no le
     * quite a la cuenta la confianza que ya se había ganado.
     *
     * Las de la cuenta son las de `Activity::deLaCuenta()`: las suyas y, si
     * es la principal, las que quedaron sin autor en su organización. Con una
     * organización se cuenta la de su principal, como antes.
     */
    public function cuantasPublico(Organization|User|null $quien): int
    {
        $cuenta = $quien instanceof Organization ? $quien->user : $quien;

        if (! $cuenta) {
            return 0;
        }

        return Activity::withTrashed()
            ->deLaCuenta($cuenta)
            ->whereNotNull('published_at')
            ->count();
    }

    /** ¿Hay alguna corrección sin resolver? */
    public function tieneAjustesPendientes(Organization $organizacion): bool
    {
        return Activity::where('organization_id', $organizacion->id)
            ->where('estado', 'ajustes')
            ->exists();
    }
}
