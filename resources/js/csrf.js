/*
 * El token CSRF de la página, en un solo sitio.
 *
 * Estaba escrito dentro de home-editor.js. Cuando el reordenar de las tablas
 * del panel lo llamó desde panel.js, `token()` no existía ahí: la llamada
 * lanzaba dentro de un `try`, el `catch` la convertía en «no se pudo guardar el
 * orden» y no quedaba ni un error en la consola. Se guardaba nada y parecía un
 * problema del servidor.
 */
export const token = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
