# Pruebas de verificación

Scripts que conducen la aplicación por HTTP como lo haría una persona: entran,
envían formularios y leen el HTML que vuelve. **No prueban el código, prueban el
sistema.** Están aquí porque esa distinción es la que costó una jornada entera:
el bloque A pasó tres pases de QA automático mientras en el servidor no salía un
solo correo.

Ninguno necesita dependencias: sólo Node 18+ y la aplicación levantada.

---

## Cómo correrlos

```bash
# 1. La base de datos y la aplicación
php artisan serve --host=127.0.0.1 --port=8123

# 2. Un servidor SMTP de verdad, si se van a probar correos
node pruebas/smtp-real.mjs          # escucha en 127.0.0.1:2526

# 3. El script que toque
node pruebas/humo.mjs
```

Dos variables opcionales, por si cambia el entorno:

| Variable | Por defecto |
|---|---|
| `DPS_URL` | `http://127.0.0.1:8123` |
| `DPS_MYSQL` | `C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe` |

**Los que llevan «necesita Chrome» conducen un navegador de verdad, y eso no es
un capricho.** Un script por HTTP lee el HTML que vuelve y encuentra en él
cualquier aviso, porque no tiene pantalla ni scroll: los 74 casos del bloque F
pasaban también con el menú roto, y el fallo que arregla `wizard-errores.mjs`
—un aviso correcto que quedaba fuera de la pantalla— es invisible por HTTP por
definición. Lo que hay que medir ahí son píxeles.

**`clave-admin.mjs` le cambia la contraseña al organizador y no se la devuelve.**
Cualquier script que corra después no podrá entrar con `organizador1234`, y lo
que se ve entonces no es «login roto» sino la mitad de las comprobaciones
fallando por sitios raros. Para volver al punto de partida:

```bash
php artisan db:seed --class=UserSeeder
```

Los scripts entran con las cuentas del `UserSeeder`
(`admin@ong-laravel.test` / `admin1234`), así que **son para local**, nunca
contra producción: varios borran filas y cambian contraseñas.

---

## Qué cubre cada uno

| Script | Qué comprueba |
|---|---|
| `humo.mjs` | Las 26 pantallas públicas y del panel cargan con 200, y el editor de plantillas tiene variables, vista previa y envío de prueba. Es el que hay que correr después de cualquier cambio. |
| `bloqueo.mjs` | El bloqueo por intentos, en cinco casos. Incluye **vaciar la caché a mitad de la tanda**, que es lo que lo rompía en producción. |
| `clave-admin.mjs` | Un admin le cambia la contraseña a un organizador: que la nueva sirva, que la anterior no, que quede en el registro con el autor, que salga el correo y que se cierren las sesiones. |
| `flujos.mjs` | Registro y «olvidé mi contraseña» de punta a punta. Deja los correos encolados; hay que correr `queue:work` después para ver si llegan. |
| `menu.mjs` | Que la ficha de usuario marque el nodo correcto del menú y pinte las migas. |
| `home-editor.mjs` | El editor de contenido del home: que lo publicado se vea en el sitio, que **vaciar un campo devuelva el texto original**, que siete ataques distintos no sobrevivan al guardado, y que el texto larguisimo, la palabra de 600 letras y lo pegado desde Word no rompan nada. Incluye los cinco fallos que encontró el testing en producción del 2026-08-27. |
| `panel-home.mjs` | Que cada número de la portada del panel coincida con su consulta en MySQL, y que **cambie cuando cambia la base**: publica una actividad, inscribe a alguien, lo cancela y lo borra, mirando la pantalla en cada paso. |
| `panel-vacio.php` | Que con la base **vacía** todo dé cero. Corre dentro de una transacción que se deshace: `php artisan tinker --execute="require base_path('pruebas/panel-vacio.php');"` |
| `permisos.mjs` | Que un organizador **no** llegue a los datos de otro cambiando el número de la URL, y que ningún rol entre en el panel del otro. Sesenta segundos y 29 comprobaciones; el que hay que correr al añadir cualquier pantalla que reciba un id. |
| `bloque-g.mjs` | los once CRUD del bloque G: crear, editar, esconder, borrar en blando, papelera, restaurar, y el reflejo en el home (**necesita Chrome**) |
| `bloque-g2.mjs` | filtros, orden, paginación, acciones masivas, reordenar arrastrando y exportar (**necesita Chrome**) |
| `bloque-j.mjs` | la biblioteca de medios y el selector: subir, filtrar, elegir desde un formulario, editar, reemplazar y borrar (**necesita Chrome**) |
| `wizard-errores.mjs` | que el usuario **vea** lo que falta, en el wizard, en el editor de actividades de mi-cuenta y en el formulario de inscripción: el resumen de arriba, el salto al campo, la marca «Obligatorio» de los chips, la máscara y el calendario del campo de fecha, y que lo obligatorio aquí sea exactamente lo obligatorio en el servidor (**necesita Chrome**) |
| `login-puertas.mjs` | los dos accesos: que quien se equivoca de puerta vea el botón que lleva a la buena con su correo puesto, que la pista NO salga sin la contraseña correcta, que equivocarse no bloquee, y qué pasa al cambiarle el rol a alguien con la sesión abierta (**necesita Chrome**) |
| `moderacion-ajustes.mjs` | el circuito «pedir ajustes → corregir → reenviar»: que guardar no mueva el estado y que el botón lo devuelva directo a revisión, sin paso intermedio (**necesita Chrome**) |
| `calendario-y-aprobacion.mjs` | el `.ics` y el enlace de Google de los correos —incluidas las líneas de 75 octetos y las tildes al plegarlas—, y la aprobación automática con sus dos interruptores, el «ajustes» que la pausa y la marca que deja para poder repasarla (**necesita Chrome**) |
| `smtp-real.mjs` | **No es una prueba, es un servidor.** SMTP mínimo pero de verdad: habla el protocolo, exige `AUTH LOGIN` y escribe en `buzon.jsonl` lo que recibe. |

