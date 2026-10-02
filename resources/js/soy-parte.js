/*
 * «Soy parte del DPS» (punto 7 del 30/09): la pantalla que sigue a
 * inscribirse en una actividad.
 *
 * La imagen es fija —la misma para todos— y está en public/img. Para poder
 * compartirla como archivo hay que tenerla en memoria, así que se pide una vez
 * al abrir la pantalla. El compartir es el de la imagen de difusión: ver
 * compartir-imagen.js, que es donde se decide qué hace el botón en el
 * teléfono y en el escritorio.
 */
import { AVISOS, compartirImagen, descargar, modoDeCompartir } from './compartir-imagen';

export function soyParte(rutaImagen, nombreArchivo, texto) {
    return {
        archivo: null,
        modo: 'descargar',
        aviso: '',
        textoCopiado: false,

        async init() {
            try {
                const r = await fetch(rutaImagen);
                if (! r.ok) throw new Error(r.status);
                const blob = await r.blob();
                this.archivo = new File([blob], nombreArchivo, { type: blob.type || 'image/jpeg' });
                this.modo = modoDeCompartir(this.archivo);
            } catch {
                // Sin la imagen en memoria, «Descargar» sigue funcionando: es
                // un enlace normal al archivo.
                this.archivo = null;
            }
        },

        async compartir() {
            if (! this.archivo) return;
            // En el teléfono el texto viaja con la imagen; en el escritorio se
            // copia la imagen y el texto tiene su propio botón.
            this.aviso = AVISOS[await compartirImagen(this.archivo, { texto })];
        },

        descargar() {
            if (this.archivo) descargar(this.archivo);
        },

        async copiarTexto() {
            try {
                await navigator.clipboard.writeText(texto);
                this.textoCopiado = true;
                setTimeout(() => { this.textoCopiado = false; }, 2500);
            } catch {
                // Sin portapapeles el texto sigue a la vista para copiarlo a mano.
                this.textoCopiado = false;
            }
        },
    };
}
