# Bitácora — Bloque J, biblioteca de medios

Primera mitad del bloque: **la biblioteca y el selector**. El procesamiento
—miniaturas, WebP, recorte— queda para el final, que es lo que más depende del
servidor, tal como se acordó.

Es el pendiente que arrastraban los bloques F y G: hasta hoy, poner una imagen
era escribir su ruta a mano y confiar en que el archivo ya estuviera en el
servidor.

---

## 1. Qué trae el servidor, comprobado antes de escribir nada

Producción declara `gd`, `imagick`, `exif`, `fileinfo` y `zip`.

**El motor es GD, no imagick**, y la razón es de método más que técnica: en el
PHP local no hay imagick, así que no podría probar nada de lo que escribiera
contra él, y en este proyecto no se marca `[x]` sin ejecutarlo. GD llega a todo
lo que pide el bloque —redimensionar, recortar, escribir WebP— y en local
declara soporte de WebP **y** AVIF.

`exif` tampoco estaba en local. Estaba la DLL, sólo comentada en `php.ini`: se
habilitó, con respaldo al lado (`php.ini.respaldo-2026-08-28`), para que el
entorno iguale a producción. Sólo hace falta para la orientación de las fotos
de móvil, que es del tramo de procesamiento.

**Ninguna librería de Composer.** Ni lo hecho ni lo que queda la necesita.
Intervention Image envolvería GD sin aportar nada que no se resuelva en unas
pocas funciones, y este proyecto ya descartó dependencias por menos.

### El límite de subida, que es la trampa de este bloque

En local `upload_max_filesize` son **2 MB** y `post_max_size` 8 MB.

Pasarse de `post_max_size` **no da un error de validación**: PHP descarta el
cuerpo entero antes de que Laravel lo vea, así que llega una petición sin
`$_FILES`, sin `$_POST` y **sin token CSRF**. El usuario ve un error de sesión
que no tiene nada que ver con lo que hizo. Es exactamente la familia de fallos
mudos que ya costó el bloque A.

Por eso el tope efectivo se calcula como el menor de los tres —los dos de PHP y
un ajuste configurable—, se enseña en la pantalla de subida, y el navegador
frena el archivo antes de enviarlo. Y por eso el JavaScript traduce el 413 y el
419 a una frase en castellano en vez de dejar un `catch` mudo.

---

## 2. La migración: no se movió nada

**Las 75 imágenes de `public/img` se quedan donde están.** Se indexan en su
sitio.

Funciona porque todas las columnas de imagen del proyecto guardan ya la ruta
**relativa a `public/`**: `img/manos.png` y `storage/medios/2026/08/x.png` son
la misma forma con distinta carpeta, las dos resueltas con `asset()`. Indexar no
tocó ni una fila de las otras tablas.

La columna `origen` separa las dos procedencias, y no es cosmética:

| `origen` | Dónde vive | En git | Se puede borrar o reemplazar |
|---|---|---|---|
| `codigo` | `public/img` | sí | **no** |
| `subido` | `storage/app/public/medios/AAAA/MM` | no | sí |

Lo del repositorio no se borra desde el panel porque el siguiente `git pull` lo
repondría: el borrado sería una mentira. El servidor lo impide también, no sólo
la pantalla.

`dps:indexar-medios` es idempotente y va enganchado a `dps:instalar`, para que
una base limpia no nazca con la biblioteca vacía teniendo las 75 imágenes ahí
mismo. Es el mismo agujero que dejó a producción sin plantillas de correo.

---

## 3. Dos cosas que sólo se ven construyéndolo

### 3.1 Una imagen puede estar en uso sin que ninguna fila la nombre

La primera versión del detector miraba las seis columnas de imagen y el JSON de
las secciones del home. Con eso, `img/logo-cos.png` y la imagen del hero salían
como **«sin usar»**.

Y sostienen la portada.

Los valores por defecto de las secciones viven en `CatalogoHome`, en el código,
y sólo bajan a la base cuando alguien edita esa sección. Mientras nadie la haya
tocado, no hay ninguna fila que nombre esa imagen.

El detector mira ahora las tres procedencias: filas, JSON de las secciones
—incluido el borrador, porque una imagen que sólo está en el borrador rompe la
sección en cuanto alguien publique— y valores por defecto del catálogo.

### 3.2 La fecha era la del indexado, no la del archivo

Las 75 nacían con la fecha del día en que se corrió el indexado, así que el
filtro «desde hoy» las devolvía todas. Ahora sale de `filemtime`, que es lo más
cerca que se puede estar de cuándo entró esa imagen al proyecto: quedan en el 24
y 25 de agosto, que es cuando se añadieron.

---

