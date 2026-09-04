import { token } from './csrf';

/*
 * La biblioteca de medios: el subidor y el selector.
 *
 * Los dos comparten el mismo problema y la misma solución: son piezas que
 * viven DENTRO de otra pantalla —el selector, dentro de un formulario a medio
 * rellenar—, así que nada de lo que hacen puede recargar la página.
 */

/** Cabeceras comunes. `X-Requested-With` es lo que hace que Laravel conteste JSON. */
const cabeceras = () => ({
    'X-CSRF-TOKEN': token(),
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
});

/**
 * Saca el mensaje de un error, venga como venga.
 *
 * No es paranoia: cuando la respuesta es un 419 por sesión caducada, o el 413
 * del servidor por pasarse de `post_max_size`, lo que llega es HTML, no JSON.
 * Sin esto el `catch` decía «no se pudo» sin decir por qué, que es justo el
 * fallo que costó una tarde en el bloque G.
 */
const motivo = async (respuesta) => {
    if (respuesta.status === 413) {
        return 'El servidor rechazó el envío por tamaño. Sube menos archivos a la vez.';
    }

    if (respuesta.status === 419) {
        return 'Caducó la sesión. Recarga la página y vuelve a intentarlo.';
    }

    try {
        const datos = await respuesta.json();

        if (datos.errors) {
            return Object.values(datos.errors).flat().join(' ');
        }

        return datos.message || `El servidor respondió ${respuesta.status}.`;
    } catch {
        return `El servidor respondió ${respuesta.status}.`;
    }
};

const legible = (bytes) => {
    if (bytes < 1024) return `${bytes} B`;
    const kb = bytes / 1024;
    return kb < 1024 ? `${Math.round(kb)} KB` : `${(kb / 1024).toFixed(1)} MB`;
};

/* ── El subidor de la pantalla de la biblioteca ─────────── */

export const subidorMedios = ({ url, maxBytes, maxArchivos, maxTexto }) => ({
    abierto: false,
    encima: false,
    subiendo: false,
    carpeta: '',
    cola: [],
    maxArchivos,
    maxTexto,
    siguienteId: 1,

    get pendientes() {
        return this.cola.filter((f) => f.estado === 'espera');
    },

    get resumen() {
        const mal = this.cola.filter((f) => f.estado === 'mal').length;
        const ok = this.cola.filter((f) => f.estado === 'ok').length;

        if (!this.cola.length) return 'Ningún archivo elegido todavía.';
        if (mal) return `${ok} subidos, ${mal} con problemas.`;
        if (ok) return `${ok} subidos.`;

        return `${this.pendientes.length} listos para subir.`;
    },

    /**
     * Se comprueba el peso ANTES de enviar. El servidor también lo valida, pero
     * pasarse de `post_max_size` no da un error de validación: llega una
     * petición vacía, sin `$_FILES` y sin token, y el usuario ve un fallo de
     * sesión que no tiene que ver con lo que hizo.
     */
    añadir(lista) {
        for (const archivo of lista) {
            if (this.cola.length >= this.maxArchivos) {
                this.cola.push({
                    id: this.siguienteId++,
                    nombre: archivo.name,
                    detalle: `caben ${this.maxArchivos} por tanda`,
                    estado: 'mal',
                });
                break;
            }

            const pesado = archivo.size > maxBytes;

            this.cola.push({
                id: this.siguienteId++,
                archivo,
                nombre: archivo.name,
                detalle: pesado ? `${legible(archivo.size)} — pasa de ${maxTexto}` : legible(archivo.size),
                estado: pesado ? 'mal' : 'espera',
            });
        }
    },

    async subir() {
        const listos = this.pendientes;
        if (!listos.length) return;

        this.subiendo = true;
        listos.forEach((f) => (f.estado = 'subiendo'));

        const cuerpo = new FormData();
        listos.forEach((f) => cuerpo.append('archivos[]', f.archivo));
        if (this.carpeta) cuerpo.append('carpeta', this.carpeta);

        try {
            const respuesta = await fetch(url, { method: 'POST', headers: cabeceras(), body: cuerpo });

            if (!respuesta.ok) {
                const porQue = await motivo(respuesta);
                listos.forEach((f) => {
                    f.estado = 'mal';
                    f.detalle = porQue;
                });

                return;
            }

            listos.forEach((f) => (f.estado = 'ok'));

            // Recargar es correcto aquí: la cuadrícula de detrás tiene que
            // enseñar lo nuevo, y esta pantalla no tiene nada sin guardar.
            window.location.reload();
        } catch (e) {
            console.error('No se pudieron subir los archivos:', e);
            listos.forEach((f) => {
                f.estado = 'mal';
                f.detalle = 'No se pudo conectar con el servidor.';
            });
        } finally {
            this.subiendo = false;
        }
    },

    cerrar() {
        this.abierto = false;
        this.cola = [];
    },
});

