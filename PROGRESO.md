# Progreso — tercera tanda

**Fecha:** 20/09/2026
**Producción:** https://ong.sandboxdelta.com
**Avance:** los 20 puntos de función cerrados (P1–P20). P21 es seguridad: se
hizo lo que estaba en el código y queda pendiente una decisión de Jonas antes
de tocar ninguna cuenta de producción.

---

## LO QUE SE HIZO

Todo verificado en Chrome de verdad. «(prod)» = comprobado además contra el
servidor, después de desplegar.

| # | Qué | Verificación |
|---|---|---|
| P1 | El ID, primera columna de las 15 tablas del panel y de las 9 exportaciones | `ids-panel.mjs` (51), `ids-exportaciones.mjs` (7) · **(prod)** 53 + 7 |
| P2 | Guardar una actividad en «ajustes» la devuelve a revisión, sin botón aparte | `hilo-moderacion.mjs` (22), `moderacion-ajustes.mjs` (12) |
| P3 | El organizador acompaña la corrección con un mensaje; los dos lados ven el mismo hilo | idem · **(prod)** el hilo se pinta y el CSS llegó |
| P4 | La ONG recibe correo al volver una actividad, más alerta, pestaña e insignia | idem, con SMTP real |
| P5 | El organizador ve evaluaciones y fotos de SUS actividades | `evaluaciones-fotos.mjs` (28) · **(prod)** |
| P6 | Varias fotos por evaluación, máximo configurable, 3 por defecto | idem · **(prod)** |
| P7 | Enlaces a las fotografías en el Excel de evaluaciones | idem · **(prod)** |
| P8 | Descarga en zip **respetando los filtros** de la pantalla | idem · **(prod)** zip real de 628 KB |
| P9 | Buscador de organizaciones en el paso 3, distinguiendo libres de tomadas | `organizaciones-wizard.mjs` (27) · **(prod)** |
| P10 | Reclamar una del listado: sólo contraseña, sin duplicar la organización | idem |
| P11 | Correo ya registrado: lo dice con las dos salidas junto al campo | idem |
| P12 | La marquesina sale de la tabla de organizaciones y no repite | `marquesina.mjs` (8) · **(prod)** 5 organizaciones, ninguna repetida |
| P13 | Selector de hora: sólo horas en punto con AM/PM, sin proponer la actual | `hora-y-campos.mjs` (24) · **(prod)** |
| P14 | Entrar a mitad del wizard **sin perder lo escrito** | `acceso-wizard.mjs` (28) · **(prod)** |
| P15 | La pregunta de los voluntarios, al paso de la actividad | `hora-y-campos.mjs` · **(prod)** |
| P16 | Direcciones con Photon, punto guardado y mapa apuntando a él | `direcciones-photon.mjs` (22) · **(prod)** |
| P17 | Fuera la columna «Estado» de los inscritos del organizador | `hora-y-campos.mjs` · **(prod)** |
| P19 | Las imágenes se reducen en el navegador; si no caben, se avisa y se corta | `peso-imagenes.mjs` (18) |
| P18 | Duplicar una sección del home: copia exacta, editable y movible aparte | `duplicar-seccion.mjs` (21) |
| P20 | Correo con la encuesta tras la actividad, momento configurable | `invitacion-evaluacion.mjs` (12) · **(prod)** ajuste y plantilla |

Repaso final contra producción: `tanda-produccion.mjs`, 25 de 25.

---

## P18 — CÓMO SE RESOLVIÓ DUPLICAR UNA SECCIÓN

Las secciones del home **no son filas de un CRUD**. Cada una existe porque
`App\Support\CatalogoHome` la define, y su clave es la que decide qué parcial
de Blade la pinta: `hero`, `participar`, `cifras`. La tabla `home_sections`
sólo guarda lo que se ha cambiado respecto del HTML fuente.

Así que una copia no podía ser una fila más con una clave inventada: ningún
parcial la conocería. Lo que se hizo es **derivar la clave**: `cifras--2`
resuelve a `cifras` para todo lo que decide el catálogo —el parcial, los
campos, las reglas, los textos por defecto— y es una fila propia para todo lo
que decide el contenido.

De ahí salen las cuatro decisiones concretas:

- **La copia nace con el contenido ya materializado**, no vacía. Si naciera
  vacía se pintaría igual que la original por casualidad —las dos caerían al
  texto del catálogo— y el día que alguien cambiara un valor por defecto
  cambiarían las dos. Lo que se ve es lo que se guarda.
- **El separador es `--` y no `-`**, porque hay claves con guion
  (`somos-parte`) y partir por el primero dejaría la copia apuntando a
  `somos`.
- **Las ancladas no se duplican.** El hero y «¿Cómo participar?» están cosidos
  por un margen negativo —la segunda se monta 96 px sobre la primera— así que
  una copia de cualquiera se pondría encima de la otra. El botón no se pinta y
  el servidor responde 403 si se pide a mano.
