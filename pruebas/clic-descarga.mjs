// Pulsar «Descargar en Excel» como lo haría una persona, y saber qué URL
// siguió el navegador. Para las pantallas de Exportar (07/10): el enlace se
// arma en el clic con lo que tenga el formulario en ese momento, así que leer
// su `href` antes de pulsar no dice nada; hay que pulsar.
//
// La petición del clic se intercepta y no llega al servidor: no se guarda
// ningún archivo.
// El enlace queda «ocupado» después (no llega la cookie de descarga lista), así
// que para otro clic hay que volver a cargar la página.

export const urlDelClic = async (p, texto = 'Descargar en Excel') => {
    let url = null;
    const mirar = (r) => {
        if (! url && r.url().includes('/exportar/descargar')) {
            url = r.url();
            // Un 204 no navega: abortarla dejaría la página de error de
            // Chrome en la pestaña, y desde ahí ya no se puede pedir nada.
            r.respond({ status: 204, body: '' });
        } else {
            r.continue();
        }
    };

    await p.setRequestInterception(true);
    p.on('request', mirar);

    try {
        const hay = await p.evaluate((t) => {
            const a = [...document.querySelectorAll('a[data-descarga]')].find((x) => x.textContent.trim() === t);
            a?.click();
            return !! a;
        }, texto);

        for (let i = 0; hay && ! url && i < 50; i++) await new Promise((r) => setTimeout(r, 100));
    } finally {
        p.off('request', mirar);
        await p.setRequestInterception(false);
    }

    return url;
};

// ¿Avisa la pantalla de que el número de «Con estos filtros saldrían…» ya no
// corresponde a lo que hay en el formulario?
export const avisoCuentaVieja = (p) => p.evaluate(() => {
    const aviso = document.querySelector('[data-cuenta-vieja]');
    return !! aviso && ! aviso.hidden && aviso.offsetParent !== null;
});
