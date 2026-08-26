// Servidor SMTP mínimo pero de verdad: habla el protocolo, exige AUTH LOGIN
// y guarda lo que recibe. Sirve para comprobar el circuito entero sin Mailpit.
import net from 'node:net';
import fs from 'node:fs';

const PUERTO = 2526;
const USUARIO = 'noreply@ong.local';
const CLAVE = 'clave-de-prueba';
const BUZON = process.env.DPS_BUZON ?? new URL('./buzon.jsonl', import.meta.url).pathname.replace(/^\//, '');

net.createServer((sock) => {
  let estado = 'inicio', datos = '', enData = false, esperandoAuth = null;
  const sobre = { from: null, to: [], at: new Date().toISOString() };
  const responder = (t) => sock.write(t + '\r\n');

  responder('220 smtp-de-prueba ESMTP listo');

  sock.on('data', (buf) => {
    for (const linea of buf.toString('utf8').split('\r\n')) {
      if (enData) {
        if (linea === '.') {
          enData = false;
          const asunto = (datos.match(/^Subject:\s*(.*)$/mi) || [])[1] ?? '';
          const de = (datos.match(/^From:\s*(.*)$/mi) || [])[1] ?? '';
          fs.appendFileSync(BUZON, JSON.stringify({ ...sobre, asunto, de, bytes: datos.length }) + '\n');
          console.log(`  [buzón] de=${sobre.from} para=${sobre.to.join(',')} asunto=${asunto}`);
          responder('250 2.0.0 Aceptado');
          datos = '';
        } else { datos += linea + '\n'; }
        continue;
      }
      if (!linea) continue;
      const orden = linea.split(' ')[0].toUpperCase();

      if (esperandoAuth === 'usuario') {
        const u = Buffer.from(linea, 'base64').toString();
        if (u !== USUARIO) { esperandoAuth = null; responder('535 5.7.8 Usuario desconocido'); continue; }
        esperandoAuth = 'clave'; responder('334 UGFzc3dvcmQ6'); continue;
      }
      if (esperandoAuth === 'clave') {
        const c = Buffer.from(linea, 'base64').toString();
        esperandoAuth = null;
        if (c !== CLAVE) { responder('535 5.7.8 Autenticación fallida'); continue; }
        estado = 'auth'; responder('235 2.7.0 Autenticado'); continue;
      }

      switch (orden) {
        case 'EHLO': case 'HELO':
          responder('250-smtp-de-prueba'); responder('250-AUTH LOGIN PLAIN'); responder('250 HELP'); break;
        case 'AUTH':
          esperandoAuth = 'usuario'; responder('334 VXNlcm5hbWU6'); break;
        case 'MAIL':
          if (estado !== 'auth') { responder('530 5.7.0 Hace falta autenticarse'); break; }
          sobre.from = (linea.match(/<(.*)>/) || [])[1] ?? null; responder('250 2.1.0 Remitente ok'); break;
        case 'RCPT':
          sobre.to.push((linea.match(/<(.*)>/) || [])[1] ?? ''); responder('250 2.1.5 Destinatario ok'); break;
        case 'DATA':
          enData = true; responder('354 Adelante, termina con un punto'); break;
        case 'RSET': sobre.from = null; sobre.to.length = 0; responder('250 2.0.0 Ok'); break;
        case 'QUIT': responder('221 2.0.0 Adiós'); sock.end(); break;
        default: responder('250 2.0.0 Ok');
      }
    }
  });
  sock.on('error', () => {});
}).listen(PUERTO, '127.0.0.1', () => console.log(`SMTP de prueba escuchando en 127.0.0.1:${PUERTO}`));
