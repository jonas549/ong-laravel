import { guiaDeErrores } from './formularios';

/*
 * El editor de una actividad en «Mi cuenta».
 *
 * Vivía en un <script> dentro de la vista, igual que el wizard. Se trajo aquí al
 * darle la misma guía de errores: es la pantalla que más van a usar los
 * organizadores, y era la única que se había quedado con el aviso viejo —«hay N
 * datos por corregir», sin decir cuáles—.
 *
 * A diferencia del wizard, aquí no hay pasos: la guía no define `irAlPaso`, y
 * las llamadas de dentro están escritas con `?.` justamente para eso.
 */
export const editorActividad = (inicial) => ({
    ...guiaDeErrores(inicial.errores ?? []),

    sel: {
        temas: inicial.temas.map(Number),
        caracteristicas: inicial.caracteristicas.map(Number),
        publicos: inicial.publicos.map(Number),
        accesos: inicial.accesos.map(Number),
    },
    limites: inicial.limites,

    formato: inicial.formato,
    sinFecha: inicial.sinFecha,
    abierta: inicial.abierta,
    insc: inicial.insc,
    descLen: inicial.descLen,

    colaboradores: inicial.colaboradores,
    colab: inicial.colaboradores.length > 0,

    modalCancelar: false,

    init() {
        // `$el` sólo es la raíz aquí dentro; ver la regla de panel.js.
        this.iniciarGuia(this.$el);
    },

    marcado(grupo, id) {
        return this.sel[grupo].includes(id);
    },

    // Igual que en el wizard: al pasarse del tope, sale el más antiguo.
    alternar(grupo, id) {
        const lista = this.sel[grupo];
        const i = lista.indexOf(id);

        if (i !== -1) {
            lista.splice(i, 1);
        } else {
            const tope = this.limites[grupo];

            if (tope && lista.length >= tope) {
                lista.shift();
            }

            lista.push(id);
        }

        // Los hidden que llevan la selección los pinta un x-for: hasta el
        // siguiente tick la caja sigue pareciendo vacía.
        this.$nextTick(() => this.revisarCampo(grupo));
    },

    /** Cuántos hay elegidos, para la marca que va junto al grupo. */
    cuantos(grupo) {
        return this.sel[grupo].length;
    },

    activarColab() {
        this.colab = true;

        if (this.colaboradores.length === 0) {
            this.colaboradores.push({ nombre: '', tipo: '' });
        }
    },
});
