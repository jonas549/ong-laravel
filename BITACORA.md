# Bitácora — Día del Patrimonio Social

Registro de trabajo del proyecto `ong-laravel`.

---

## 2026-08-24 — Montaje del entorno y construcción completa

Jornada inicial: se partió sin PHP instalado y se terminó con la aplicación
funcionando en local y publicada en GitHub.

### 1. Entorno local

Estado de partida: solo Git 2.51.0, Node v22.20.0 y npm 10.9.3. **PHP y
Composer no estaban instalados.**

- Laragon 8.7.0 instalado vía winget. El ID `Laragon.Laragon` no existe; el
  paquete oficial es **`LeNgocKhoa.Laragon`**.
- Laragon trae PHP 8.3.33, pero producción corre 8.4.24. Se descargó
  **PHP 8.4.24 Thread Safe VS17 x64** desde windows.php.net y se verificó el
  SHA256 contra el checksum oficial (`7b57fc98…3174`).
- Se conservó la carpeta de PHP 8.3 como respaldo: ambas versiones conviven
  en `C:\laragon\bin\php\` y se alternan desde el menú de Laragon.
- `php.ini` creado desde `php.ini-development` con 10 extensiones activadas.
  `bcmath`, `ctype`, `tokenizer` y `xml` **no llevan línea**: en Windows
  vienen compiladas en el core.
- PATH de usuario: se agregaron **solo** PHP y Composer, y al final. El
  "Add Laragon to PATH" del menú también agrega su propio Node y habría
  tapado el Node 22.20.0 del sistema.

**Bloqueo del día:** `laragon.exe start` abre la ventana pero no arranca los
servicios. Laragon nunca había hecho su inicialización de primera vez —
`C:\laragon\data\` vacío y `httpd.conf` sin incluir sus propias configs. Se
resolvió con un clic manual en *Start All*, que inicializa el data dir de
MySQL, reescribe `httpd.conf` y genera los vhosts.

**Duda que se verificó en vez de asumir:** el Apache de Laragon es un build
VS18 y PHP 8.4 es VS17. Se levantó Apache en un puerto aislado sirviendo un
`.php` real: funciona (`SAPI=apache2handler`). De paso apareció que `curl` e
`intl` no cargaban bajo Apache si la carpeta de PHP no está en el PATH del
proceso — Laragon lo resuelve solo agregando `LoadFile` para
`libcrypto`/`libssl` en `mod_php.conf`.

### 2. Análisis del prototipo

Fuente: `F:\Descargas\Proyecto ONG\ORG-solidarias-main` (3 HTML, 1.925 líneas).

Hallazgos que cambiaron el plan:

- **No era HTML estático.** `support.js` (66 KB) es un runtime React que
  compila plantillas `<x-dc>` en el navegador y descarga React 18, ReactDOM y
  **Babel standalone desde unpkg en cada carga**. Inviable en producción.
- Su sintaxis mapea casi 1:1 contra Blade: `<sc-for>` → `@foreach`
  (28 usos), `<sc-if>` → `@if` (34 usos), `{{ }}` idéntico.
- **La carpeta `_ds` se descartó.** Es un design system "Industry" (azul
  acero, Barlow, esquinas cuadradas) de otro proyecto. Ningún HTML lo
  referencia y el diseño real es naranja `#e57200` con Raleway/Inter.
- Los catálogos estaban hardcodeados en el JS. `CARACS` tenía **dos
  definiciones distintas**: 15 opciones en `mi-cuenta.html` y 5 en
  `publicar-actividad.html`. Se unificó en la de 15.

### 3. Construcción

| Bloque | Resultado |
|---|---|
| Migraciones | 19 del dominio + las 3 de Laravel |
| Seeders | 16 regiones, 346 comunas, 45 términos, contenido de ejemplo |
| Modelos | 18, con scopes y accessors |
| Controladores | 7 públicos, 3 de cuenta, 10 de admin |
| Vistas Blade | Home en 12 secciones separadas, panel completo |
| Rutas | 57 |

Decisiones tomadas sobre la marcha:

- **Tailwind fuera del pipeline.** Laravel 12 lo trae, pero el diseño es CSS
  propio sin una sola utilidad Tailwind. El CSS final pesa 11.6 KB (3.1 gzip).
- **IntersectionObserver en vez de polling.** El prototipo corría
  `setInterval` cada 180 ms de forma permanente para detectar scroll.