---

## Por qué un SMTP propio y no Mailpit

Porque Mailpit acepta cualquier cosa sin autenticar, y dos de los tres fallos
del día 26 estaban justo ahí: en la diferencia entre «el mailer terminó sin
excepción» y «un servidor de correo aceptó el mensaje». `smtp-real.mjs` exige
autenticación y deja constancia de lo que entra, así que si el correo no llegó,
no llegó.

Para probar contra el servidor de producción, mejor desde el propio servidor:

```bash
php artisan dps:correo --enviar=tu@correo.cl
```

---

## Después de correrlos

Varios dejan rastro: usuarios de prueba, filas en `access_logs`, correos en la
cola. Para volver al punto de partida:

```bash
php artisan dps:instalar        # replantar lo que falte
php artisan queue:flush         # tirar los trabajos fallidos
```

`home-editor.mjs` publica contenido de prueba en las secciones del home. Para
dejarlas como estaban —y que el home vuelva a los textos del diseño—:

```sql
DELETE FROM home_section_versions;
UPDATE home_sections SET contenido=NULL, borrador=NULL, borrador_at=NULL, borrador_por=NULL, activo=1, orden=id;
```

`permisos.mjs` deja dos organizadores de prueba con su organización y su
actividad. Para borrarlos:

```bash
php artisan tinker
>>> Activity::whereIn('slug', ['actividad-de-prueba-a', 'actividad-de-prueba-b'])->forceDelete();
>>> Organization::whereIn('slug', ['organizacion-prueba-a', 'organizacion-prueba-b'])->delete();
>>> User::whereIn('email', ['org-a@prueba.test', 'org-b@prueba.test'])->delete();
```

Y `clave-admin.mjs` le cambia la contraseña al organizador sembrado; para
dejarla como estaba:

```bash
>>> User::where('email', 'organizador@ong-laravel.test')->first()->forceFill(['password' => 'organizador1234'])->save();
```

`buzon.jsonl` se puede borrar sin más.

---

## Lo que estos scripts NO cubren

Conducen la aplicación por HTTP, así que ven lo que responde el servidor pero no
lo que hace el navegador. **Tres de los cinco fallos del testing en producción
del bloque F vivían justo ahí**: el autoguardado que nunca llamaba al servidor,
el arrastre que mandaba el cuerpo vacío y una palabra larga que desbordaba su
caja —con la página sin desbordar, así que ni siquiera se veía mirando el ancho
total—.

Para eso hace falta un navegador de verdad. **Dos de los scripts de aquí ya lo
usan** —`bloque-g.mjs` y `bloque-g2.mjs`, los del bloque G—, así que la parte de
«habría que montarlo» ya está montada.

`puppeteer-core` no se versiona: es una dependencia grande y sólo hace falta
para esos dos. Se instala una vez, dentro de esta carpeta:

```bash
cd pruebas
npm install puppeteer-core     # queda fuera de git, ver .gitignore
node bloque-g.mjs              # 112 comprobaciones
node bloque-g2.mjs             # 37
node bloque-j.mjs              # 49
```

Apuntan al Chrome instalado (`C:/Program Files/Google/Chrome/Application/chrome.exe`).
Las capturas van al temporal del sistema; con `DPS_SALIDA` se manda a otra parte.

Los demás scripts no necesitan nada: conducen la aplicación por HTTP.

Lo que conviene comprobar ahí, y no aquí:

- Que una petición sale de verdad (`page.on('request')`), no sólo que el endpoint
  responde bien cuando se le llama a mano.
- Que ningún **elemento** se sale de su contenedor, midiendo cada uno contra su
  padre. Que la página no desborde no basta.
- El arrastrar y soltar, con `page.setDragInterception(true)` y
  `elemento.dragAndDrop(destino)`: son eventos de ratón reales.
- Las animaciones, capturando los valores intermedios con un `MutationObserver`.
- **Que una regla de CSS no haya alcanzado a la interfaz.** Un
  `overflow-wrap: anywhere` pensado para el contenido editable se puso en el
  `<body>` y empezó a partir los enlaces del menú: «Actividade s»,
  «Voluntariad o». Los 74 casos por HTTP pasaban igual, porque el HTML era
  correcto y lo que fallaba era cómo se pintaba.

  Dos comprobaciones lo cazan. Una, leer el estilo calculado de cada elemento de
  interfaz —menú, migas, botones, chips, cabeceras de tabla— y exigir que
  ninguno tenga la regla. Y dos, la directa: contar las líneas que ocupa el texto
  con `Range.getClientRects()`, que devuelve un rectángulo por línea. Si una
  palabra suelta ocupa dos, se partió.

  Ojo con medir la altura contra el interlineado en vez de usar `Range`: da
  falso positivo en cualquier enlace con relleno.
