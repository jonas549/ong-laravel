/*
 * Las cuentas con las que entran las pruebas.
 *
 * **Aquí no hay ninguna contraseña escrita**, y es a propósito: este archivo se
 * versiona. Salen del entorno o de `credenciales.local.mjs`, que no se versiona
 * y se crea copiando `credenciales.example.mjs`:
 *
 *   cd pruebas && cp credenciales.example.mjs credenciales.local.mjs
 *
 * Los valores del ejemplo son los del `UserSeeder` en una base de desarrollo
 * recién sembrada y **sólo sirven ahí**: en producción esas cuentas están
 * desactivadas y con otra contraseña.
 *
 * Para correr una prueba contra el servidor, por el entorno:
 *
 *   DPS_URL=https://el-sitio-en-produccion \
 *   DPS_ADMIN=... DPS_CLAVE_ADMIN=... node pruebas/loquesea.mjs
 */
const local = await import('./credenciales.local.mjs').catch(() => ({}));

const leer = (variable, clave) => process.env[variable] ?? local[clave] ?? '';

export const ADMIN = leer('DPS_ADMIN', 'ADMIN');
export const CLAVE_ADMIN = leer('DPS_CLAVE_ADMIN', 'CLAVE_ADMIN');

export const ORG = leer('DPS_ORG', 'ORG');
export const CLAVE_ORG = leer('DPS_CLAVE_ORG', 'CLAVE_ORG');

/*
 * Las cuentas que fabrican los escenarios (`datos-permisos.php` y compañía).
 * No las siembra nadie: las crean las propias pruebas, así que su contraseña
 * sólo vive en la máquina de quien las corre.
 */
export const ORG_A = leer('DPS_ORG_A', 'ORG_A');
export const ORG_B = leer('DPS_ORG_B', 'ORG_B');
export const CLAVE_FIXTURE = leer('DPS_CLAVE_FIXTURE', 'CLAVE_FIXTURE');

/*
 * Si falta algo se para aquí y se dice cómo arreglarlo. Sin esto, la prueba
 * intentaría entrar con la cadena vacía y fallaría veinte líneas más abajo
 * diciendo que la pantalla no carga, que es la peor forma de enterarse.
 */
const faltan = Object.entries({ ADMIN, CLAVE_ADMIN, ORG, CLAVE_ORG, ORG_A, ORG_B, CLAVE_FIXTURE })
  .filter(([, valor]) => ! valor)
  .map(([clave]) => clave);

if (faltan.length) {
  throw new Error(
    `Faltan credenciales de prueba (${faltan.join(', ')}).\n`
    + 'Copia pruebas/credenciales.example.mjs a pruebas/credenciales.local.mjs, '
    + 'o pásalas por el entorno (DPS_ADMIN, DPS_CLAVE_ADMIN, DPS_ORG, DPS_CLAVE_ORG, '
    + 'DPS_ORG_A, DPS_ORG_B, DPS_CLAVE_FIXTURE).',
  );
}

/** Lo que hay que pasarle a los escenarios en PHP, que leen del entorno. */
export const ENTORNO_FIXTURE = {
  DPS_ORG_A: ORG_A,
  DPS_ORG_B: ORG_B,
  DPS_CLAVE_FIXTURE: CLAVE_FIXTURE,
};
