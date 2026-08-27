# Bitácora — 2026-08-27 · Correcciones del testing en producción

Cowork ejecutó el plan de testing sobre lo desplegado. **Bloques D y E limpios;
el F con cinco fallos**, todos con la causa localizada en el propio informe.

Los cinco corregidos, más doce observaciones menores. Este documento anota lo
que enseñaron, que es más interesante que lo que costaron.

---

## 1. Los dos fallos que eran el mismo fallo

Los fallos 3 (reordenar no guarda) y 4 (el autoguardado nunca llama al servidor)
parecían cosas distintas. Son el mismo error escrito dos veces:

> **En Alpine, `$el` es el elemento del manejador que se está ejecutando, no la
> raíz del componente.**

- `x-on:input` está en **cada campo**, así que cuando saltaba el temporizador
  del autoguardado, `this.$el` era el `<textarea>` recién escrito y
  `new FormData(textarea)` reventaba. El informe lo confirmó instrumentando
  `FormData`: recibía un TEXTAREA.
- `x-on:dragend` está en **cada `<li>`**, así que `this.$el.querySelector('ul')`
  devolvía null —un `li` no contiene al `ul`— y `this.orden` quedaba vacío. El
  POST salía sin un solo `orden[]` y el servidor contestaba 422.

Los tres componentes guardan ahora su raíz en `init()`, que es el único sitio
donde `$el` sí es la raíz.

**Y se cayó una cuarta vez mientras arreglaba esto**: el buscador que escribí
para «filtrar al escribir» hacía `this.$el.submit()` desde un manejador puesto
en el `<input>`. La consola lo dijo: `this.$el.submit is not a function`. Lo
pilló la prueba de navegador antes de salir de local.

Es la tercera vez que el proyecto tropieza con Alpine por lo mismo: ya estaba
anotado en `app.js` desde el día 24 —los círculos del wizard, con `:style`— y
volvió a pasar el día 27 con la lista de secciones. **La regla, escrita donde se
tropieza:** si un manejador vive en un hijo, `$el` no sirve para hablar del
componente.

---

## 2. La negrita que desaparecía

`document.execCommand('bold')` produce `<b>` en unos navegadores y `<strong>` en
otros, y no hay forma de pedirle uno concreto. La lista blanca tenía `strong` y
`em` pero no `b` ni `i`, y como la acción por defecto del saneador es
desenvolver, el texto sobrevivía y el formato no.

El editor aplicaba la negrita bien. Se veía en pantalla. Se perdía al publicar.

Ahora **pasan las dos formas y se guardan siempre como `<strong>` y `<em>`**: es
la etiqueta semántica, la que anuncia un lector de pantalla y la que espera el
CSS del sitio. El renombrado se hace después del saneador, cuando el HTML ya
pasó la lista blanca y `b` sólo puede venir sin atributos.

---

## 3. El desbordamiento tenía dos capas

El informe fue preciso: 2.507 px dentro de un contenedor de 560, cruzando la
pantalla y por encima de la fotografía. Y el aviso de revisar **todos** los
campos, no sólo ese, era el aviso correcto: el `overflow-wrap` del bloque F
sólo cubría `.texto-editable`, es decir el cuerpo rico, y no los titulares ni
las bajadas ni los botones.

Se resolvió marcando el `<body>` del home con `home-editable` y aplicando la
regla a todo lo que hay dentro. Pero al probarlo apareció **una segunda capa**:

- `.btn` lleva `white-space: nowrap` —correcto para «Conoce más»— y con nowrap
  el `overflow-wrap` no tiene nada que hacer.
- El botón del hero lleva ese `nowrap` **en el atributo `style` en línea**, que
  gana a cualquier hoja de estilos. Hubo que sacarlo a una clase.

Sin la prueba en navegador esto se habría dado por arreglado con la primera
capa: el desbordamiento *de página* ya era cero, y lo que seguía saliéndose era
un elemento dentro de su caja. La comprobación que lo detectó mide cada elemento
contra su contenedor, no la página contra la ventana.

---

## 4. La contradicción de diseño

El informe la señaló bien: la pantalla prometía «lo que escribas se guarda solo
como borrador» y el único botón era Publicar.

**Se hicieron las dos cosas.** El autoguardado cumple la promesa —ahora de
verdad— y además hay un botón **Guardar borrador**. No es redundancia: una
promesa invisible que se rompe no la nota nadie, y eso es exactamente lo que
pasó. Con el botón, quien no se fíe tiene dónde pulsar y ve la hora del guardado.

---

## 5. Los contadores negativos

«Personas participando **-11.631** de 100.000», visible menos de un segundo.

La causa: el instante que recibe `requestAnimationFrame` es el del comienzo del
fotograma, y **puede ser anterior** al `performance.now()` capturado dos líneas
antes. Con `p` negativa, la cúbica `1-(1-p)³` sale negativa. Con p = -0,01 da
-0,03, que sobre 100.000 son -3.000. Acotada por abajo además de por arriba.