- **Una copia se puede borrar; las trece del catálogo no.** Ésas se esconden.
  Sin el borrado, duplicar sería una puerta de una sola dirección y el cliente
  se quedaría con una sección de más que sólo podría ocultar.

Un efecto que había que atajar: los parciales llevan un `id` fijo —`ediciones`,
`noticias`— para que el menú salte a ellos, y duplicar la sección duplicaba el
identificador. Dos elementos con el mismo `id` es HTML inválido y rompe
cualquier `getElementById` del sitio. La original conserva el suyo intacto
—los enlaces de fuera tienen que seguir llegando— y la copia lleva sufijo:
`ediciones-2`.

---

## P21 — SEGURIDAD: HECHO LO QUE NO NECESITABA PERMISO

En el código, dos cosas, y la segunda era la grave:

- `UserSeeder` deja de traer contraseñas en texto plano: salen del `.env`
  (`DPS_CLAVE_ADMIN`, `DPS_CLAVE_ORGANIZADOR`) o se generan al azar y se
  imprimen una sola vez, al crear la cuenta.
- **Deja de pisar la contraseña de una cuenta que ya existe.** Un
  `php artisan db:seed --force` en el servidor —que está en la lista de
  comandos de despliegue manual de la documentación— devolvía la contraseña
  del administrador de producción a la del repositorio, en silencio y por
  mucho que alguien la hubiera cambiado antes. Ahora la contraseña sólo se
  escribe al CREAR la cuenta.

**Ninguna cuenta de producción se ha tocado.** Las credenciales propuestas van
por otro canal, nunca en el repositorio.

---

## LO QUE QUEDA ABIERTO

- **Los logos del Excel del cliente.** El importador está listo
  (`dps:importar-organizaciones`: lee .xlsx y .csv, detecta el separador,
  idempotente, con `--simular` y `--ejemplo`) y el circuito entero probado con
  datos de prueba. Falta el archivo.
- **Las tres plantillas de moderación** —recibida, necesita ajustes,
  cancelada— siguen siendo vistas Blade fijas y no editables. Es la «opción C»
  de la tanda anterior, ~6 h.
- **`activity_evaluations.foto_path`** quedó sin uso pero no se borró: quitarla
  es una migración destructiva sobre datos de producción y el único motivo
  sería la limpieza.
- Lo que ya venía de antes: el bloque I (Configuración), el procesamiento de
  imágenes del bloque J, y los pendientes 6 a 12 de la lista vieja.

---

## DECISIONES DE ESTA TANDA (no re-litigar)

- **P19:** se reduce automáticamente en el navegador; el aviso con bloqueo
  queda para cuando eso no baste. Era lo que pedía el ticket frente a lo que
  pedía el Word, y se eligió reducir: rechazar una foto de teléfono por pesar
  cuatro megas es mandar a alguien a buscar un editor de imágenes.
- **P9 y P10:** `organizations.user_id` admite nulos. Nulo significa «está en
  el listado, sin reclamar». Es lo que permite importar las ~180 sin
  inventarle a cada una un usuario con un correo falso.
- **P12:** la segunda pasada de la marquesina no se quita: la animación
  desplaza -50% y sin ella el bucle da un salto. Lo que se quitó fueron los
  nombres duplicados de verdad, que eran los dos «deltadigital.cl».
- **P20:** hay una tercera opción, «no enviar», además de mismo día y día
  siguiente. El QR del cartel sigue funcionando, así que apagar el correo no
  apaga la evaluación.
- **P6:** una sola autorización por envío, no una por foto. El consentimiento
  se firma una vez y sobre lo que se manda.
- **P18:** la copia se numera desde la base (`cifras--2`, `cifras--3`) y nunca
  desde otra copia. Y las ancladas no se duplican.

---

## LOS TRES FALLOS QUE APARECIERON TRABAJANDO

Ninguno de los tres se veía leyendo el código; los tres los encontró una
prueba en Chrome.

1. **El 419 mudo de P14.** Entrar a mitad del wizard regenera la sesión y con
   ella el token CSRF. El formulario que la persona tenía delante se pintó con
   el viejo, así que al enviarlo perdía todo lo escrito — exactamente lo que
   el punto venía a evitar. Lo encontró `acceso-wizard.mjs` al publicar de
   verdad después de entrar.
2. **`$el` otra vez.** En el selector de fotos, «Quitar» no vaciaba el campo
   porque leía el input desde `$el` dentro del manejador de un botón. La
   miniatura desaparecía, el usuario creía haberla quitado, y el archivo se
   enviaba igual. Es la quinta vez que esta trampa muerde en este proyecto.
3. **El aviso que se borraba solo.** Al rechazar un archivo que no era imagen,
   `quitar()` limpiaba el aviso que se acababa de poner: el campo se vaciaba
   sin decir por qué.

Y uno que estaba desde el 18/09 sin que nadie lo notara:
`wizard-errores.mjs` no probaba el formulario de inscripción, porque buscaba
la ficha en `/actividades/{slug}` y ésa pasó a ser una redirección. Se saltaba
el bloque entero en silencio.
