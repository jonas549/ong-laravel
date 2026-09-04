/**
 * Los botones de compartir de la ficha de actividad.
 *
 * WhatsApp y Facebook no necesitan JavaScript: son enlaces normales con la URL
 * ya montada en el Blade, así que funcionan aunque esto no cargue. Aquí vive
 * sólo «copiar enlace», que sí lo necesita.
 *
 * Copiar es además la salida para Instagram: no admite compartir por URL desde
 * la web —no existe un `instagram.com/share?url=`— y lo único que se puede
 * ofrecer es dejar el enlace en el portapapeles para pegarlo en una historia.
 */
export function compartir(enlace) {
    return {
        enlace,

        /** '' mientras no se ha pulsado, 'ok' o 'error' después. */
        estado: '',
        temporizador: null,

        async copiar() {
            this.estado = (await this.alPortapapeles()) ? 'ok' : 'error';

            // El aviso vuelve a su sitio solo. Si falló se deja más rato: ahí
            // hay algo que leer y hacer, y no sólo una confirmación.
            clearTimeout(this.temporizador);
            this.temporizador = setTimeout(
                () => { this.estado = ''; },
                this.estado === 'ok' ? 2500 : 6000,
            );
        },

        async alPortapapeles() {
            /*
             * `navigator.clipboard` sólo existe en contexto seguro: https o
             * localhost. El sitio va por https, pero abrirlo por IP dentro de
             * una red local no lo es, y ahí el botón se quedaría mudo sin que
             * nadie sepa por qué. De ahí la reserva.
             */
            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(this.enlace);
                    return true;
                }
            } catch {
                // Sin permiso o sin foco: se prueba con la reserva.
            }

            try {
                const caja = document.createElement('textarea');
                caja.value = this.enlace;
                caja.setAttribute('readonly', '');
                // Fuera de la pantalla, pero dentro del documento: un elemento
                // con `display:none` no se puede seleccionar.
                caja.style.position = 'fixed';
                caja.style.top = '-2000px';
                document.body.appendChild(caja);
                caja.select();
                const copiado = document.execCommand('copy');
                caja.remove();

                return copiado;
            } catch {
                return false;
            }
        },
    };
}
