# Bitácora — 2026-08-27 · Bloque F

El editor de contenido del home. El bloque más grande del backlog y, según el
propio backlog, el más importante: es el que le da autonomía a la ONG para
cambiar su sitio sin pedirnos un despliegue.

Se cerró entero. Antes de escribir una línea hubo que preguntar tres cosas,
porque la lista de tareas y el HTML fuente no coincidían.

---

## 1. Tres cosas que no cuadraban

### El home tiene 12 secciones, no 9

La lista del backlog nombra nueve. El home renderiza doce parciales. Las tres
que faltaban eran copy fijo en el Blade:

| Sección | Qué tiene dentro |
|---|---|
| Súmate a la meta | **Los contadores 500 de 1.000 y 50.000 de 100.000**, escritos a mano |
| ¿Por qué celebramos este día? | Un párrafo largo y su remate |
| Es una iniciativa de | El crédito de la COS |

La primera es la que obligaba a preguntar: son datos de campaña que envejecen
solos. Sin hacerlos editables, actualizar «500 de 1.000» habría exigido un
despliegue cada vez.

**Jonas decidió meter las tres.** Doce secciones editables.

### El video de YouTube no estaba donde decía el backlog

El backlog lo pone en «¿Qué es el Patrimonio Social?». En el HTML fuente el
video está en la sección siguiente, «¿Por qué celebramos este día?», con su
portada de YouTube y su botón de play. Y **nuestra versión no lo había
implementado nunca**: teníamos el texto y una imagen, sin video.

Curiosamente, el manejador de JavaScript sí estaba —`playVideo` en `app.js`,
del día 24, construyendo el iframe con `createElement` y `encodeURIComponent`—.
Lo que faltaba era el marcado que lo dispara.

**Jonas decidió añadirlo replicando el fuente**, con el identificador editable
desde el panel. Es el único cambio de aspecto del sitio público en todo el
bloque, y va en la dirección de la regla de fidelidad: cierra un hueco del
punto 8.

### Hero y «¿Cómo participar?» no se pueden mover

La segunda sube **96 píxeles por encima** de la primera —`margin:-96px auto 0`—
para que las tarjetas se monten sobre la imagen de portada. Separarlas deja un
agujero.

**Jonas eligió anclarlas**: las dos se editan, ninguna se arrastra ni se apaga.
El editor lo explica en pantalla en vez de dejar que alguien lo descubra
rompiéndolo, y **el servidor las repone en su sitio aunque llegue un POST con
otro orden**: el editor no las deja arrastrar, pero eso es el dibujo, no la
regla.

---

## 2. Cómo está construido

### Los textos por defecto SON el HTML fuente

`App\Support\CatalogoHome` tiene los más de sesenta campos con su valor exacto
del prototipo, copiado uno a uno. **La base sólo guarda lo que se ha cambiado.**

Eso es la regla 5 y además una red de seguridad concreta para este proyecto: el
cron del servidor migra pero **nunca siembra**, así que si la base mandara, el
home quedaría en blanco tras el primer despliegue con la tabla recién creada.
Tal como está, el home se ve bien sin una sola fila.

Y tiene una consecuencia que se nota al usarlo: **vaciar un campo lo devuelve al
texto original**. Es lo que espera quien borra algo sin querer, y evita el
clásico hueco en blanco en producción.

### Publicado y borrador son dos columnas distintas

`contenido` es lo que ve el público; `borrador` es lo que va escribiendo el
autoguardado cada tres segundos. Si compartieran columna, escribir en el panel
cambiaría el sitio a media frase.

La vista previa es **el home de verdad** —mismo método de datos, mismos
parciales, mismo CSS— leyendo los borradores. Una vista previa que se arma por
su cuenta enseña otra cosa, y entonces no sirve para decidir si publicar.

### El historial sólo crece

Cada publicación guarda **lo que ya estaba** antes de pisarlo. Restaurar no
borra lo que vino después: publica una copia de la versión antigua y guarda la
actual como una más. Un historial del que se pueda borrar no sirve para lo único
que sirve un historial.

Guardar lo *nuevo* habría sido el error fácil: la primera fila del historial
sería siempre igual a lo que ya se ve.

### La sanitización no está escrita a mano

`symfony/html-sanitizer`, que implementa la especificación del W3C. Un filtro
casero a base de expresiones regulares es la forma clásica de dejar pasar un
XSS creyendo lo contrario, y en este proyecto ya apareció uno en la vista previa
de plantillas.

Lista blanca de trece etiquetas. Dos comportamientos distintos a propósito:

- **Lo peligroso se va con su contenido dentro**: `script`, `style`, `iframe`,
  `object`, `embed`, `form`, `input`, `svg`, `math`…
- **Lo desconocido se desenvuelve conservando el texto.**

Lo segundo salió de un fallo real, ver §4.

Los campos que no son de texto rico tienen su propia limpieza: el de video
guarda **sólo el identificador** —nunca una URL ni un iframe—, los enlaces
aceptan `http`, `https`, `mailto`, `tel` y rutas internas, y las imágenes son
rutas dentro de `public/` sin `..` ni dominios de fuera.