/* ── El selector, que se abre desde un campo de imagen ──── */

/*
 * `valor` es la RUTA relativa —lo que viaja al servidor— y `vista` la URL
 * absoluta con la que se pinta la miniatura. Son dos cosas y hay que recibirlas
 * por separado: mientras se inicializaban las dos con la URL absoluta, abrir
 * cualquier formulario con imagen y guardarlo sin tocarla escribía la URL
 * entera en la base, porque el campo oculto va atado a `ruta`. Se veía igual
 * —`asset()` devuelve tal cual lo que ya es una URL— y dejaba guardado un
 * `http://localhost:8123/...` que se rompe al cambiar de dominio y que el
 * detector de «dónde se usa» de la biblioteca no reconoce.
 */
export const selectorMedio = ({ urlBuscar, urlSubir, valor, vista, maxBytes, maxTexto }) => ({
    abierto: false,
    cargando: false,
    subiendo: false,
    error: '',
    busqueda: '',
    origen: '',
    pagina: 1,
    paginas: 1,
    totalTexto: '',
    medios: [],
    ruta: valor || '',
    vistaPrevia: vista || '',
    temporizador: null,

    abrir() {
        this.abierto = true;
        this.error = '';

        if (!this.medios.length) this.cargar();
    },

    cerrar() {
        this.abierto = false;
    },

    /** Elegir cierra: es lo que se vino a hacer, y dejarlo abierto obliga a un clic de más. */
    elegir(medio) {
        this.ruta = medio.ruta;
        this.vistaPrevia = medio.url;
        this.abierto = false;
    },

    quitar() {
        this.ruta = '';
        this.vistaPrevia = '';
    },

    /** El buscador espera a que se deje de escribir, como el del resto del panel. */
    buscarConRetardo() {
        clearTimeout(this.temporizador);
        this.temporizador = setTimeout(() => {
            this.pagina = 1;
            this.cargar();
        }, 350);
    },

    async cargar() {
        this.cargando = true;
        this.error = '';

        const params = new URLSearchParams({ page: this.pagina });
        if (this.busqueda) params.set('q', this.busqueda);
        if (this.origen) params.set('origen', this.origen);

        try {
            const respuesta = await fetch(`${urlBuscar}?${params}`, { headers: cabeceras() });

            if (!respuesta.ok) {
                this.error = await motivo(respuesta);
                this.medios = [];

                return;
            }

            const datos = await respuesta.json();
            this.medios = datos.medios;
            this.pagina = datos.pagina;
            this.paginas = datos.paginas;
            this.totalTexto = datos.totalTexto;
        } catch (e) {
            console.error('No se pudo cargar la biblioteca:', e);
            this.error = 'No se pudo conectar con el servidor.';
            this.medios = [];
        } finally {
            this.cargando = false;
        }
    },

    irA(n) {
        if (n < 1 || n > this.paginas || n === this.pagina) return;
        this.pagina = n;
        this.cargar();
    },

    /**
     * Subir desde el propio selector, sin salir del formulario que hay detrás.
     * Lo recién subido se elige solo: es lo que se venía a hacer.
     */
    async subirAqui(entrada) {
        const archivo = entrada.files?.[0];
        if (!archivo) return;

        if (archivo.size > maxBytes) {
            this.error = `Ese archivo pesa ${legible(archivo.size)} y el tope es ${maxTexto}.`;
            entrada.value = '';

            return;
        }

        this.subiendo = true;
        this.error = '';

        const cuerpo = new FormData();
        cuerpo.append('archivos[]', archivo);

        try {
            const respuesta = await fetch(urlSubir, { method: 'POST', headers: cabeceras(), body: cuerpo });

            if (!respuesta.ok) {
                this.error = await motivo(respuesta);

                return;
            }

            const datos = await respuesta.json();
            const subido = datos.medios?.[0];

            if (subido) {
                this.elegir(subido);
            }
        } catch (e) {
            console.error('No se pudo subir el archivo:', e);
            this.error = 'No se pudo conectar con el servidor.';
        } finally {
            this.subiendo = false;
            entrada.value = '';
        }
    },
});
