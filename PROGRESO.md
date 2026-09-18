# Progreso — tanda del 11/09

**Fecha:** 18/09/2026
**Producción:** `7aa3e8e` · https://ong.sandboxdelta.com
**Avance:** 20 de 36 puntos cerrados.

---

## HECHO HOY

Todo verificado en navegador real. «(prod)» = comprobado contra el servidor.

| # | Qué | Verificación |
|---|---|---|
| 1 | Ficha en `/activity/{id}/{slug}`, la vieja redirige 301 | (prod) 301 correcto, slug erróneo y `/activity/2` también; **QR real decodificado con jsQR**: sigue en `/evaluar/{slug}` |
| 2 | «No es necesario inscripción previa…» | local, los dos ramales (sin inscripción / cupos agotados) |
| 3 | Botón «Editar mi actividad» del correo, a esa actividad | local, mailable renderizado |
| 4 | El archivo subido sobrevive al rebote del formulario | (prod) aviso con nombre de archivo; local, ciclo completo: la organización nace con su logo |
| 5 | Formato online → dirección no obligatoria | (prod) navegador y servidor |
| 10 | Pregunta 2 de la encuesta, escala «Nada/Muy dispuesto(a)» | (prod) |
| 11 | Aviso de uso de la fotografía, antes de elegir archivo | (prod) |
| 12 | Autorización nueva con «Política de Privacidad» enlazada | (prod) pulsar el enlace no marca la casilla |
| 19 | Nombre de organización repetido, rechazado | (prod) contra `deltadigital.cl` real |
| 21 | «Ver registrados»: revisado, ya cumplía. No se rehízo | — |
| 23 | «Pendiente» fuera; «Cancelada» se mantiene; KPI del panel corregido | (prod) panel del organizador |
| 24 | Al cancelar, se dice a cuántas personas se avisó | local, los 4 casos incluido cero |
| 25 | Botón fijo del kit de difusión; vacío no pinta botón | (prod) |
| 26 | Umbral numérico de aprobación automática | (prod) Configuración → General |
| 28 | «Usar el mismo correo» rellena el campo | (prod) |
| 29 | Visor de contraseña | (prod) |
| 30 | «Selecciona hasta 5 opciones que correspondan» | (prod) |
| 31 | «https://» automático en web y red social | (prod) |
| 34 | Instrucción de colaboración | (prod) |
| 36 | ID de actividad y de organización a la vista | (prod) |

**Hallazgos fuera del encargo, ya corregidos:** `aprobacion_automatica` y `kit_difusion_url` se leían en el código pero no existían como fila (la ONG no podía tocarlos); el KPI «inscripciones confirmadas» era siempre cero en producción; `clave-admin.mjs` dejaba cambiada la contraseña del organizador y hacía fallar en falso a las suites posteriores.

**Pruebas nuevas:** `campos-formulario.mjs` (29), `ids-y-aprobacion.mjs` (13), `archivo-retenido.mjs` (9), `qr-produccion.mjs` (10). `encuesta-evaluacion` 67 → 74.

---

## PENDIENTE DE LA TANDA — 16 puntos

En el orden de prioridad del encargo.

**Panel del organizador y evaluaciones**
- **16 y 22** — Evaluaciones y fotos para el organizador, sólo de sus actividades (D1).
- **14** — Enlace a las fotografías en el Excel de evaluaciones.
- **15** — Botones para descargar fotos: todas y por actividad.
- **13** — Varias fotos por evaluación, máximo configurable, 3 por defecto (D6). *Necesita tabla aparte: hoy hay una sola columna `foto_path`.*

**Formulario y wizard**
- **7** — Selector de hora: hora en punto por defecto, saltos de 15 min (D3).
- **8** — Mover «¿Cuántos trabajadores participan como voluntarios?» al paso 3.
- **9** — Cerrar sesión mientras se crea una actividad.
- **32** — 🔴 **BLOQUEADO** por decisión abierta (ver abajo): choca con el ítem 6 de la Tanda B.
- **33** — 🟠 **PARCIAL** — falta decidir el comportamiento, no sólo el botón (ver decisiones abiertas).
- **17** — 🟠 **PARCIAL** — la estructura y el autocompletado contra las organizaciones actuales sí se pueden hacer (D7); la importación espera el archivo del cliente.
- **18** — Validación única de organización (D2). Se puede hacer.
- **20** — 🔴 **BLOQUEADO** — no se puede comprobar como está escrito: hoy la marquesina **no tiene logos**, son 11 pastillas de texto. Aparecerá al cargar el Excel.

