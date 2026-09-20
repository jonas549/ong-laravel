/*
 * Las cuentas con las que entran las pruebas.
 *
 * Los valores por defecto son los del `UserSeeder` en una base de desarrollo
 * recién sembrada, y **sólo sirven ahí**: desde el 2026-09-20 las dos cuentas
 * sembradas están desactivadas en producción y con otra contraseña (C6). Lo
 * que estaba escrito en cuarenta archivos de este repositorio ya no abre nada
 * que no sea el portátil de quien lo clone.
 *
 * Para correr una prueba contra producción, las credenciales van por el
 * entorno y no por el archivo:
 *
 *   DPS_URL=https://ong.sandboxdelta.com \
 *   DPS_ADMIN=... DPS_CLAVE_ADMIN=... node pruebas/loquesea.mjs
 */
export const ADMIN = process.env.DPS_ADMIN ?? 'admin@ong-laravel.test';
export const CLAVE_ADMIN = process.env.DPS_CLAVE_ADMIN ?? 'admin1234';

export const ORG = process.env.DPS_ORG ?? 'organizador@ong-laravel.test';
export const CLAVE_ORG = process.env.DPS_CLAVE_ORG ?? 'organizador1234';
