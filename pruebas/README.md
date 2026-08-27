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
| `permisos.mjs` | Que un organizador **no** llegue a los datos de otro cambiando el número de la URL, y que ningún rol entre en el panel del otro. Sesenta segundos y 29 comprobaciones; el que hay que correr al añadir cualquier pantalla que reciba un id. |
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