**Se limpia dos veces: al guardar y al pintar.** Guardar limpio es lo correcto;
la segunda pasada es lo que protege si algún día entra HTML por otra vía —una
importación, una fila tocada a mano— y cuesta prácticamente nada.

### Sin librerías

El editor rico es un `contenteditable` con su barra, y el arrastre son eventos
HTML5. Doscientas líneas de Alpine. El proyecto quitó `support.js` justo por
descargar React y Babel de unpkg en cada carga; traer un editor completo para
escribir párrafos con alguna negrita sería el mismo error con otro nombre.

Los títulos van partidos en «antes / destacado / después» en vez de ser texto
rico. En el fuente la palabra naranja es un `<span>` dentro de un `<h1>` con su
tamaño y su interletraje exactos: con un editor libre, la primera negrita que
alguien pusiera cambiaría el peso del titular. Partido, la ONG cambia las
palabras y el diseño no se mueve.

---

## 3. Que el diseño no se rompa (reglas 1 y 2)

### Regla 1 — el frontend no cambia de aspecto

Demostrado, no supuesto, y por dos caminos.

**El HTML renderizado, antes y después.** Se capturó el home con el bloque
puesto, se guardó el trabajo en el stash, se capturó otra vez, y se compararon
las 710 líneas. Diferencias: 29, y todas explicadas —el video, un espacio en
blanco irrelevante, un `margin-bottom:10px` que pasa a `margin:0 0 10px`, y el
envoltorio del texto rico—.

**La geometría medida en Chrome, antes y después.** De 42 medidas, **35
idénticas**. Las 7 que cambian son: la columna de «¿Por qué celebramos?», que
pasa de 1180 px a 560 px porque ahora tiene el video al lado (lo aprobado), y el
margen inferior del último párrafo dentro del texto rico, que pasa al
contenedor.

Ese último merecía comprobación aparte, y se hizo midiendo los huecos en
pantalla: **16 px entre los dos párrafos y 20 px antes de la frase destacada**,
exactamente lo de antes. El margen se mueve de sitio; el hueco no.

De paso, la tipografía se comparó contra la referencia en vivo: tamaño, interlineado
y color coinciden en los cinco bloques medidos. **El ancho no se comparó contra
la referencia**, y conviene anotar por qué: esa página sirve sus fuentes con
404, y sin la tipografía cargada la unidad `ch` mide otra cosa. Da 703 px donde
nosotros damos 647. No es una desviación nuestra —nuestro `h1` mide igual antes
y después del bloque— pero es la clase de dato que parece un fallo si se mira
sin contexto.

### Regla 2 — el editor no puede romper el diseño

CSS acotado a `.texto-editable`: los encabezados que ofrece el editor se quedan
por debajo del titular de la sección, y `overflow-wrap:anywhere` parte una
palabra sin espacios de 600 caracteres en vez de estirar la columna.

Comprobado en Chrome a **1440, 900 y 390 px** con una palabra de 600 letras, un
párrafo de 6.000 y una mezcla de encabezados, listas, cita y enlaces: **cero
desborde horizontal en los tres anchos**, y la palabra larga siempre dentro de
su caja.

---

## 4. Dos fallos, encontrados ejecutando

### Pegar desde Word borraba el párrafo entero

La acción por defecto de Symfony es `Drop`: se lleva la etiqueta **y el texto
que tiene dentro**. Word envuelve cada frase en `<span style="mso-…">`, así que
al guardar desaparecía todo.

Lo peor no era perder el texto: era que la persona pegaba, **veía su texto en el
editor**, publicaba, y se encontraba la sección vacía.

Arreglado cambiando la acción por defecto a `Block` —quita la etiqueta, deja el
texto— y dejando `Drop` explícito para lo peligroso. No afloja la seguridad: una
etiqueta desconocida pierde sus atributos, con sus `onclick`, al desenvolverse.

**Lo encontró `home-editor.mjs`**, precisamente con el caso que pidió Jonas.

### La lista de secciones se apilaba en vertical

Alpine, cuando el valor de `:style` es un string, **reemplaza el atributo entero
en vez de fusionarlo**. La fila tenía su `style` con el `display:flex` y un
`:style` para el realce del arrastre: el segundo borraba al primero y la lista
quedaba en columna, sin bordes.

Lo llamativo es que **ya estaba anotado en `app.js`**, con este mismo comentario,
desde el día 24: pasó igual con los círculos del wizard. Arreglado usando una
clase.

Sólo se vio mirando la captura. Los 50 casos de la prueba por HTTP pasaban.

---

## 5. Cómo se verificó

### `pruebas/home-editor.mjs` — 50 comprobaciones, 50 en verde

No comprueba que la pantalla cargue: **publica de verdad y lee el home público**
para ver si cambió.

| Sección | Qué |
|---|---|
| 1 | Sin nada guardado, el home dice lo del HTML fuente |
| 2 | Se publica y el sitio cambia; se vacía y vuelve al original |
| 3 | Siete ataques distintos, ninguno sobrevive |
| 4 | Contenido extremo |
| 5 | El borrador no toca el sitio; la vista previa sí lo enseña |
| 6 | Historial y restaurar |
| 7 | Encender, apagar y reordenar |
| 8 | El editor es sólo del panel |