## 4. Qué se construyó

**Base:** tabla `media`, modelo `Media`, servicio `Services\Biblioteca` —guardar,
indexar, detectar usos y calcular los límites reales del servidor— y el comando
`dps:indexar-medios`.

**Pantalla:** cuadrícula con miniaturas, no tabla. Aquí se busca mirando, no
leyendo: en una lista de nombres de archivo no se distingue una foto de otra, y
la mayoría de estos nombres vienen de un banco de imágenes. Filtros por
búsqueda, formato, carpeta, procedencia y fecha, todos en la URL como en el
resto del panel. Ficha de detalle con datos, edición de título, texto
alternativo y carpeta, reemplazo conservando la URL, y «dónde se usa».

**Selector:** `<x-panel.imagen>`, un campo que abre la biblioteca en un diálogo.
Lo que envía sigue siendo **la misma cadena de siempre** —la ruta relativa a
`public/`, en un campo oculto con el mismo `name`—, y por eso entró en
formularios ya escritos sin tocar sus controladores ni su validación.

El diálogo carga por `fetch` y no navegando: vive dentro de un formulario a
medio rellenar, y una recarga se llevaría lo escrito. También deja subir sin
salir, y lo recién subido queda elegido.

Conectado en: **el editor del home** (3 campos), **los CRUD de noticias,
ediciones, testimonios y partners**, y **el logo de la organización** —donde el
texto de ayuda decía literalmente «todavía no hay subida de archivos»—.

De regalo, el listado de contenido enseña ahora una miniatura donde antes
enseñaba la ruta recortada.

---

## 5. Verificación

`pruebas/bloque-j.mjs`, **49 comprobaciones en Chrome real, 49 en verde.**

Cubre la cuadrícula y las miniaturas, los cinco filtros, subir dos archivos de
una tanda, la normalización del nombre (`Foto de Prueba ÁÉÍ.png` →
`foto-de-prueba-aei.png`, conservando el original para enseñarlo), las medidas y
el tipo real leídos del archivo, el selector desde tres formularios distintos,
subir desde el propio diálogo, editar, reemplazar sin cambiar la URL, las
carpetas, y que borrar esté frenado **tanto en pantalla como en el servidor**
—se comprueba lanzando un DELETE a mano—.

Como regresión, porque el bloque toca `ContentController` y el formulario de
contenido: `bloque-g` 112/112, `bloque-g2` 37/37, `home-editor` 74/74,
`bloque-h` 43/43 y `humo` todas OK.

La prueba limpia detrás: borra **todos** los archivos que sube, no sólo el
último. La primera versión dejaba huérfanos en `storage/medios` a cada pasada.

---

## 6. Lo que NO está comprobado, y hay que mirar

- **El arrastrar y soltar archivos.** La subida múltiple está probada por el
  diálogo de archivos; el gesto de arrastrar no se puede sintetizar de forma
  fiable, lo mismo que pasó con el reordenar del bloque G. El manejador está
  escrito y la zona reacciona, pero hay que soltarle archivos encima con el
  ratón una vez.
- **Los límites de subida en producción.** Si `upload_max_filesize` son 2 MB
  como en local, una foto de móvil no entra. Es un dato del servidor, no del
  código.

---

## 7. La portada de actividad: por qué no la toqué

Es el único punto del selector que quedó sin hacer, y a propósito.

Esa imagen **no se edita en el panel**: la sube el organizador desde
`/mi-cuenta`, y ahí ya funciona con una subida de archivo de verdad, no
escribiendo una ruta. No es el problema que este bloque viene a resolver.

Ponerle el selector significaría dejar que **cualquier organizador se pasee por
la biblioteca entera de la ONG**: logos de otras organizaciones, material
interno, todo lo subido. Eso es una decisión de Jonas, no mía. Las salidas
razonables son tres: dejarlo como está, darle un selector que sólo vea lo que ha
subido él, o abrirle una carpeta pública.

---

## 8. Punto de retoma

1. **Decidir lo de la portada de actividad** (punto 7).
2. **El tramo de procesamiento**, que es lo que queda del bloque J: validación de
   dimensiones, miniaturas, WebP con reserva, recorte al subir. Todo sobre GD.
   Conviene mirar antes los límites reales del servidor.
3. **El bloque I**, que ya puede usar el selector para el logo y el favicon: era
   justo lo que lo bloqueaba.

Sigue abierto de antes, sin tocar hoy: `dps:instalar` no está en el cron de
despliegue —aunque ahora siembra también la biblioteca, así que importa más—,
falta comprobar el cron del scheduler, las credenciales de prueba del
`UserSeeder`, y `APP_DEBUG` en `false`.
