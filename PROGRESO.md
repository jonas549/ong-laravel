# Progreso — cuarta y quinta tanda

**Fecha:** 20/09/2026
**Producción:** https://ong.sandboxdelta.com
**Avance:** la cuarta cerrada entera (C1–C6) y la quinta con Q1, Q2 y Q3
cerrados; Q4 está hecho pero falta comprobarlo en el servidor. Con la cuarta
queda cerrada también la tercera, cuyo único cabo suelto era P21.

El día entero, con el porqué de cada decisión, está en
`BITACORA-2026-09-20.md`.

---

## LO QUE SE HIZO

Verificado en Chrome de verdad. «(prod)» = comprobado además contra el
servidor, después de desplegar.

| # | Qué | Verificación |
|---|---|---|
| C1 | El buscador de organizaciones, también al crear cuenta de organizador; y un solo sitio del que sale para las dos pantallas | `registro-organizacion.mjs` (34), `organizaciones-wizard.mjs` (27) · **(prod)** |
| C2 | El desplegable de direcciones ya no se reabre al elegir una sugerencia | contando peticiones a Photon: cero de más |
| C3 | Fuera la columna «Estado» de inscripciones —tabla, filtro, exportar y dashboard—, con las bajas marcadas en su fila | `ids-panel.mjs` (53), `ids-exportaciones.mjs` (7) · **(prod)** |
| C4 | El paso 3 se salta si la ficha está completa, y si no, pide sólo lo que falta; la barra renumera sin dejar hueco | `paso3-salto.mjs` (28) · **(prod)** |
| C5 | Cómo se verificó P12, la marquesina | `marquesina.mjs` (8) · **(prod)** 5 organizaciones, ninguna repetida |
| C6 | Las credenciales de producción, fuera del repositorio | `c6-credenciales.mjs`: 12 en el ensayo local, 12 en producción |
| Q1 | Qué pasa al cancelar una actividad, y qué haría falta para republicarla | Respuesta, sin tocar nada. Ver la bitácora |
| Q2 | El logo de la organización se lee en la ficha: alto fijo y ancho según su forma | `ficha-actividad.mjs` (45) · **(prod)** 11 de 12 fichas |
| Q3 | Un enlace sin `https://` no rebota, en los cinco sitios donde se escribe uno | `quinta-tanda.mjs` (33) · **(prod)** |
| Q4 | El kit de difusión, en la barra de las seis pantallas de mi-cuenta | `quinta-tanda.mjs` (33) · **falta en prod**: no hay cuenta de organizador |

Repaso contra el servidor: `cierre-produccion.mjs` 27 de 27 y
`quinta-produccion.mjs` para Q2 y Q3.

---

## LAS DECISIONES QUE HUBO QUE TOMAR

**C1 — los sitios donde nace una organización eran dos, no uno.** El paso 3
del wizard y `/mi-cuenta/registro`. Aquél tenía buscador y éste un campo de
texto suelto, así que quien llegaba por ahí creaba un duplicado de su propia
organización y se quedaba sin su historial. No se ha copiado el buscador: se
ha sacado a `resources/js/organizaciones.js`, a `<x-buscador-organizacion>` y
al trait `App\Support\ReclamarOrganizacion`, y lo usan los dos. El panel de
administración no crea organizaciones: edita las que hay.

Una consecuencia buscada: registrarse ya no puede duplicar un nombre de
organización. Era la única puerta que se saltaba la regla de P19 del 11/09.

**C2 — no era el `$el` de Alpine.** Elegir una sugerencia escribe la dirección
en el campo y dispara un `input` para que Alpine se entere; ese `input` volvía
a entrar en el buscador, que pedía a Photon la etiqueta entera y abría el
desplegable con lo que devolviera. Sólo se veía cuando esa segunda consulta
traía resultados, de ahí que pareciera aleatorio. El mismo fallo estaba
copiado en el editor de `/mi-cuenta`.

**C3 — el filtro no se quita, se cambia.** Ofrecía los tres estados del
esquema y dos no separaban nada: «pendiente» devolvía todo y «confirmado»,
nada, porque el doble opt-in nunca se construyó. Se queda uno que sí separa:
todas / sin las canceladas / sólo las canceladas. La columna sí desaparece, y
la baja se marca en la propia fila.

**C4 — el tipo de organización se puede cambiar después de decidir el salto.**
Se elige en el paso 2, y de él dependen dos campos obligatorios del 3:
«Otra» pide describirse e «Institución educativa» pide la unidad. Con el salto
decidido sólo en el servidor, cambiar el tipo dejaba el 3 saltado pidiendo un
dato que la ficha no tiene: un campo obligatorio fuera de pantalla, que es el
peor error de todos. El salto se recalcula en el navegador y el paso 3 vuelve,
barra incluida.

