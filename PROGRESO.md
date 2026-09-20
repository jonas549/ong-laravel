# Progreso — cuarta tanda (cierre)

**Fecha:** 20/09/2026
**Producción:** https://ong.sandboxdelta.com
**Avance:** los seis puntos cerrados (C1–C6). Con esto queda cerrada también
la tercera tanda, cuyo único cabo suelto era P21.

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

Repaso de los cuatro primeros contra el servidor: `cierre-produccion.mjs`,
27 de 27.

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

Sin cambios respecto a la tercera tanda, menos P21, que se cierra aquí:

1. El Excel de organizaciones del cliente. `dps:importar-organizaciones` está
   listo y probado; falta el archivo.
2. Las tres plantillas de moderación —recibida, necesita ajustes, cancelada—
   siguen siendo vistas Blade fijas y no editables.
3. `activity_evaluations.foto_path` quedó sin uso; quitarlo es una migración
   destructiva sobre datos de producción y el único motivo sería la limpieza.
4. Duplicar «Cifras» o «Voces» copia los textos y no la lista: las dos copias
   enseñan los mismos elementos.
5. El ingreso con código al correo no se construyó; se hizo «recuperar
   contraseña», que era la otra mitad del encargo.

El detalle de la tercera tanda está en el historial de git: los commits de
P1–P20 llevan escrito el porqué de cada decisión.
