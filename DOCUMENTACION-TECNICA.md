# Documentación técnica

Sitio del **Día del Patrimonio Social**, una conversión a Laravel de un
prototipo HTML.

Esto es un punto de partida para alguien que llega de fuera y tiene que
mantenerlo: qué hay, dónde está, cómo se levanta, cómo se despliega y qué cosas
parecen mejorables pero están así por un motivo. No pretende documentar cada
clase; para eso está el código, que va comentado en castellano.

---

## 1. Qué versiones usa

| Pieza | Versión | Dónde se fija |
|---|---|---|
| PHP | 8.4.24 en producción | `composer.json` pide `^8.2`, así que 8.2 en adelante vale |
| Laravel | 12 | `composer.json` |
| MySQL | 8.4 | el servidor; en local, el de Laragon |
| Node | 22 | sólo para compilar los assets; **no hace falta en el servidor** |
| Vite | 6 | `package.json` |
| Alpine.js | 3 | `package.json` |

**Node no se usa en producción.** Los assets se compilan en la máquina de quien
desarrolla y se suben ya compilados; ver el apartado de despliegue, porque es
la trampa que más veces ha mordido.

---

## 2. Dependencias, y para qué sirve cada una

**Son tres, y esa cortedad es intencionada.** El hosting es compartido y sin
Node, y cada dependencia es algo que alguien tendrá que actualizar dentro de dos
años. Aparte de lo que trae Laravel de serie, `composer.json` pide:

| Paquete | Para qué |
|---|---|
| `symfony/html-sanitizer` | Limpiar el HTML que la ONG escribe en el editor del panel. **No sustituirlo por un filtro propio**: ver el punto 7. |
| `openspout/openspout` | Exportar listados del panel a XLSX y CSV, en streaming. Ver `app/Services/Exportador.php`. |
| `laravel/tinker` | Consola interactiva. Sólo se usa en desarrollo. |

**No hay librería de imágenes.** La biblioteca de medios guarda los archivos y
lee sus medidas con `getimagesize`, que viene en PHP. El redimensionado y la
conversión a WebP están pendientes y, cuando se hagan, van sobre **GD**: el
servidor no tiene imagick.

En JavaScript (`package.json`) hay tres: `alpinejs`, `vite` y
`laravel-vite-plugin`. **No hay Tailwind ni ningún framework de CSS**: todo el
diseño es CSS propio en `resources/css/app.css`, un solo archivo. Los prototipos
traen la maquetación en `style` en línea y reescribirla en utilidades habría
sido reinterpretarla; ver el punto 7.

`pruebas/` tiene su propio `package.json` con `puppeteer-core`, que **no se
versiona** y sólo sirve para las pruebas: no entra en la aplicación.

---

## 3. Cómo está organizado

Estructura de Laravel, sin sorpresas, con estas zonas propias:

```
app/
  Http/Controllers/
    Admin/          el panel de administración   (/admin/...)
    Account/        la cuenta del organizador    (/mi-cuenta/...)
    (raíz)          lo público: home, actividades, wizard de publicar
  Http/Requests/    validación de los formularios grandes
  Http/Middleware/  EnsureRole (roles), SoloInvitados, ApplySmtpSettings
  Models/           Eloquent
  Policies/         quién puede tocar qué registro
  Services/         la lógica que no cabe en un controlador
  Support/          ayudas sin estado: formato, catálogos, listados
  Console/Commands/ los comandos propios (punto 6)

resources/
  views/
    public/         el sitio
    admin/          el panel
    account/        mi cuenta
    components/     componentes Blade reutilizables (<x-...>)
    emails/         las plantillas de correo
  css/app.css       TODO el CSS del proyecto, un solo archivo
  js/               Alpine y los componentes de pantalla

pruebas/            pruebas de sistema, en Node. Ver pruebas/README.md
```

**Convención de la base de datos: tablas y columnas de sistema en inglés,
campos de negocio en español.** `activities.titulo`, `users.name`. Es
deliberado: los nombres de negocio se hablan con el cliente en español y
traducirlos en cada conversación costaba más de lo que ordenaba.

**Las taxonomías van todas en una tabla**, `taxonomy_terms`, con una columna
`grupo` (`tema`, `caracteristica`, `publico`, `acceso`) y una tabla pivote.
Añadir un catálogo nuevo no necesita migración.

---

## 4. Levantar el entorno local

Hace falta PHP 8.4, MySQL, Composer y Node 22. En Windows, Laragon lo trae casi
todo.