**Home y correos**
- **27** — Duplicar secciones del home, copia exacta e independiente (D4).
- **35** — Correo con la encuesta el día posterior, configurable (D10).

**Al final**
- **6** — Photon + latitud/longitud, enlace de mapa con el punto exacto (D9).

---

## DECISIONES YA TOMADAS (no re-litigar)

- **D1** — El organizador ve evaluaciones y fotos de sus actividades; acotado estrictamente, fotos en disco privado. Sustituye a la decisión del 07/09.
- **D2** — No se borran ni fusionan duplicados existentes. Sólo se impiden los nuevos. Sin índice único en base de datos.
- **D3** — Hora por defecto: la siguiente hora en punto. Saltos de 15 minutos.
- **D4** — Duplicar sección = copia exacta, editable y reordenable de forma independiente.
- **D5** — No se construye el doble opt-in. Fuera «Pendiente», se mantiene «Cancelada», KPI corregido, columna intacta en base de datos.
- **D6** — Máximo de fotos configurable, 3 por defecto.
- **D7** — Estructura del listado histórico lista; se importa cuando llegue el archivo.
- **D8** — Botón del kit fijo, URL configurable, vacía no pinta botón.
- **D9** — Photon, con lat/lng guardadas. El campo sigue aceptando texto libre.
- **D10** — Encuesta el día posterior a la actividad, momento configurable.
- **D11** — Conservar el archivo subido cuando el formulario rebota.
- **D12** — ✅ Confirmado: la ONG ya puede apagar la aprobación automática desde el panel.
- **D13** — ✅ `clave-admin.mjs` restaura el estado al terminar.

---

## DECISIONES ABIERTAS

**1. Punto 32 contra el ítem 6 de la Tanda B. 🔴 Urgente — bloquea el 32.**
El punto 32 pide **bloquear el envío** si la imagen pesa más de 2 MB. El ítem 6 del Word (14 septiembre) pide lo contrario: **reducirla automáticamente** en vez de rechazarla.
Son estrategias opuestas ante la misma imagen. Hacer las dos es construir y tirar. *Recomendación: reducir automáticamente (ítem 6) y dejar el bloqueo sólo como último recurso si aun reducida no entra.*

**2. Credenciales de producción.** `admin@ong-laravel.test` / `admin1234` funcionan en el sitio en vivo y están en texto plano en `database/seeders/UserSeeder.php`. Hay que cambiarlas y decidir si esas dos cuentas siguen existiendo en producción.

**3. Punto 33.** Si alguien inicia sesión en el paso 2, ¿se conserva lo ya escrito o se empieza de nuevo con su organización rellena? Y el ingreso con código al correo es un mecanismo nuevo que hoy no existe: ¿se construye?

**4. Punto 13.** La autorización de uso de imagen es una sola casilla por evaluación. ¿Vale para todas las fotos de esa persona, o hace falta una por foto?

**5. Punto 5.** La dirección ya no se pide en online. ¿Región y comuna tampoco, o se mantienen para poder filtrar por territorio en `/actividades`?

**6. Tanda B.** ¿Entra entera, o sólo algunos grupos?

---

## BLOQUEADOS POR MATERIAL DEL CLIENTE

**1. Listado histórico de organizaciones (punto 17).**
Un CSV o Excel de una hoja, con cabecera, UTF-8:

| Columna | Obligatoria | Para qué |
|---|---|---|
| `nombre` | **Sí** | El nombre exacto, tal como debe verse |
| `alias` | No | Alternativas separadas por `;` — lo que hace que el autocompletado encuentre la ficha aunque se escriba distinto |
| `tipo` | No | Uno de los que ya usa el sitio |
| `logo_archivo` | No | El **nombre del archivo**, no una ruta |
| `sitio_web` | No | Con o sin `https://` |
| `categoria` | No | Auspician / Participan / Colaboran / Alianzas estratégicas / Somos parte de |

Más una carpeta con los logos: **SVG preferible**, si no PNG con fondo transparente, con el nombre exacto de `logo_archivo`. No hacen falta ni IDs ni orden.

**2. URL del kit de difusión (punto 25).** El botón ya está; el ajuste está vacío en Configuración → General. En cuanto se pegue el enlace, aparece.

**3. Material de diseño del grupo C.** Cabecera, pie y logos por separado para la plantilla de difusión. Además depende del tramo de procesamiento de imágenes del bloque J, que sigue sin hacerse.

