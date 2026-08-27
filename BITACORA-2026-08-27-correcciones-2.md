# Bitácora — 2026-08-27 · Segunda ronda de correcciones

Cowork volvió a pasar el plan de testing. Las cinco correcciones del bloque F
aguantaron, y el E y el H salieron limpios. Cuatro fallos nuevos y tres
observaciones menores.

---

## 1. El botón del hero: tercera vuelta al mismo tema

La secuencia completa, porque la lección está en la secuencia y no en el
arreglo:

1. Una palabra larga sin espacios desbordaba la sección.
2. Se puso `overflow-wrap: anywhere` en el `<body>` del home → **rompió el menú
   de cabecera**, que empezó a partir «Actividade s».
3. Se acotó a los elementos editables con la clase `dato-editable` → **rompió el
   botón del hero**: a 375 px medía 140×106 px en cuatro líneas, con
   «participa / r hoy?» partido y tapando el logo del corazón. A 1440 px, dos
   líneas donde el diseño tiene una de 314×46.

**La lección, ahora en firme: partir una palabra arregla un párrafo y estropea
un control.** Un titular largo que se parte sigue leyéndose; un botón que se
parte deja de parecer un botón. Los otros cuatro botones editables aguantaban
sólo porque les sobraba ancho, no porque estuviera bien: el del hero va
encajado dentro del corazón y no le sobra nada.

La clase se quitó de los cinco controles y quedó únicamente en texto corrido.

### La otra mitad, que no estaba en el informe

Sin partir, un texto de botón de cien letras se sale de su caja y empuja lo que
tenga al lado. Así que los cinco botones llevan ahora `boton-editable`: no
parten, pero se acotan y recortan por el borde. Recortar tampoco es bonito;
entre eso y cruzar la pantalla en cuatro líneas, esto se lee y aquello no.

Y ahí apareció **una trampa propia de este proyecto**: `max-width` no acotaba
nada, porque **no hay reset global de `box-sizing: border-box`** —se quitó el
día 25, porque el HTML fuente no lo tiene y restaba el padding al ancho de cada
contenedor—. Sin `border-box`, `max-width` acota sólo la caja de contenido y los
60 px de padding se suman por fuera: el botón medía 483 px dentro de un hueco de
470. Se puso `box-sizing: border-box` **sólo en esos cinco botones**.

### Esta vez se revisó todo, no lo sospechado

Una comprobación que recorre `a`, `button`, `.btn`, `input`, `select` y
`[role=button]` de la página entera y exige que **ninguno** tenga la regla. Más
la medida de cada botón contra su contenedor a 1440, 900, 390 y 375 px.

Resultado: el botón vuelve a 314×46 px en una línea a 1440, y a 375 px mide
266 px dentro de un contenedor de 295, también en una línea, sin desbordar.

---

## 2. Las anclas: eran dos cosas distintas

**La primera, que sí era un fallo:** `#ediciones` no tenía `scroll-margin-top`.
Las otras cuatro anclas del sitio sí lo llevan (90 px, y 180 el del
voluntariado), así que ésa caía debajo de la cabecera fija.

**La segunda, la que explica los tres resultados distintos:** el sitio lleva
`scroll-behavior: smooth`, así que pulsar «Ediciones» desde arriba lanza un
desplazamiento animado de más de cinco mil píxeles. Durante ese viaje se cruzan
las imágenes con carga diferida; cada una que entra reajusta la página y el
anclaje de scroll de Chrome empuja en sentido contrario para «conservar» lo que
se está viendo. El viaje se corta en un sitio distinto cada vez.

**La solución no fue quitar el desplazamiento suave** —se pierde la referencia
de dónde estabas— sino comprobar dónde se acabó parando y corregir:
`resources/js/anclas.js` deja llegar, espera a que la página se quede quieta
(`scrollend`, con temporizador de reserva), y si el destino no está donde
debería, ajusta. Se corrige **dos veces**: al terminar el desplazamiento y otra
cuando acaba de cargar todo, porque una imagen que entra tarde vuelve a mover el
destino.

Y si mientras tanto la persona toca la rueda o el teclado, se cancela: nada peor
que un sitio que te devuelve a donde él quiere.

**Comprobado en las seis anclas del sitio**, por URL directa y pulsando el enlace
del menú desde arriba del todo: desvío de 0 px en todas. Los contadores de la
sección animan al llegar.

---

## 3. El video: la regla del valor por defecto tapaba un estado legítimo