Y lo segundo que reportaron —que sólo animan con scroll real— tenía otra causa:
`requestAnimationFrame` no corre en una pestaña de fondo, y llegando por ancla
el elemento ya estaba dentro antes de que el observador empezara a mirar. Ahora
hay un repaso al cambiar el ancla y una red de seguridad que deja el contador en
su número aunque la animación no llegue a ejecutarse nunca.

---

## 6. Las demás correcciones

| Qué | Cómo quedó |
|---|---|
| «1 días» con el plazo en 1 | «hace más de un día» |
| `<p><ul>…</ul></p>` deja `<p></p>` sueltos | El servidor quita los párrafos vacíos |
| «Personas inscritas» contaba inscripciones | «24 inscripciones · de 8 personas» |
| Dos KPI iban al listado completo | Filtros `?estado=activas` y `?filtro=activas` |
| «Usuarios» sin `?rol` | El acceso rápido lo lleva |
| Sin migas en perfil, buscador y usuarios | Las migas tienen salida para lo que no está en el árbol |
| El buscador pedía Enter | Filtra al escribir, ya en la pantalla de resultados |
| Historial al final, y sin poder mirarlo | Arriba, plegado, y se ve qué decía sin restaurar |
| `confirm()` nativo | Confirmación en la propia fila |

Dos merecen una nota.

**«Personas inscritas».** El informe tenía razón y la etiqueta era mía: son 24
inscripciones de 8 personas distintas. Ahora se cuentan y se dicen las dos, y la
tarjeta enlaza a un listado filtrado que enseña esas 24 y no más.

**Los KPI que no filtraban.** Enlazar a un listado que enseña 3 filas cuando la
tarjeta dice 1 es peor que no enlazar. Hizo falta añadir dos filtros que no
existían: «inscripciones sin las canceladas» y «organizaciones con actividad
publicada», que son literalmente las definiciones de esos dos KPI.

**El buscador** filtra al escribir sólo cuando ya estás en la pantalla de
resultados. Fuera de ella sigue haciendo falta Enter, a propósito: llevarte a
otra página a media palabra sería peor que esperar.

---

## 7. Lo que el testing no pudo probar, probado aquí

**Inyección de scripts.** Siete cargas distintas —`<script>`, `onerror`, iframe
ajeno, `href="javascript:"`, un `style` con `position:fixed`, un formulario que
se lleva contraseñas y un `svg` con `onload`— comprobando **el HTML del sitio y
lo guardado en la base**: que no se pinte no basta si quedó almacenado. Ninguna
sobrevive. Se comprobó también que el arreglo de `<b>`/`<i>` no abrió nada: son
las únicas dos etiquetas que se añadieron, sin atributos.

**Arrastrar y soltar con ratón de verdad.** En producción hubo que usar eventos
sintéticos porque Chrome perdía el foco. Aquí se hizo con la interceptación de
arrastre de Chrome vía CDP, que son eventos de ratón reales: el orden cambia en
la base, el home público lo respeta, y el hero sigue anclado el primero.

---

## 8. Cómo se verificó

**`pruebas/home-editor.mjs`: de 50 a 74 comprobaciones**, con una sección nueva
por cada uno de los cinco fallos y otra para las correcciones menores.

**26 comprobaciones más en Chrome real**, que es donde viven tres de los cinco:
el desbordamiento por elemento a 1440/900/390 px, el autoguardado (interceptando
la petición para confirmar que sale), el arrastre con ratón, los contadores
—capturando 1.092 valores intermedios para buscar negativos— y el buscador.

**Regresiones:** `humo.mjs`, `permisos.mjs` (29/29) y `panel-home.mjs` (27/27,
con una comprobación nueva para la cifra de personas distintas).

**Tres fallos de mis propias pruebas**, anotados porque enseñan algo:

1. Un POST sin `Accept: application/json` da 302 con errores, no 422. Cowork vio
   422 porque su cliente mandaba JSON. Las dos son correctas.
2. Cambiar un ajuste con SQL directo no invalida su caché. El panel sí la
   invalida al guardar —`Setting::saved` la olvida— así que el fallo era de la
   prueba, no del código. Convenía comprobarlo antes de tocar nada.
3. El triple clic de Puppeteer no selecciona el texto de un campo, así que la
   prueba concatenaba en vez de reemplazar. Se cambió por Ctrl+A.

---

## 9. Estado del entorno

**Sin migraciones.** Toca CSS y JS, así que lleva `npm run build` y
`public/build/`.

Ningún cambio en la base ni en la configuración: todo son plantillas, estilos,
JavaScript y dos filtros nuevos de listado.

---

## 10. Qué queda abierto

- **Subida de archivos.** Las imágenes siguen indicándose por su ruta escrita a
  mano. Pendiente 8 de la lista general, transversal a todo el panel.
- **`document.execCommand` está obsoleto.** Funciona en todos los navegadores
  actuales y no hay sustituto estándar; es lo que produce además el
  `<p><ul></ul></p>` que el servidor tiene que limpiar.
- **Dos personas editando la misma sección a la vez**: el último que publique
  gana, sin aviso.
- Los menús del panel siguen sin ocultar lo que un rol no puede ver (bloque C).
