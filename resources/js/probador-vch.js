/*
 * La pantalla de prueba de la API de Voluntariados Chile (panel).
 *
 * Todo pasa por `fetch` contra el propio servidor, que es quien llama a la
 * API: la clave viaja en el cuerpo de esa petición y no se guarda en ningún
 * sitio. Tampoco aquí: el campo no tiene `name` de formulario, así que el
 * navegador no lo ofrece para autocompletar ni lo guarda al recargar.
 */
export const probadorVch = ({ rutaConsultar, rutaRevisar }) => ({
    clave: '',
    updatedSince: '',
    page: '',
    pageSize: '',
    pegado: '',
    modo: 'api',

    cargando: false,
    segundos: 0,
    resultado: null,
    falloLocal: '',

    async consultar() {
        await this.enviar(rutaConsultar, {
            api_key: this.clave,
            updated_since: this.updatedSince,
            page: this.page,
            page_size: this.pageSize,
        });
    },

    async revisarPegado() {
        await this.enviar(rutaRevisar, { json: this.pegado });
    },

    /*
     * Siempre tiene que verse algo (04/10). En producción «Consultar» parecía
     * no hacer nada: mientras el servidor esperaba a la API, la única señal
     * era el texto del botón, y una respuesta que no fuera JSON se tragaba
     * sin decir nada. Ahora hay un aviso grande con los segundos que van
     * pasando, un corte propio a los 30 s por si el servidor tampoco
     * contesta, y cualquier respuesta rara se enseña tal cual.
     */
    async enviar(ruta, cuerpo) {
        this.cargando = true;
        this.falloLocal = '';
        this.resultado = null;
        this.segundos = 0;

        const reloj = setInterval(() => { this.segundos++; }, 1000);
        const corte = new AbortController();
        const limite = setTimeout(() => corte.abort(), 30000);

        try {
            const r = await fetch(ruta, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(cuerpo),
                signal: corte.signal,
            });

            const texto = await r.text();
            let datos = null;
            try { datos = JSON.parse(texto); } catch { /* se dice abajo */ }

            if (!r.ok) {
                // Un fallo de ESTA pantalla (sesión caducada, JSON pegado
                // inválido, demasiadas consultas), no de la API.
                this.falloLocal = datos?.error_json
                    ?? (datos?.message ? `${datos.message} (HTTP ${r.status})` : null)
                    ?? `Nuestro servidor respondió HTTP ${r.status}: ${texto.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300)}`;

                return;
            }

            if (!datos) {
                this.falloLocal = `Nuestro servidor respondió algo que no es JSON (HTTP ${r.status}): ${texto.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300)}`;

                return;
            }

            this.resultado = datos;
        } catch (e) {
            this.falloLocal = e.name === 'AbortError'
                ? 'Nuestro servidor no respondió en 30 segundos. La consulta se canceló.'
                : `No se pudo hablar con nuestro servidor: ${e.message}`;
        } finally {
            clearInterval(reloj);
            clearTimeout(limite);
            this.cargando = false;

            // Al resultado, para que se vea sin buscarlo.
            this.$nextTick(() => document.querySelector('[data-probador-veredicto], [data-probador-fallo-local]:not([style*="display: none"])')
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        }
    },

    get crudo() {
        if (!this.resultado) return '';
        if (this.resultado.json !== null && this.resultado.json !== undefined) {
            return JSON.stringify(this.resultado.json, null, 2);
        }

        return this.resultado.texto ?? '';
    },

    get errorApi() {
        return this.resultado?.json?.error ?? null;
    },

    get informe() {
        return this.resultado?.informe ?? null;
    },

    /** «falta 2 · vacío 1 · tipo 0…», sin los ceros. */
    conteo(c) {
        return [
            ['falta', c.falta],
            ['vacío', c.vacio],
            ['tipo distinto', c.tipo],
            ['valor no documentado', c.valor],
            ['vacío (permitido)', c.vacio_permitido],
        ].filter(([, n]) => n > 0).map(([t, n]) => `${t}: ${n}`).join(' · ');
    },

    esProblema(c) {
        return c.falta + c.vacio + c.tipo + c.valor > 0;
    },

    tonoHttp() {
        const h = this.resultado?.http;
        if (!h) return 'var(--rosa)';
        if (h >= 200 && h < 300) return 'var(--turquesa)';

        return h >= 500 ? 'var(--rosa)' : '#b7791f';
    },

    async copiar() {
        try {
            await navigator.clipboard.writeText(this.crudo);
        } catch { /* sin portapapeles: queda seleccionar a mano */ }
    },

    rellenarEjemplo() {
        this.pegado = document.getElementById('ejemplo-pdf')?.textContent.trim() ?? '';
    },
});