- **Assets normalizados a minúsculas.** No solo se quitó el espacio de
  `Sodimac horizontalalta.jpg`: los 39 pasaron a minúsculas (21 renombrados).
  Windows no distingue mayúsculas, BanaHosting sí — una referencia mal
  capitalizada funcionaría en local y daría 404 en producción.
- **Taxonomías en una sola tabla agrupada**, para que agregar un catálogo no
  requiera migración.

### 4. Verificación

Todo probado con peticiones HTTP reales, no solo "compila":

- 7 rutas públicas y 23 de admin: 200
- Guardias sin sesión: 302 al login correcto en los 4 casos
- Wizard completo por POST: crea usuario + organización + actividad
- Moderación: cambia estado, escribe historial y **el correo llega a Mailpit**
- Botón de correo de prueba: enviado, recibido y **registrado en el log**
- Las 30 imágenes del home existen y responden 200

**Bug real encontrado y corregido:** `/admin` sin sesión devolvía **500** en
vez de redirigir. El middleware `auth` busca una ruta llamada `login` y las
del proyecto son `admin.login` y `account.login`. Resuelto con
`redirectGuestsTo()` discriminando por prefijo de URL.

**Falsa alarma investigada:** el wizard parecía rechazar `org_tipo` con
acentos. No era de la aplicación — era el comando de prueba (Git Bash
re-codificando la `ó` en `--data-urlencode`). Confirmado enviando `%C3%B3`.

### 5. Publicación

- `git init`, rama `main`, 2 commits
- Se quitó `/public/build` del `.gitignore`: el cron del servidor hace
  `git pull` pero **no compila**, así que los assets van versionados
- Push a `github.com/jonas549/ong-laravel`: 227 archivos, 56.5 MB
  (55.7 MB son las imágenes de `public/img`)
- Verificado contra el árbol remoto: `public/build/` incluido, `.env` fuera

---

## Pendientes

Ordenados por urgencia.

1. **Edición de actividades.** El organizador puede ver, enviar a revisión,
   cancelar y gestionar inscritos, pero **no editar**. Cuando el admin pide
   ajustes, hoy no hay dónde hacerlos. Es el hueco más grande.
2. **Credenciales de prueba en el repo.** `database/seeders/UserSeeder.php`
   líneas 17 y 28, en texto plano. No correr `db:seed` en producción sin
   cambiarlas.
3. **Recuperación de contraseña.** Ninguno de los dos logins la tiene. Si el
   equipo pierde el acceso al panel, no hay salida por interfaz.
4. **Subida de archivos.** Logos e imágenes se guardan como ruta de texto.
   Falta el `<input type="file">` con las validaciones del prototipo
   (máx. 500 KB logo, 2 MB portada).
5. **Correo de confirmación de inscripción.** `emails/registration/` está
   creada y vacía.
6. **Selects región→comuna sin encadenar** en el wizard (346 opciones en un
   `<select>` con `optgroup`).
7. **Sin tests automatizados.** SQLite ya está habilitado para tests en
   memoria.
8. **Diseño del dashboard admin** pendiente de definición.

---

## Datos de referencia

**Entorno local**

```
Proyecto     C:\laragon\www\ong-laravel
URL          http://ong-laravel.test
Fuente HTML  F:\Descargas\Proyecto ONG\ORG-solidarias-main
PHP          8.4.24 (Thread Safe VS17 x64)
Composer     2.10.2
MySQL        8.4.3 — base ong_laravel (utf8mb4_unicode_ci)
Mailpit      SMTP 1025 · bandeja http://127.0.0.1:8025
```

**Credenciales de desarrollo** (solo local — cambiar antes de producción)

```
admin@ong-laravel.test        admin1234          /admin/login
organizador@ong-laravel.test  organizador1234    /mi-cuenta/login
```

**Producción**

```
Servidor   BanaHosting cPanel
Repo       ~/ong-laravel (fuera del docroot)
Docroot    ~/ong-laravel/public
Stack      PHP 8.4.24 · Composer 2.10.2 · Git 2.48.2 · MySQL
Cron       cada 5 min: git pull + composer install + migrate + cache
```

**Al desplegar:** `APP_KEY` nueva en el servidor (`php artisan key:generate`).
Ojo: la contraseña SMTP del panel se cifra con esa clave, así que cambiarla
después obliga a volver a escribirla. `APP_DEBUG=false` y, con HTTPS activo,
`SESSION_SECURE_COOKIE=true`.

**Recordar al tocar CSS o JS:** `npm run build` y commitear `public/build/`,
o el servidor desplegará assets viejos.
