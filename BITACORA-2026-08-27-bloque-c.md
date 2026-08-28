# Bitácora — 2026-08-27

Bloque C, acotado. Jonas decidió mantener los dos roles fijos (`admin` y
`organizer`) y cerrar sólo lo que importa de verdad: que cada uno llegue
únicamente a lo suyo.

El resultado corto: **las rutas ya estaban bien y no hubo nada que arreglar
ahí**; los dos agujeros que aparecieron no eran comprobaciones que faltaran,
sino comprobaciones escritas en el sitio equivocado.

---

## 1. Qué se pidió y qué se hizo

| Punto del bloque | Estado |
|---|---|
| Middleware de autorización | Auditado. Sin cambios: ya estaba correcto |
| Policies por modelo | Nuevas: `ActivityPolicy`, `OrganizationPolicy`, `UserPolicy` |
| Auditoría de fugas por id | Hecha. **Dos agujeros reales, los dos cerrados** |
| Roles granulares · permisos por módulo · gestión de roles | **No aplica por ahora**, por decisión de Jonas |

---

## 2. La auditoría de rutas — punto 1

Se sacó el listado real con `php artisan route:list --json` y se clasificaron
las 105 rutas por lo que las protege, no por lo que parece protegerlas.

| Grupo | Rutas | Guardián |
|---|---|---|
| Panel de administración | 59 | `auth` + `role:admin`, las 59 |
| Mi cuenta (organizador) | 16 | `auth` + `role:organizer`, las 16 |
| Verificación de correo | 3 | `auth` + enlace firmado |
| Públicas | 27 | ninguno, y es correcto |

**No falta ni una.** Los dos formularios de acceso filtran además por rol
dentro del propio `Auth::attempt` (`['role' => …, 'is_active' => true]`), así
que un organizador ni siquiera consigue sesión desde `/admin/login`.

Comprobado ejecutándolo, no leyéndolo: ocho cruces de rol en `permisos.mjs`,
sección 5.

---

## 3. Las policies — punto 2

Antes, la comprobación de propiedad era un método privado `autorizar()` copiado
literalmente en dos controladores:

```php
abort_unless($activity->organization_id === $request->user()->organization?->id, 403, '…');
```

Estaba bien escrito. El problema no era ese código, era **que estuviera
repetido**: una pantalla nueva que recibiera una actividad por la URL nacía sin
comprobación y nada lo señalaba. Que es exactamente como nació el agujero de
§4.1.

Ahora la regla se escribe una vez y se pide por su nombre:

| Policy | Permisos | Quién la usa |
|---|---|---|
| `ActivityPolicy` | `view`, `update`, `submit`, `cancel`, `duplicate`, `manageParticipants` | `MyActivityController`, `ParticipantController`, `ActivityController`, `PublishController`, `UpdateActivityRequest` |
| `UserPolicy` | `updateSelf`, `changeRole`, `changePassword`, `toggleActive` | `Admin\UserController` |
| `OrganizationPolicy` | `view`, `update` | **nadie todavía** — ver abajo |

Tres decisiones dentro de esto:

**`before()` de administrador en dos de las tres, y en `UserPolicy` no.** El
admin modera todo el sitio, así que en actividades y organizaciones pasa por
encima. En `UserPolicy` no puede haberlo: sus tres reglas son precisamente
límites *a* un administrador sobre su propia cuenta —no quitarse el rol, no
desactivarse, no cambiarse la contraseña sin la anterior—, y un `before()` las
borraría las tres de golpe.

**Las inscripciones no tienen policy propia.** No se tocan nunca por su cuenta:
siempre se piden a través de su actividad (`$activity->registrations()`), así
que quien manda es la actividad y `manageParticipants` es su permiso. Una
policy aparte sería una segunda verdad que mantener.

**`OrganizationPolicy` no la usa nadie, y se escribe igual.** No existe todavía
una pantalla `/mi-cuenta/organizacion`. Se deja puesta a propósito: el agujero
de §4.1 nació de una pantalla añadida sin que hubiera un sitio evidente donde
preguntar de quién es esto.

**Registradas a mano** en `AppServiceProvider`, no por el descubrimiento
automático de Laravel. Ese descubrimiento va por convención de nombres: mover o
renombrar una policy la desactiva **sin decir nada** y todo queda abierto.
Escritas ahí, un nombre que no cuadre revienta al arrancar.

De paso, la clase base `Controller` recupera el trait `AuthorizesRequests`. En
Laravel 12 viene vacía, así que `$this->authorize()` no existía: una
comprobación escrita en un controlador nuevo habría muerto con un error de
método indefinido, y al ser en tiempo de ejecución, quizá no en la pantalla
donde se probó.

---

## 4. Los dos agujeros — punto 3

### 4.1 «Actividad enviada» era pública

`GET /publicar-actividad/{slug}/listo` no tenía `auth`, ni rol, ni comprobación
de propiedad. Cargaba la actividad con su organización y pintaba **nombre de la
organización, título, fecha y lugar** de cualquier ficha del sistema, incluidas
las que están en revisión y las que nadie ha publicado nunca.

Y el slug se deriva del título, así que tampoco había gran cosa que adivinar.

La ruta tiene que seguir siendo pública —el wizard aterriza ahí recién creada
la cuenta— así que se cierra por dentro: `abort_unless(Gate::allows('update',
$activity), 404)`. Después del wizard la persona ya tiene sesión iniciada, así
que su dueño pasa; el resto no.

**404 y no 403**, aquí y en la ficha pública: un 403 confirmaría que esa
dirección existe.

### 4.2 La edición autorizaba después de validar

Este es el que no se ve leyendo el código.