**4. Número máximo de fotos (punto 13).** Se deja en 3; el cliente confirma después.

---

## TANDA B — 20 ítems, ~45-65 h (sin el grupo C)

**Grupo A — «Comentarios web 14 septiembre» (6) · ~10-14 h**
1. Mensaje predeterminado al compartir en WhatsApp/redes
2. Mensaje para quien se inscribe
3. Contador de días en el home *(el cliente lo sugiere para la etapa 2)*
4. Corregir el enlace «quiero ser voluntario» → Voluntariados Chile
5. Logo no obligatorio para el tipo de organizador «otros»
6. Reducir el peso de la imagen automáticamente al subirla

> **Choques:** el **6 choca de frente con el punto 32** (decisión abierta nº 1). El **5 choca con 17/18**. El 1 y el 2 tocan `compartir.blade.php`, ya modificado hoy por el punto 1 — sin conflicto, pero conviene hacerlos juntos.

**Grupo B — «Mobile» (9) · ~12-16 h**
7. Agrandar el recuadro naranja de «¿Cómo quieres participar hoy?»
8. Vista de calendario para elegir la fecha en móvil
9. Agrandar los títulos de los campos del formulario
10. Logo no obligatorio en móvil
11. Opción en el admin para subir el logo después
12. El logo del corazón del pie se ve achatado
13. Enlace de YouTube en el pie
14. Enlace en el logo COS
15. Crédito con enlace a Gabriel Ebensperger

> **Choques:** el **8 choca con el punto 7** y con la decisión del bloque K de que fecha y hora son `type="text"` a propósito (los nativos no dejan pegar). El **10 y el 11 son la misma decisión que el 5 del grupo A**, dicha tres veces. El **12 es fidelidad al HTML fuente**: mirar el original antes de tocarlo.

**Grupo C — «PENDIENTES COS», plantilla de difusión (3) · no estimable, bloqueado**
16. Generar la imagen de difusión por actividad
17. Enlace al kit *(ya resuelto: punto 25, falta la URL)*
18. Imagen «soy parte del Día del Patrimonio Social»

> **Bloqueado dos veces:** falta material de diseño y depende del procesamiento de imágenes del bloque J. Pregunta del cliente sin responder: ¿podrán los organizadores editar el texto del HTML que genera la imagen?

**Grupo D — sueltas (3) · ~3-5 h**
19. ¿Una actividad cancelada desde el admin queda en borradores? *(hoy queda «cancelada»; es pregunta, no tarea)*
20. Destacar más el botón «Ver actividad» en `/actividades` *(falta saber cuánto)*
21. Que al pulsar la imagen de una actividad se abra su ficha

---

## RIESGOS Y DEUDA

1. **Credenciales de prueba activas en producción.** `admin1234` y `organizador1234`, en texto plano en `database/seeders/UserSeeder.php`, funcionan hoy en el panel del sitio en vivo. Es el riesgo más alto abierto.
2. **Índice único de organizaciones, no aplicable.** Mientras existan las dos filas `deltadigital.cl` (**#2** j.gonzalez@deltadigital.cl, 0 actividades · **#4** jonasgym82@gmail.com, 1 actividad), una migración que lo añada se caería. La validación del formulario ya impide duplicados nuevos.
3. **Parecido sin resolver:** **#3 «jonas»** y **#7 «Fundación jonas»**. No son duplicado exacto, así que la validación no los detecta; probablemente son la misma entidad. Decidir con el cliente.
4. **`APP_DEBUG` debe estar en `false`** en producción.
5. Sin tests de PHPUnit. Las pruebas de sistema están en `pruebas/`.

---

## CÓMO RETOMAR — por dónde empezar mañana

1. **Responder la decisión nº 1** (punto 32 contra ítem 6 de la Tanda B). Bloquea el 32 y condiciona el grupo A.
2. **Puntos 16 y 22** — evaluaciones y fotos para el organizador (D1). Es el bloque más grande de lo que queda y no depende de nadie. Arrastra el 14 y el 15, que son la misma pantalla.
3. **Punto 13** — varias fotos por evaluación (D6). Necesita tabla aparte y migración; conviene hacerlo junto con 16/22, que tocan lo mismo.

**Nota de entorno:** `dps:instalar` **sí** corre en el cron de despliegue (comprobado el 18/09). Un ajuste nuevo va en `SettingsSeeder`, **no** en una migración.