Vaciar el campo del video y publicar decía «Publicado», pero el campo volvía a
mostrar `e8iqqzO3s7k` y el home seguía con el video. Coherente con la regla que
anuncia el editor —«un campo vacío vuelve al texto original»— y aun así mal: la
variante de una columna quedaba fuera del alcance del panel.

Lo peor es que **la ayuda del propio campo prometía que funcionaba**: «Déjalo
vacío para que el video no aparezca».

Se arregló como clase de problema y no como caso suelto: los campos donde vacío
es una respuesta válida se marcan con **`'vaciable' => true`** en el catálogo, y
`HomeSection::valor()` los respeta. Se distingue con `array_key_exists` y no con
`blank`, porque hacen falta las dos cosas: «se guardó vacío» y «nunca se ha
guardado» son estados distintos.

**La revisión que pedía el informe encontró un segundo caso con la misma
promesa incumplida:** `hero.titulo_destacado`, cuya ayuda dice «Déjalo vacío si
no quieres destacar nada». Y dos más donde vaciar es legítimo aunque no lo
prometieran: el destacado de «¿Qué es?» y el de «es una iniciativa de». Los
cuatro marcados.

---

## 4. El foco del modal

Al abrirlo con el ratón, el foco se quedaba en el botón «Borrar», fuera del
`role="dialog"`. El motivo: Alpine aplica el `x-show` en su propio ciclo, y
hasta que el elemento no está visible no admite el foco; un solo
`requestAnimationFrame` no llegaba a tiempo.

Ahora se reintenta unos fotogramas y **se comprueba que el foco acabó de verdad
dentro**, en vez de darlo por hecho. Si aun así no entra, se enfoca la caja del
diálogo, que lleva `tabindex="-1"` para eso.

Anotado para la próxima: *pedir* el foco y *tenerlo* no son lo mismo, y sólo lo
segundo se puede comprobar.

---

## 5. Las tres menores

**La hora en UTC.** No era sólo el aviso del borrador: **el panel entero
mostraba UTC**, cuatro horas por delante de Chile, en 21 sitios repartidos por
once archivos.

Cambiar `APP_TIMEZONE` habría sido peor: Laravel pasaría a escribir en hora
local y todo lo ya guardado —correos, accesos, inscripciones— se leería
corrido. Se guarda en UTC y **se convierte al pintar**, con `App\Support\Fecha`
y una zona configurable (`APP_ZONA_HORARIA`, América/Santiago por defecto). Las
21 llamadas sueltas pasan ahora por ahí.

**«5 versións».** `Str::plural` de Laravel pluraliza en inglés. Acierta por
casualidad con las palabras que acaban en vocal —«dato», «registro»— y falla con
el resto: `Str::plural('actividad')` da «actividads», y en una vista ya había un
parche a mano pegando la `s` por fuera, que es la señal de que la herramienta no
era la correcta. Ahora hay `App\Support\Texto::plural`, con las reglas del
castellano —incluida la que se olvida siempre: al añadir `-es` desaparece la
tilde de la última sílaba, «versión» → «versiones»—.

**Sin indicador al ordenar y paginar.** Son navegaciones normales, así que el
navegador ya lo enseña en la pestaña; pero eso queda lejos de donde está la
mano. Ahora la tabla se atenúa y deja de admitir clics, para que no se pulse dos
veces la misma columna creyendo que no ha respondido.

---

## 6. Cómo se verificó

**32 comprobaciones en Chrome real**, 32 en verde: los cuatro fallos y las tres
menores, incluida la hora del borrador contrastada contra `CONVERT_TZ` de MySQL.

**Regresiones:** `humo`, `home-editor` (74/74), `panel-home` (27/27),
`permisos` (29/29), interfaz sin partir palabras (18/18) y componentes del panel
(43/43).

**Tres fallos de mis propias pruebas**, anotados porque enseñan algo:

1. El enlace del menú es `href="http://…/#ediciones"`, absoluto, no
   `#ediciones`. El selector de la prueba buscaba el literal y no encontraba
   nada: la prueba decía «desvío 5.277 px» cuando lo que pasaba es que nunca
   llegó a pulsar.
2. Las páginas de un mismo navegador comparten cookies, así que la segunda
   sección de la prueba encontraba `/admin/login` ya redirigido.
3. Un `.click()` desde `evaluate` no mueve el foco; hay que usar el ratón de
   verdad para comprobar nada que dependa de él.

---

## 7. Estado del entorno

Sin migraciones. **Ajuste de configuración nuevo:** `zona_horaria` en
`config/app.php`, con valor por defecto; no hace falta tocar el `.env`.

Toca CSS y JS: lleva `npm run build` y `public/build/`.
