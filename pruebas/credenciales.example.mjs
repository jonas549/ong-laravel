/*
 * Copia este archivo a `credenciales.local.mjs` y pon ahí tus contraseñas:
 *
 *   cd pruebas && cp credenciales.example.mjs credenciales.local.mjs
 *
 * `credenciales.local.mjs` no se versiona. **Aquí no va ninguna contraseña**,
 * que este archivo sí se versiona.
 *
 * Las dos primeras cuentas son las que siembra el `UserSeeder`. Su contraseña
 * la eliges tú: si pones `DPS_CLAVE_ADMIN` y `DPS_CLAVE_ORGANIZADOR` en el
 * `.env` antes de sembrar, serán ésas; si no, el seeder genera una al azar y la
 * imprime una sola vez, al crearlas.
 *
 * Las dos últimas no las siembra nadie: las crean los escenarios de `pruebas/`
 * —`datos-permisos.php` y compañía— con la contraseña que pongas aquí, así que
 * vale cualquiera.
 *
 * Contra un servidor de verdad, nada de esto: las credenciales van por el
 * entorno (DPS_ADMIN, DPS_CLAVE_ADMIN, DPS_ORG, DPS_CLAVE_ORG).
 */
export const ADMIN = 'admin@ong-laravel.test';
export const CLAVE_ADMIN = 'la-que-pusiste-en-DPS_CLAVE_ADMIN';

export const ORG = 'organizador@ong-laravel.test';
export const CLAVE_ORG = 'la-que-pusiste-en-DPS_CLAVE_ORGANIZADOR';

export const ORG_A = 'org-a@prueba.test';
export const ORG_B = 'org-b@prueba.test';
export const CLAVE_FIXTURE = 'la-que-quieras-para-las-cuentas-de-prueba';
