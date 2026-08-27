/*
 * Que un ancla caiga donde tiene que caer.
 *
 * El problema, medido: el sitio lleva `scroll-behavior: smooth`, así que pulsar
 * «Ediciones» desde arriba lanza un desplazamiento animado de más de cinco mil
 * píxeles. Durante ese viaje se cruzan treinta y cinco imágenes con carga
 * diferida; cada una que entra reajusta la página, y el anclaje de scroll de
 * Chrome empuja en sentido contrario para «conservar» lo que se está viendo.
 * El resultado es que el viaje se corta en un sitio distinto cada vez: en
 * producción se midieron tres intentos y tres posiciones, ninguna correcta.
 *
 * La solución no es quitar el desplazamiento suave —se pierde la referencia de
 * dónde estabas— sino **comprobar dónde se acabó parando y corregir**. Se deja
 * llegar, se espera a que la página se quede quieta, y si el destino no está
 * donde debería, se ajusta de una vez.
 *
 * Se corrige dos veces: al terminar el desplazamiento y otra vez cuando acaba
 * de cargar todo, porque una imagen que entra tarde vuelve a mover el destino.
 *
 * Y si mientras tanto la persona toca la rueda o el teclado, se cancela: nada
 * peor que un sitio que te devuelve a donde él quiere.
 */

/** Dónde debería quedar el scroll para que este elemento se vea entero. */
const posicionDe = (destino) => {
    // `scroll-margin-top` es lo que aparta el destino de la cabecera fija. Si
    // no se resta, la seccion queda debajo de la barra y parece que el salto
    // se quedo corto.
    const margen = parseFloat(getComputedStyle(destino).scrollMarginTop) || 0;

    return Math.max(0, Math.round(destino.getBoundingClientRect().top + window.scrollY - margen));
};

const asentar = (id) => {
    const destino = document.getElementById(id);

    if (!destino) return;

    let cancelado = false;

    const cancelar = () => { cancelado = true; };
    const eventos = ['wheel', 'touchstart', 'keydown'];
    eventos.forEach((e) => window.addEventListener(e, cancelar, { passive: true }));

    const soltar = () => eventos.forEach((e) => window.removeEventListener(e, cancelar));

    const corregir = () => {
        if (cancelado) return;

        const deseado = posicionDe(destino);

        // Cuatro pixeles de margen: por debajo de eso es redondeo, y saltar por
        // redondeo se ve como un tiron.
        if (Math.abs(deseado - window.scrollY) > 4) {
            window.scrollTo({ top: deseado, behavior: 'auto' });
        }
    };

    /*
     * Se espera a que la pagina deje de moverse. `scrollend` es lo correcto y
     * lo que usan los navegadores actuales; el temporizador es la red por si no
     * existe o por si el desplazamiento nunca llega a arrancar.
     */
    let reloj = null;

    const quieta = () => {
        clearTimeout(reloj);
        reloj = setTimeout(() => {
            window.removeEventListener('scroll', quieta);
            window.removeEventListener('scrollend', alTerminar);

            corregir();

            // Y otra vez cuando ya cargo todo: una imagen que entra tarde
            // vuelve a mover el destino.
            if (document.readyState === 'complete') {
                setTimeout(() => { corregir(); soltar(); }, 350);
            } else {
                window.addEventListener('load', () => setTimeout(() => { corregir(); soltar(); }, 350), { once: true });
            }
        }, 160);
    };

    const alTerminar = () => quieta();

    window.addEventListener('scroll', quieta, { passive: true });
    window.addEventListener('scrollend', alTerminar);

    // Arranca el reloj aunque no haya ni un evento de scroll: pasa cuando el
    // ancla ya esta a la vista y el navegador no mueve nada.
    quieta();
};

export const iniciarAnclas = () => {
    // Al pulsar un enlace interno del propio documento.
    document.addEventListener('click', (e) => {
        const enlace = e.target.closest('a[href^="#"], a[href*="#"]');

        if (!enlace) return;

        const url = new URL(enlace.href, location.href);

        if (url.pathname !== location.pathname || !url.hash || url.hash === '#') return;

        asentar(decodeURIComponent(url.hash.slice(1)));
    });

    // Al llegar con el ancla ya puesta en la URL.
    if (location.hash.length > 1) {
        asentar(decodeURIComponent(location.hash.slice(1)));
    }

    window.addEventListener('hashchange', () => {
        if (location.hash.length > 1) asentar(decodeURIComponent(location.hash.slice(1)));
    });
};
