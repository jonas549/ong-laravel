/*
 * Páginas → Marquesina de organizaciones.
 *
 * La lista manual se edita entera en el navegador —añadir, quitar, ordenar— y
 * viaja al guardar como `organizaciones[]` en su orden. Nada se escribe hasta
 * pulsar «Guardar»: es una lista que sale en la portada, y a medio ordenar no
 * tiene que verse.
 */
export const marquesinaAdmin = (elegidas, urlBuscar) => ({
    lista: elegidas.map((o) => ({ ...o })),
    q: '',
    resultados: [],
    buscando: false,
    buscado: false,
    cambiado: false,
    enviando: false,
    arrastrando: null,
    temporizador: null,

    init() {
        // Irse con la lista cambiada y sin guardar la perdería entera.
        window.addEventListener('beforeunload', (evento) => {
            if (this.cambiado && ! this.enviando) {
                evento.preventDefault();
                evento.returnValue = '';
            }
        });
    },

    enLista(id) {
        return this.lista.some((o) => o.id === id);
    },

    buscar() {
        clearTimeout(this.temporizador);

        if (this.q.trim().length < 2) {
            this.resultados = [];
            this.buscado = false;
            return;
        }

        this.temporizador = setTimeout(async () => {
            this.buscando = true;

            try {
                const r = await fetch(urlBuscar + '?q=' + encodeURIComponent(this.q.trim()), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                this.resultados = r.ok ? (await r.json()).organizaciones : [];
            } catch {
                this.resultados = [];
            } finally {
                this.buscando = false;
                this.buscado = true;
            }
        }, 250);
    },

    anadir(organizacion) {
        if (this.enLista(organizacion.id)) return;

        this.lista.push({ ...organizacion });
        this.cambiado = true;
    },

    quitar(indice) {
        this.lista.splice(indice, 1);
        this.cambiado = true;
    },

    mover(indice, destino) {
        if (destino < 0 || destino >= this.lista.length || destino === indice) return;

        const [organizacion] = this.lista.splice(indice, 1);
        this.lista.splice(destino, 0, organizacion);
        this.cambiado = true;
    },

    /*
     * Arrastrar. Se reordena el array —no el DOM, como en `ordenSecciones`—
     * porque aquí la lista la pinta un `x-for` con clave, que mueve los nodos
     * en vez de rehacerlos: el que se arrastra sigue existiendo y el
     * navegador no cancela el arrastre.
     */
    empezar(evento, id) {
        this.arrastrando = id;
        evento.dataTransfer.effectAllowed = 'move';
        // Firefox no arranca el arrastre si no se escribe algo aquí.
        evento.dataTransfer.setData('text/plain', String(id));
    },

    sobre(id) {
        if (this.arrastrando === null || id === this.arrastrando) return;

        const desde = this.lista.findIndex((o) => o.id === this.arrastrando);
        const hasta = this.lista.findIndex((o) => o.id === id);

        this.mover(desde, hasta);
    },

    terminar() {
        this.arrastrando = null;
    },
});