El paso 3 se esconde de la barra desde `estiloPaso` y no con `x-show`: esa
función devuelve el style entero, y Alpine con un `:style` de texto reemplaza
el atributo. Las dos reglas escriben en `display` y se peleaban.

**C6 — se desactivan, no se borran.** Borrar `organizador@ong-laravel.test` se
llevaría por delante su organización y sus actividades. Quedan en el panel,
inactivas, y con contraseña nueva por si alguien las reactivara.

**Q2 — el logo no faltaba: no se leía.** Cuatro de las cinco organizaciones de
producción tienen logo, el `<img>` se pintaba y la imagen cargaba. Son logos
de palabras —uno de 668×100 px— y en un cuadrado de 54 salían a 54×8. Ahora
el alto manda y el ancho sale de la proporción, hasta 170 px.

**Q3 — lo decide el servidor, no el navegador.** El completado del `https://`
existía desde el punto 31, pero sólo en dos campos y sólo al salir del campo:
enviando con Enter el `blur` no siempre llega. `App\Support\Enlace` completa
antes de validar y lo usan los cinco sitios. De paso, `url` a secas aceptaba
`javascript:`, que acababa en un `href` público: la regla es `url:http,https`.

**Q4 — la barra se saca a un componente.** Estaba escrita dentro de «Mis
actividades» y sólo se veía allí. Usa el ajuste `kit_difusion_url` que ya
existía (punto 25) y no uno nuevo; vacío no pinta el botón.

---

## LO QUE HAY QUE SABER PARA SEGUIR

**Las credenciales de producción ya no están en el repositorio.** Salen de
`pruebas/credenciales.mjs`, cuyos valores por defecto sólo valen en una base
local recién sembrada. Para correr contra el servidor van por el entorno:

    DPS_URL=https://ong.sandboxdelta.com DPS_ADMIN=... DPS_CLAVE_ADMIN=... \
      node pruebas/ids-panel.mjs

**Y ya no hay cuenta de organizador de pruebas en producción.** Las
comprobaciones que necesitan esa sesión —`hilo-moderacion-lectura.mjs`,
`evaluaciones-fotos-lectura.mjs`, la parte de C4 de `cierre-produccion.mjs`—
no se pueden correr allí hasta que haya otra.

**Las suites que escriben no se corren contra el servidor.** Devolver una
actividad a revisión avisa por correo a todos los administradores activos, y
allí dos son personas de verdad. Para eso están las gemelas `*-lectura.mjs`,
`tanda-produccion.mjs` y `cierre-produccion.mjs`.

**El `UserSeeder` ya no pisa contraseñas.** `cuenta()` sólo la escribe al
crear, así que un `db:seed --force` en el servidor refresca nombre y rol pero
no devuelve la clave a la del repositorio. Era lo grave de P21.

---

## LO QUE SIGUE PENDIENTE

1. **Q4 sin comprobar en el servidor.** La barra vive detrás de
   `role:organizer` y desde C6 no queda ninguna cuenta con ese rol activa en
   producción. Hace falta reactivar la sembrada un rato o crear una de
   pruebas; lo decide Jonas.
2. **Los tres agujeros de cancelar y republicar** que sacó Q1:
   `inscripcion_habilitada` no se vuelve a encender, se reenvía el correo de
   publicación con QR, y a los inscritos nadie les avisa de la vuelta. En
   torno a un día, más la decisión de si republicar reabre inscripciones.
3. El Excel de organizaciones del cliente. `dps:importar-organizaciones` está
   listo y probado; falta el archivo.
4. Las tres plantillas de moderación —recibida, necesita ajustes, cancelada—
   siguen siendo vistas Blade fijas y no editables.
5. `activity_evaluations.foto_path` quedó sin uso; quitarlo es una migración
   destructiva sobre datos de producción y el único motivo sería la limpieza.
6. Duplicar «Cifras» o «Voces» copia los textos y no la lista: las dos copias
   enseñan los mismos elementos.
7. El ingreso con código al correo no se construyó; se hizo «recuperar
   contraseña», que era la otra mitad del encargo.
8. **El filtro de inscripciones de C3**, por si se quería fuera del todo y no
   sustituido por el de las bajas.

El detalle de la tercera tanda está en el historial de git: los commits de
P1–P20 llevan escrito el porqué de cada decisión.
