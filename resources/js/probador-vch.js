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

    async enviar(ruta, cuerpo) {
        this.cargando = true;
        this.falloLocal = '';

        try {
            const r = await fetch(ruta, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify(cuerpo),
            });

            const datos = await r.json().catch(() => null);

            if (!r.ok) {
                // Un fallo de ESTA pantalla (sesión caducada, JSON pegado
                // inválido, demasiadas consultas), no de la API.
                this.falloLocal = datos?.error_json
                    ?? datos?.message
                    ?? `La pantalla respondió ${r.status}.`;

                return;
            }

            this.resultado = datos;
        } catch (e) {
            this.falloLocal = `No se pudo hablar con el servidor: ${e.message}`;
        } finally {
            this.cargando = false;
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