Los ataques: `<script>`, `onerror`, iframe ajeno, `href="javascript:"`, un
`style` con `position:fixed`, un formulario que se lleva contraseñas y un `svg`
con `onload`. Se comprueba en el HTML del sitio **y en lo guardado en la base**:
que no se pinte no basta si quedó almacenado.

El contenido extremo, que es lo que pidió Jonas: texto larguísimo, palabra de
500 letras sin espacios, HTML pegado desde Word con sus `MsoNormal` y su tabla
de maquetación, y `Ñandú «comillas» — 中文 🎉 <>&"'`.

### La parte visual — 19 comprobaciones, 19 en verde

Chrome real a tres anchos: tipografía contra la referencia, huecos verticales,
desborde con contenido extremo, y el panel (12 filas, 10 arrastrables y 2
ancladas, editor con su barra). Sin errores de consola.

### Regresiones

`humo.mjs`, `permisos.mjs` (29/29), `panel-home.mjs` (26/26) y `menu.mjs`, todos
sin fallos. La base quedó limpia.

---

## 6. Decisiones tomadas

### Aprobadas por Jonas

- **Las doce secciones son editables**, no las nueve de la lista.
- **El video se añade replicando el HTML fuente**, en «¿Por qué celebramos?».
- **Hero y «¿Cómo participar?» van ancladas**, editables pero no movibles.

### Tomadas dentro del bloque, y anotadas

- **Los textos por defecto viven en el código, no en un seeder.** Es lo que hace
  que el home se vea bien sin sembrar y que vaciar un campo lo devuelva.
- **`symfony/html-sanitizer`** en vez de un filtro casero. Es la única
  dependencia nueva del bloque; el cron del servidor corre `composer install`.
- **Los títulos van partidos** en vez de ser texto rico.
- **El campo de video guarda el identificador, nunca un iframe.**
- **Se sanitiza al guardar y otra vez al pintar.**
- **El historial sólo crece**; restaurar publica una copia.
- **El orden se corrige en el servidor**, no sólo en el editor.
- **`MenuPanel::SECCIONES_HOME` se elimina**: era una segunda lista de secciones
  y ya se había quedado corta al pasar de nueve a doce. Ahora sale del catálogo.
- **El helper `salud()` y la vista `admin/home/seccion.blade.php`**, que quedaron
  sin uso, se retiraron en vez de dejarlos ahí.

---

## 7. Estado del entorno

**Migración nueva:** `2025_01_07_000001_create_home_sections_table` (dos tablas,
aditivas).

**Dependencia nueva:** `symfony/html-sanitizer ^8.1`. El cron del servidor corre
`composer install`, así que llega sola; `composer.lock` va en el commit.

**Sí hay que compilar**: se tocaron CSS y JS. `npm run build` hecho y
`public/build/` en el commit.

`dps:instalar` crea ahora las doce filas de secciones. **No es imprescindible
para que el home se vea** —de eso se encarga el catálogo— pero sí para poder
ordenarlas y apagarlas desde el panel, y el editor las crea solo al abrirse.

---

## 8. Lo que queda abierto

- **Las imágenes se indican por su ruta, escrita a mano.** No hay subida de
  archivos: el campo de imagen del hero o de «¿qué es?» pide una ruta como
  `img/foto.jpg`, y el archivo tiene que existir ya en el servidor. Es el
  pendiente 8 de la lista general y afecta a todo el panel.
- **El editor no está probado con dos personas editando a la vez.** El último
  que publique gana, sin aviso. Con un equipo pequeño no debería pasar, pero
  está sin resolver.
- **`document.execCommand` está marcado como obsoleto.** Funciona en todos los
  navegadores actuales y no hay sustituto estándar todavía; queda anotado.
- Los menús del panel siguen sin ocultar lo que un rol no puede ver (viene del
  bloque C).

---

## 9. Próximos pasos

1. **Añadir `dps:instalar` al cron de despliegue.** Sigue siendo el primero de
   la lista desde hace tres jornadas.
2. **Comprobar el cron del scheduler.**
3. **`APP_DEBUG=false` en producción.**
4. **Credenciales de prueba en `UserSeeder.php`.**
5. **Bloque G — CRUDs** (11 tareas).
6. **Bloque H — Componentes transversales** (8 tareas).
7. **Bloque I — Configuración** (lo que queda: datos de la edición, textos
   legales, modo mantenimiento).
8. **2FA**, la única tarea abierta del bloque B.

**Nota sobre las indicaciones del PM:** el día anterior este bloque quedó en
espera por ellas y hoy Jonas mandó seguir, así que se hizo. No sé si llegaron.
Lo que sí cambia es quién las aplica: **si traen cambios de textos, ahora los
hace la ONG desde el panel sin tocar código ni esperar un despliegue**, que era
justo el punto del bloque. Si traen cambios de *diseño* —otra maquetación, una
sección nueva— eso sigue siendo trabajo de plantilla.