```bash
git clone git@github.com:jonas549/ong-laravel.git
cd ong-laravel

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Editar `.env` con los datos de la base (`DB_DATABASE=ong_laravel`, usuario y
contraseña de MySQL) y después:

```bash
php artisan migrate
php artisan dps:instalar        # ajustes, plantillas de correo, regiones, taxonomías
php artisan storage:link        # el enlace a storage/app/public; va en .gitignore,
                                # así que hay que rehacerlo en cada instalación

npm run dev                     # compila y se queda escuchando
php artisan serve               # http://127.0.0.1:8000
```

`dps:instalar` **no crea usuarios**. Para tener con qué entrar en local:

```bash
php artisan db:seed --class=UserSeeder
```

Eso crea `admin@ong-laravel.test` y `organizador@ong-laravel.test`, con
contraseñas de prueba escritas en el propio seeder. **Ese seeder es sólo para
desarrollo y no se ejecuta en producción.**

**El correo en local** va a Mailpit si está instalado (SMTP en 1025, bandeja en
http://127.0.0.1:8025). Para probar un envío de verdad, con autenticación, está
`node pruebas/smtp-real.mjs`, que levanta un servidor SMTP mínimo pero honesto;
el porqué está en `pruebas/README.md`.

---

## 5. El despliegue

Producción es un **hosting compartido cPanel (BanaHosting)**. El repositorio
está clonado en `~/ong-laravel`, **fuera del directorio público**, y el
subdominio apunta a `~/ong-laravel/public`.

No hay pipeline ni acción manual: **un cron ejecuta el despliegue cada cinco
minutos**, y hace un `git pull`, instala dependencias de PHP, corre las
migraciones y rehace las cachés.

De ahí salen **tres cosas que hay que saber, y las tres han costado un fallo**:

### El cron NO compila los assets

No hay Node en el servidor. Por eso **`public/build/` se versiona**, al revés de
lo que se estila, y quien toque CSS o JavaScript tiene que compilar antes de
subir:

```bash
npm run build
git add public/build
```

Sin eso, el servidor sirve los assets de la versión anterior y el cambio no
aparece, sin ningún error que lo diga.

### El cron migra pero NO siembra

Una base recién migrada se queda sin plantillas de correo, sin ajustes y sin la
biblioteca de medios indexada. Eso no da error: los correos automáticos dejan
de salir en silencio y la biblioteca sale vacía. **`php artisan dps:instalar`
debería estar en el cron de despliegue**, junto al `migrate`; es idempotente y
mientras no esté hay que acordarse de ejecutarlo a mano tras cada instalación
nueva.

### El cron toca la caché cada cinco minutos

Por lo tanto **nada que deba sobrevivir puede vivir en la caché**. Ya rompió
una vez el bloqueo por intentos fallidos, que llevaba ahí el contador: en local
funcionaba siempre —nadie limpia nada— y en el servidor se vaciaba solo. Ahora
ese contador se lleva en la tabla `access_logs`. Si hace falta guardar un
estado, va a la base.

### El otro cron, el del planificador

Además del de despliegue, el servidor necesita:

```cron
* * * * * cd ~/ong-laravel && php artisan schedule:run >> /dev/null 2>&1
```

**Sin esta línea no sale ni un correo.** Todos los mailables son `ShouldQueue`,
así que se encolan y esperan a que el planificador vacíe la cola. Es la primera
cosa que hay que comprobar cuando alguien diga que no le llega nada.

Es **una sola** entrada de cron para todo lo programado, que está declarado en
`routes/console.php`:

| Cuándo | Qué |
|---|---|
| cada minuto | `queue:work --stop-when-empty --max-time=50` — vacía la cola de correos y termina, para no dejar un proceso permanente en un hosting compartido |
| a las 09:00 | `dps:recordatorios` — el aviso a las personas inscritas los días previos |
| cada semana | `queue:prune-failed --hours=720` — tira los trabajos fallidos de más de un mes |

---

## 6. Los comandos propios

```bash
php artisan dps:instalar
```

Deja la instalación en pie: siembra ajustes por defecto, las plantillas de
correo, las regiones y comunas de Chile, las taxonomías, e indexa la biblioteca
de medios. **Es idempotente**: se puede ejecutar cuantas veces haga falta, no
duplica nada y no pisa lo que la ONG haya cambiado. No crea usuarios ni datos
de demostración.

```bash
php artisan dps:correo
php artisan dps:correo --enviar=alguien@ejemplo.cl
```

Diagnostica **toda la cadena del correo** y dice cuál es el eslabón roto:
configuración, transporte, conexión y autenticación reales contra el servidor
SMTP, estado de la cola y presencia de las plantillas. Con `--enviar` manda un
correo de verdad saltándose la cola. **Ejecutarlo en el servidor**, que es
donde importa: los tres fallos que tuvieron el correo parado una semana eran de
entorno y ninguno se veía desde el código.

```bash
php artisan dps:indexar-medios
php artisan dps:indexar-medios --limpiar
```

Mete en la biblioteca de medios las imágenes que ya venían con el proyecto, en
`public/img`. **No mueve ni un archivo**: sólo deja una fila por cada uno.
`--limpiar` quita las filas cuyo archivo ya no exista. Va incluido en
`dps:instalar`.

```bash
php artisan dps:recordatorios
```

Manda el recordatorio a las personas inscritas en las actividades que se acercan.
**No hace falta llamarlo a mano**: lo dispara el planificador cada día a las
09:00, y se protege él solo de mandar dos veces lo mismo. Está aquí por si hay
que forzarlo o comprobar qué haría.

Los cuatro comandos aceptan `--help`. `dps:instalar` además tiene `--seco`, que
dice qué falta sin escribir nada.

---

## 7. Decisiones que conviene conocer antes de tocar nada

Esta es la parte que más ahorra: cosas que parecen mejorables y no lo son.

### El HTML fuente es la referencia, y se replica uno a uno

El diseño no salió de una guía de estilos: salió de tres prototipos HTML
(`index.html`, `mi-cuenta.html`, `publicar-actividad.html`) que el cliente
aprobó. La regla del proyecto es replicarlos, no reinterpretarlos. Si algo no
está claro, se pregunta antes de inventarlo.

### No hay reset global de `box-sizing`, y es a propósito

Ningún HTML fuente lo tiene. Añadir `* { box-sizing: border-box }` descuadra el
sitio entero: restaba el relleno al ancho de cada contenedor y un paso del
wizard medía 860 px en vez de 924.

**El precio hay que conocerlo:** cualquier `max-width`, `height` o `min-height`
sobre un elemento con relleno cuenta sólo el contenido, así que el relleno se
suma por fuera y el tope no recorta. Ya ha mordido cuatro veces. Cuando haga
falta, se pone `box-sizing: border-box` **explícito y acotado a esa clase**, y
nunca se vuelve al reset global.

### Alpine.js, y sus dos trampas

La interactividad va con Alpine, no con un framework de componentes. Dos cosas
que han roto este proyecto varias veces:

- **`$el` es el elemento del manejador que se está ejecutando, no la raíz del
  componente.** Si el `x-on:` vive en un hijo, `$el` es ese hijo. Todo
  componente que necesite su raíz **la guarda en `init()`**, que es el único
  sitio donde `$el` sí lo es.
- **`:style` con una cadena reemplaza el atributo entero** en vez de
  fusionarlo, y se lleva por delante el `style` estático del mismo elemento.
  Para alternar estilos se usa `:class`.

### La validación vive en el servidor, y el navegador la lee del DOM

Los formularios largos avisan de lo que falta antes de enviarse, pero **no
tienen una segunda lista de reglas**. El navegador marca los campos leyendo
`data-obligatorio` del propio HTML y da por relleno cualquiera que tenga un
control con valor —lo que vale igual para un `<input>`, un `<select>` y un
grupo de chips—. Lo que no se puede comprobar desde ahí, como un formato o un
`unique`, no se finge: lo dice el servidor y el resumen se rellena con lo que
devuelva. Ver `resources/js/formularios.js`.

**Si se añade un campo obligatorio, hay que mirar si su regla lleva
`required_without` o `required_if`** y decirlo en el marcado
(`data-obligatorio-salvo`). Un formulario que exige más que el servidor frena
envíos que habrían valido.

### Los campos de fecha y hora son de texto a propósito

`type="date"` y `type="time"` **no dejan pegar**, y pegar la fecha desde otro
sitio es de lo más habitual aquí. Son campos de texto con máscara, con un
selector nativo al lado que **escribe en el campo de texto** en lugar de
sustituirlo. Lo que la persona escriba se interpreta en
`App\Support\FechaEscrita`, y su gemelo en el navegador —`campoFecha` y
`campoHora`— tiene que entender **exactamente lo mismo**.

### La sanitización usa `symfony/html-sanitizer` y su acción por defecto es `Block`

Nunca un filtro casero. Y el detalle importa: con `Drop` por defecto, pegar
desde Word borraba el párrafo entero, porque Word envuelve cada frase en un
`<span>`. `Block` quita la etiqueta y deja el texto; `Drop` se reserva para lo
peligroso.

### Autorización: dos capas, y una trampa

El **rol** lo decide el middleware `role:` de `routes/web.php`. La **propiedad**
—si este registro es tuyo— la deciden las policies de `app/Policies/`, que están
**registradas a mano** en `AppServiceProvider` y no por descubrimiento
automático: aquél va por convención de nombres, y renombrar una policy la
desactivaría en silencio dejándolo todo abierto.

**La trampa: un `FormRequest` valida ANTES de que corra el método del
controlador.** Una comprobación de permisos escrita dentro del método llega
tarde. Si la ruta recibe un `FormRequest`, **el permiso va en su `authorize()`**.

Y en las pantallas públicas se responde **404 y no 403**: un 403 confirmaría
que esa dirección existe.

### Los dos accesos están separados a propósito

`/admin/login` y `/mi-cuenta/login` son dos puertas, y cada una rechaza a la
cuenta de la otra; lo que separa los paneles es el middleware `EnsureRole`, que
comprueba el rol en **cada** petición leyéndolo de la base. Unificarlos
quitaría esa barrera. Cuando alguien se equivoca de puerta, el aviso lleva un
botón a la correcta con su correo puesto; esa pista **sólo aparece cuando la
contraseña ya es correcta**, porque si no, probar correos en el acceso de
administración diría cuáles son de administración.

Como el rol se lee en cada petición, **cambiárselo a alguien surte efecto de
inmediato**, sin que tenga que cerrar sesión.

### Los textos por defecto del home viven en el código

`App\Support\CatalogoHome` tiene el texto original de las 12 secciones. La base
guarda **sólo lo que se ha cambiado**, así que una sección sin fila se pinta con
su texto original, vaciar un campo desde el panel lo devuelve al original, y el
home se ve bien con la tabla recién migrada y sin sembrar. Para cambiar un texto
por defecto hay que tocar el catálogo y desplegar.

---

## 8. Cómo se comprueba que algo funciona

En `pruebas/` hay pruebas **de sistema**, en Node, que conducen la aplicación
como lo haría una persona. No son tests unitarios y no prueban el código:
prueban el sistema. Su README explica cada una.

La distinción no es teórica. Un bloque entero pasó tres revisiones automáticas
de código mientras en el servidor no salía un solo correo, porque el fallo era
de entorno y ningún análisis de código lo ve.

**Las que llevan «necesita Chrome» conducen un navegador de verdad**, y eso
tampoco es capricho: un script que lee el HTML que vuelve encuentra cualquier
aviso, porque no tiene pantalla ni scroll. Para saber si algo se ve, hay que
medir píxeles.

```bash
php artisan serve --host=127.0.0.1 --port=8123
node pruebas/humo.mjs            # las 26 pantallas cargan
```

No hay tests de PHPUnit todavía. SQLite en memoria ya está habilitado en
`phpunit.xml` para cuando se escriban.

---

## 9. Dónde mirar cuando algo falla

| Síntoma | Primero mira |
|---|---|
| No llega ningún correo | `php artisan dps:correo` **en el servidor**. Casi siempre es el cron del planificador. |
| Un cambio de CSS o JS no aparece | ¿Se corrió `npm run build` y se commiteó `public/build/`? |
| Plantillas de correo o biblioteca vacías | `php artisan dps:instalar`. |
| Un intento de acceso raro | `/admin/accesos`, que registra cada intento con su motivo. |
| Un cambio de estado de una actividad | La tabla `activity_status_logs`: guarda quién, cuándo y de qué estado a cuál. |
| Un correo que se envió o no | `/admin/correos`, con su estado y el reintento a mano. |

Los registros de la aplicación están en `storage/logs/laravel.log`.

**`APP_DEBUG` tiene que estar en `false` en producción.**

---

## 10. Bitácoras

En la raíz del repositorio hay un archivo `BITACORA-*.md` por jornada de
trabajo. No son documentación de referencia —esto lo es— pero cuentan **por
qué** se hizo cada cosa, con los fallos que salieron por el camino y cómo se
diagnosticaron. Cuando algo del código parezca arbitrario, suele estar
explicado ahí.
