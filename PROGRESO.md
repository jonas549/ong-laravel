# Progreso — sexta tanda

**Fecha:** 22/09/2026
**Producción:** https://ong.sandboxdelta.com
**Estado:** B1–B8, C1–C6 y D1 hechos, verificados en local y **desplegados**
(`9435e79`, el 22/09). En producción se repasó lo que se puede sin sesión:
`sexta-home` 29/29 y `sexta-formulario` 20/20 contra el servidor, las seis
pantallas públicas a 1440 y 390 px sin errores de consola ni desborde, y el
logo del pie medido con WebKit (310×229, correcto). A1 sigue sin causa: en
local no se reproduce y hace falta el log de producción.

Lo de la quinta tanda y la importación de organizaciones (21/09) está en
`BITACORA-2026-09-20.md` y en los commits `1f2ecc0`, `0cb4d2b` y `0f240ee`.

---

## LO QUE SE HIZO

| # | Qué | Verificación (local) |
|---|---|---|
| A1 | El 500 de `/admin/paginas/home` **no se reproduce en local**. El circuito entero de duplicar funciona | `duplicar-circuito.mjs` (30), `duplicar-seccion.mjs` (21), y 35 secciones con copias de copias, orden al revés y copias escondidas |
| B1 | Con sesión y tipo en la ficha, el paso 2 no se pinta; la barra renumera | `sexta-wizard.mjs` (39) |
| B2 | El paso 3 casi vacío era una ficha sin tipo (reclamada desde el registro). El tipo se pregunta en el 2 y el 3 se salta | `sexta-wizard.mjs` |
| B3 | «Cerrar sesión y entrar con otra cuenta» junto a «Tu cuenta», sin salir ni perder lo escrito | `sexta-wizard.mjs` |
| B4 | La hora es un `<select>` con las 24 en punto en AM/PM, abre en las 9:00 AM | `hora-y-campos.mjs` (43), escritorio y 390 px |
| B5 | El logo es obligatorio en escritorio en los cinco tipos menos «Otra», en el wizard y en `/mi-cuenta/registro` | `sexta-correcciones.mjs` (45) |
| B6 | En el teléfono el logo no obliga a nadie; se sube después en «Mi perfil» | `sexta-wizard.mjs` (50) |
| B7 | Títulos de campo a 14,5 px en negrita; la ayuda, 12,5 px gris | `sexta-formulario.mjs` (20) |
| B8 | En el teléfono, botón «Elegir en el calendario» que es un campo de fecha nativo | `sexta-formulario.mjs` |
| C1 | «¿Cómo quieres participar hoy?» ya no se recorta en el teléfono | `sexta-home.mjs` (29), de 320 a 1440 px |
| C2 | El logo del pie salía achatado en Safari del iPhone | WebKit de Playwright: 310×288 → 310×229 |
| C3 | YouTube del pie, al canal de la Comunidad | `sexta-home.mjs` |
| C4 | El logo de la Comunidad enlaza a comunidad-org.cl en cabecera y pies | `sexta-home.mjs` |
| C5 | «Quiero ser voluntario» a voluntariadoschile.cl/oportunidades; una tarjeta sin enlace ya no se pinta como enlace y el botón del kit no va al ancla muerta | `sexta-home.mjs`, `sexta-correcciones.mjs`, `home-tarjetas-y-logos.mjs` (58) |
| — | Paso 1: «Redirigiendo en 5 segundos…» redirige de verdad, con destino configurable | `sexta-correcciones.mjs` |
| C6 | «Ver actividad» con relleno naranja; la imagen abre la ficha | `sexta-home.mjs` |
| D1 | Correo «Guía para organizadores» al registrar una actividad | `guia-organizador.mjs` (14), leído en Mailpit |

---

## LAS DECISIONES

**B1/B2 — decide el navegador, con la ficha entera.** Qué pasos se saltan
cambia sin recargar: al elegir tipo, al entrar a mitad (P14) y al cambiar de
cuenta (B3). El servidor manda los hechos (`Organization::fichaParaElWizard()`)
y pinta el estado de partida; el paso 3 lleva siempre todos sus campos y Alpine
enseña los que falten (`faltaEnElPaso3()` en `wizard.js`). Con el tipo en la
ficha **ya no se puede cambiar desde el wizard**: es lo que pide B1.