`MyActivityController::update()` empezaba con su `$this->authorize(...)`, en su
sitio y bien escrito. Pero el método recibe un `UpdateActivityRequest`, y **un
`FormRequest` valida antes de que el método del controlador llegue a
ejecutarse**. Así que un organizador que enviara el formulario contra la
actividad de otro recibía los errores de validación de una ficha ajena —un 302
de vuelta al formulario— y la línea que autorizaba no se ejecutaba jamás.

Es la misma trampa que ya está anotada en la bitácora del día 25 para el guard
del wizard, y volvió a morder en otro sitio.

El permiso se mudó a `UpdateActivityRequest::authorize()`, que sí corre antes
de las reglas, y devuelve `Gate::inspect(...)` en vez de un bool para no perder
el mensaje en castellano. La comprobación del controlador se retiró: repetirla
sería una segunda verdad, y la de allí llegaría tarde.

**Lo encontró `permisos.mjs`**, devolviendo 302 donde esperaba 403. Leyendo el
código habría pasado por bueno las veces que hiciera falta.

### 4.3 Lo que se auditó y ya estaba bien

Anotado para no volver a recorrerlo:

- **El perfil no recibe id por la URL.** `/mi-cuenta/perfil` y `/admin/perfil`
  trabajan siempre sobre `$request->user()`, así que la fuga clásica de cambiar
  el número no existe ahí. `UserPolicy::updateSelf` queda escrita para el día
  que alguien le ponga un id a esa ruta.
- **El cierre remoto de sesiones filtra por `user_id`** en las tres consultas
  de `SesionesActivas`. No se puede cerrar la sesión de otro.
- **Cancelar una inscripción va por token**, no por id: la capacidad es el
  token y no hay nada que enumerar.
- **Las fichas públicas comprueban su estado antes de pintarse**: actividad
  (`publicada` o de su organización), noticia (`activo` + `published_at`),
  página (`activo`).
- **El `{tipo}` del CRUD genérico del panel está en una lista blanca**, y todo
  ello detrás de `role:admin`.
- **Ningún formulario deja tocar el rol.** `PerfilController` guarda sólo las
  claves validadas, y el registro y el wizard fijan `organizer` en código.

---

## 5. Cómo se verificó

`pruebas/permisos.mjs`, nuevo en el repo. Siembra **dos organizadores de
distinta organización**, cada uno con una actividad sin publicar, entra de
verdad como cada uno y les cambia el número de la URL.

**29 comprobaciones, 29 en verde.**

| Sección | Qué |
|---|---|
| 1 | El organizador B pide las 9 direcciones de la actividad de A → 403 las nueve |
| 2 | …y sobre la suya propia sigue pudiendo → 200 |
| 3 | La ficha sin publicar de A: 404 sin sesión, 404 para B, 200 para A y para el admin |
| 4 | «Actividad enviada»: 404 sin sesión, 404 para B, 200 para su dueño |
| 5 | Los ocho cruces de rol entre los dos paneles |
| 6 | Los tres límites del admin sobre su propia cuenta |

Las escrituras van con su token CSRF de verdad: sin él volvería un 419, que no
diría nada sobre permisos.

También se corrieron, sin fallos: `humo.mjs` (26 pantallas), `menu.mjs` y
`clave-admin.mjs` — este último porque `Admin\UserController` cambió.

Al terminar: usuarios y actividades de prueba borrados, contraseña del
organizador restaurada.

---

## 6. Decisiones tomadas

### Aprobadas por Jonas

- **Dos roles fijos.** Nada de sistema granular, permisos por módulo ni gestión
  de roles como catálogo. Los tres puntos quedan en el backlog tachados y
  marcados «no aplica por ahora», no como deuda.

### Tomadas dentro del bloque, y anotadas

- **`before()` de admin en `ActivityPolicy` y `OrganizationPolicy`, y no en
  `UserPolicy`.** El porqué, en §3.
- **Las inscripciones no tienen policy propia**: se gobiernan desde su
  actividad.
- **`OrganizationPolicy` se escribe aunque todavía no la use nadie.**
- **Policies registradas a mano** y no por descubrimiento automático.
- **404 en vez de 403** en las dos pantallas públicas, para no confirmar que la
  dirección existe.
- **El permiso de edición vive en el `FormRequest`**, no en el controlador.

---

## 7. Lo que queda abierto de este bloque

**Los menús no ocultan lo que el usuario no puede ver.** Es lo único del QA
original del bloque que no se cierra. Hoy no hace falta: cada panel tiene su
propio árbol y ninguno enlaza al del otro, y quien se salga recibe un 403. Si
algún día comparten pantallas, habrá que filtrar `MenuPanel` por permiso.

---

## 8. Estado del entorno

Nada que desplegar con cuidado: **ninguna migración, ningún ajuste nuevo,
ningún cambio en CSS ni en JS**. No hace falta `npm run build`.

El único efecto visible en producción es que dos direcciones que antes
respondían ahora devuelven 404 a quien no es su dueño. Eso es el arreglo.

---

## 9. Próximos pasos

Sin cambios respecto a ayer, salvo que el bloque C ya no está pendiente:

1. **Añadir `dps:instalar` al cron de despliegue.** Sigue siendo el arreglo más
   barato de la lista y el que evita que el correo se rompa solo.
2. **Comprobar que existe el cron del scheduler.**
3. **`APP_DEBUG=false` en producción.**
4. **Credenciales de prueba en `UserSeeder.php`**, líneas 17 y 28.
5. **Bloque E — Home del panel** (6 tareas). Corto.
6. **Bloque F — Editor de contenido del home** (19 tareas). El más grande y el
   que le da autonomía a la ONG.
7. **2FA**, la única tarea abierta del bloque B.
