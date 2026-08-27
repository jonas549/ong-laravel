# Bitácora — 2026-08-27 · Bloque E

Segunda mitad de la jornada. El bloque C quedó confirmado por Jonas en
producción (los tres casos: id ajeno en la URL, trabajo propio intacto, y que
guardar con el título vacío siga dando error de validación y no un 403), y se
pasó al bloque E: la portada del panel.

Requisito innegociable de Jonas: **todos los números salen de la base de datos
real. Nada hardcodeado, nada de datos de ejemplo.**

---

## 1. Qué había y qué hay

La portada existía desde el día 24, con cinco cifras y una tabla de «movimiento
reciente». Los números ya eran reales, pero eran los que cabían en un
controlador: el total de actividades, el total de organizaciones y poco más.

Ahora:

| Punto del bloque | Qué se hizo |
|---|---|
| KPIs | Cinco tarjetas, cada una **con su definición escrita debajo**, y todas enlazadas a su listado |
| Gráficos de evolución | Doce semanas de actividades e inscripciones, en SVG generado desde los datos |
| Pendientes de revisión | Tabla con inscritos, tiempo esperando y botón directo, lo que más lleva esperando primero |
| Últimas inscripciones | Persona, actividad, organización, estado y cuándo |
| Alertas | Revisiones atrasadas, correos fallidos y organizaciones sin verificar, más las tres del correo que ya existían |
| Accesos rápidos | Siete acciones, todas a rutas reales |

---

## 2. Las definiciones son la mitad del trabajo

Un número sin definición es un número que nadie puede comprobar. Dos de los
KPIs pedidos admitían más de una lectura, y elegir en silencio habría sido
elegir mal:

**«Organizaciones activas».** Podía ser el total registradas, las verificadas, o
las que están participando de verdad. Se eligió **las que tienen al menos una
actividad publicada**, porque es lo que mide una campaña. Las otras dos lecturas
van al lado en la misma tarjeta —«con actividad publicada, de 2 registradas»,
y una tarjeta aparte para verificadas— para que la diferencia se vea en vez de
tener que suponerla.

**«Total inscritos».** Se cuenta **sin las canceladas**: ese número se lee como
aforo, y una inscripción cancelada no es una persona que vaya a presentarse.
Debajo, cuántas están confirmadas y cuántas se cancelaron, para que nadie tenga
que adivinar qué entró en la suma.

Las dos definiciones están escritas en la propia pantalla, no sólo en el código.

---

## 3. Dos decisiones que no se ven pero cambian el número

**La espera de una revisión se mide desde `activity_status_logs`, no desde
`updated_at`.** Es la diferencia entre un número correcto y uno que miente
justo cuando importa: `updated_at` lo mueve cualquier cambio posterior, así que
una actividad que lleva ocho días esperando aparecería como recién llegada en
cuanto alguien le tocara una coma. La consulta busca el último paso a
`revision` en el historial y sólo cae a `updated_at` cuando no hay historial
—actividades anteriores a que existiera esa tabla—, que es lo único que hay.

**El plazo de la alerta es un ajuste, no una constante.** `alerta_revision_dias`,
3 por defecto, en Configuración → General. Un umbral escrito en el código
obliga a un despliegue para cambiar de opinión sobre cuántos días son
demasiados. La pantalla de Configuración se dibuja sola desde los metadatos del
seeder, así que añadirlo ahí bastó para que salga con su validación de entero.

---

## 4. El gráfico, sin librería

SVG generado desde los datos, en `partials/admin/grafico-semanas`.

No es purismo: **este proyecto ya pagó una vez por meter JavaScript de fuera.**
`support.js` descargaba React, ReactDOM y Babel standalone desde unpkg en cada
carga, y se eliminó el día 24 por eso mismo. Traer una librería de gráficos para
dibujar veinticuatro rectángulos sería el mismo error con otro nombre.

Tres detalles que costaron pensarlos:

- **Las semanas las corta Carbon, no `YEARWEEK` de MySQL.** La numeración de
  semanas del motor cambia según el modo que se le pase, y el resto del panel
  pinta las fechas con Carbon. Se traen los conteos por día —84 filas como
  mucho— y se reparten en memoria, así la semana la define un solo reloj.
- **El techo del eje se redondea hacia arriba a algo divisible entre cuatro.**
  Con un máximo de 7, las marcas caían en 1,75 / 3,5 / 5,25 y no se podía leer
  ninguna.
- **En móvil el gráfico se abre por el final.** No cabe entero y se desplaza,
  como las tablas del panel; empezando por la izquierda se veía el hueco de hace
  tres meses y parecía que no había datos, que es lo contrario de lo que pasa.

---

## 5. De paso: el helper de salud del correo, deduplicado

`salud()` —transporte, cola y plantillas— estaba copiado en `EmailLogController`
y en `SmtpSettingController`, y la portada necesitaba una tercera copia. Ahora es
`DiagnosticoCorreo::salud()` y los tres lo piden.

La portada no repite esas alertas: el aviso del correo ya sabe distinguir un
transporte que no entrega de una cola sin worker, y contarlo dos veces con dos
redacciones distintas es la forma de que acaben diciendo cosas diferentes.