**B4 — la opción vacía va entre las 8:00 y las 9:00 AM.** Un `<select>` nativo
abre por la opción elegida; poner las 9:00 de oficio enviaría una hora que nadie
eligió. Así abre ahí sin dejar nada puesto, y sirve para quitar la hora. Las
horas que no son en punto (fichas antiguas) se conservan como una opción más.

**B6 — «móvil» es menos de 760 px de ancho**, el mismo corte del resto del sitio.

**B5 — lo decide el navegador y la regla del servidor se queda en `nullable`.**
El logo depende del tipo elegido y del ancho de la pantalla, y el servidor no
sabe desde dónde se envía: exigirlo allí rebotaría a quien publica desde el
teléfono pidiéndole un campo que no se le pidió, que es el peor error del
bloque K. En `/mi-cuenta/registro` tampoco se usa el `required` del navegador,
porque el `<input type=file>` va oculto y Chrome cortaría el envío sin decir
nada: corta el propio formulario, con su mensaje.

**Paso 1 — se replica lo que el aviso promete.** El prototipo decía
«Redirigiendo en 5 segundos…» y su botón sólo cerraba el aviso, porque allí no
había a dónde ir. Con backend real toca cumplirlo: cuenta atrás a la vista,
botón que no espera y «Volver» que la cancela. El destino vive en
Configuración → General; vacío, el aviso no promete ninguna redirección.

**C2 — era de WebKit, y el HTML fuente también lo tiene.** Una imagen hija
directa de un flex en columna con `max-width:100%`: Safari encoge el ancho y no
el alto. Chrome no lo hace, por eso no se veía desde aquí. Se envuelve en una
caja de bloque.

**C5 — el enlace ya se editaba** en Contenido → Tarjetas de «¿cómo
participar?». En producción ponía `voluntariadochile.cl`, sin la «s». La
migración `2025_02_02_000001` lo corrige sólo si la fila tiene uno de los
enlaces conocidos.

**D1 — correo aparte, no dentro del de «recibimos tu actividad».** Aquél es una
vista fija que la ONG no puede editar, y con aprobación automática no se envía.
Llega a producción por `dps:instalar` (crea la plantilla y el ajuste que falten).

---

## LO QUE HAY QUE HACER EN PRODUCCIÓN

1. ~~Subir~~ hecho: `9435e79`. La migración de C5 corrió (la tarjeta del home
   ya apunta a voluntariadoschile.cl) y el build servido es el de este commit.
   **Falta confirmar en el panel** que `dps:instalar` sembró el ajuste y la
   plantilla de D1: Configuración → General y Plantillas de correo.
2. **A1**: leer `~/ong-laravel/storage/logs/laravel.log` y buscar la entrada de
   `paginas/home`. En local no hay forma de reproducirlo.
3. Con una cuenta de organizador activa, repasar allí B1–B6, que necesitan
   sesión. `duplicar-circuito.mjs` y `guia-organizador.mjs` **escriben**: no se
   corren contra el servidor.

---

## LO QUE SIGUE PENDIENTE

1. ~~A1~~ cerrado por Jonas el 22/09: el log de producción no tenía ninguna
   entrada de `paginas/home` y el editor funciona. Era algo puntual del
   servidor o caché vieja.
2. **B2 con la cuenta real** (carolinamileon@gmail.com): la causa es la
   probable —una organización del listado sin tipo—, pero no se ha podido mirar
   su ficha sin acceso a producción.
3. **D2 y D3**: sólo respuesta, esperando las piezas de Canva.
4. Lo de antes: Q4 sin verificar en producción, los tres agujeros de
   cancelar/republicar, las tres plantillas de moderación fijas, `foto_path`,
   duplicar «Cifras»/«Voces», el ingreso con código, el filtro de C3.
5. El enlace de LinkedIn del pie sigue en `#`: no llegó dirección. Es el único
   `#` que queda en el sitio.