---

## 6. Cómo se verificó

Dos scripts nuevos, los dos en el repo.

### `pruebas/panel-home.mjs` — 26 comprobaciones, 26 en verde

No comprueba que la pantalla cargue. **Lee el número que se pinta, cuenta lo
mismo en MySQL con una consulta escrita aparte, y los compara.**

| Sección | Qué |
|---|---|
| 1 | Los cinco KPIs contra su consulta |
| 2 | Los cinco estados y el total |
| 3 | Filas de las tablas, totales del gráfico y accesos rápidos |
| 4 | **Se mueve la base y se vuelve a mirar** |
| 5 | La alerta de revisión atrasada, encendida y apagada |

La sección 4 es la que importa: publica una actividad y comprueba que
«publicadas» sube en uno; lo deshace y comprueba que vuelve. Inscribe a alguien,
comprueba que sube y que aparece en la tabla; lo cancela, comprueba que se
descuenta; lo borra, comprueba que queda como estaba. **Mientras haya filas, un
número escrito a mano puede parecer correcto por casualidad; en cuanto la base
se mueve, deja de serlo.**

### `pruebas/panel-vacio.php` — con la base vacía, todo cero

La comprobación que de verdad delata un dato de ejemplo. Borra actividades,
inscripciones, organizaciones y correos **dentro de una transacción que siempre
se deshace**, y comprueba que los doce números dan cero, que los cinco estados
siguen listados en cero en vez de desaparecer, y que el techo del eje nunca es
cero —o el gráfico dividiría por cero al primer despiste—.

### Dos fallos, los dos míos y los dos en el script

Ninguno en la aplicación:

1. El menú lateral también dice «Publicadas» y «Canceladas» —son nodos del
   árbol del bloque D—, así que partir el HTML por esa palabra leía el número de
   otra parte de la página. Acotado a la tarjeta.
2. Lo mismo con «Inscripciones» en la leyenda del gráfico.

Quedan anotados porque enseñan algo: una prueba que busca texto en una página
entera prueba la página entera, no lo que uno cree.

### Lo demás

- `humo.mjs`, `permisos.mjs` (29/29) y `menu.mjs`, sin fallos.
- Revisada en Chrome a 1440 y a 390 px: **sin desborde horizontal en ninguna de
  las dos y sin errores de consola.**
- Base restaurada al terminar; los datos de prueba, borrados.

---

## 7. Decisiones tomadas

### Tomadas dentro del bloque, y anotadas

- **«Organizaciones activas» = con actividad publicada**, con las otras dos
  lecturas visibles al lado.
- **«Inscritos» sin las canceladas**, con el desglose debajo.
- **La espera se mide desde el historial de estados**, no desde `updated_at`.
- **El plazo de la alerta es un ajuste**, no una constante del código.
- **Gráfico en SVG propio**, sin librería ni CDN.
- **La portada no repite las alertas del correo**: las pinta su propio aviso.
- **Los números viven en `ResumenPanel`**, no en el controlador: cada uno lleva
  detrás una decisión, y esas decisiones tienen que poder leerse juntas y
  comprobarse sin pasar por una petición HTTP.

---

## 8. Estado del entorno

**Sí hay que compilar en este bloque.** Se tocó `resources/css/app.css` —las
tarjetas de KPI ahora son enlaces y necesitaban su estilo—, así que `npm run
build` está hecho y `public/build/` va en el commit. Sin eso el servidor
desplegaría el CSS viejo, porque el cron hace `git pull` pero no compila.

**Ajuste nuevo:** `alerta_revision_dias`. Lo siembra `dps:instalar`, que es
idempotente. Si no se corre, `Setting::get` cae al 3 por defecto y la alerta
funciona igual; lo que no sale es la fila en Configuración → General para
cambiarlo. **Un motivo más para el pendiente número uno: `dps:instalar` sigue
sin estar en el cron de despliegue.**

Sin migraciones.

---

## 9. Próximos pasos

**El bloque F no se arranca todavía**: Jonas espera indicaciones de su PM sobre
el home y prefiere tenerlas antes.

1. **Añadir `dps:instalar` al cron de despliegue.** Sigue siendo el arreglo más
   barato de la lista, y ahora también trae el ajuste nuevo.
2. **Comprobar que existe el cron del scheduler.** En local la cola está parada
   y la portada lo dice en rojo; en el servidor hay que confirmarlo.
3. **`APP_DEBUG=false` en producción.**
4. **Credenciales de prueba en `UserSeeder.php`**, líneas 17 y 28.
5. **Bloque F**, cuando lleguen las indicaciones del PM.
6. **Bloque G — CRUDs** (11 tareas), si hace falta adelantar trabajo mientras.
7. **2FA**, la única tarea abierta del bloque B.

### Abierto de este bloque

- **La portada no se ha visto con datos de producción**, sólo con los siete
  registros de local. Los números están comprobados contra la base; lo que no se
  ha visto es cómo queda la pantalla con volumen de verdad.
- Los menús del panel siguen sin ocultar lo que un rol no puede ver. Viene del
  bloque C y hoy no hace falta.
