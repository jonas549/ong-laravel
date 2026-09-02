<?php

namespace App\Support;

/**
 * El aviso de «te has equivocado de puerta», con el botón que lleva a la buena.
 *
 * El sitio tiene dos accesos —`/admin/login` para administración y
 * `/mi-cuenta/login` para organizaciones— y cada uno rechaza a la cuenta del
 * otro. El rechazo estaba bien pensado desde el 2026-08-26, pero se quedó en
 * texto: decía a dónde había que ir y no llevaba. Bloqueó a una persona del
 * cliente el 2026-09-01, que leyó el aviso y se quedó ahí.
 *
 * Esto guarda dos cosas en la sesión:
 *
 * - **El aviso**, en flash: vive un solo pantallazo, el del formulario al que
 *   se vuelve rebotado. Es lo que pinta el botón.
 * - **El correo**, hasta que se use: lo lee el otro acceso al pintarse, para
 *   que al llegar allí sólo quede escribir la contraseña. Va en la sesión y no
 *   en la URL a propósito; un `?correo=` acaba en el historial del navegador y
 *   en los registros del servidor, y aquí no hace ninguna falta.
 *
 * **Esto no revela nada.** Sólo se llama cuando `User::porCredenciales` ya ha
 * dicho que sí, o sea cuando quien está escribiendo ya sabe la contraseña de
 * esa cuenta. Sin eso, probar correos en el acceso de administración diría
 * cuáles son de administración, que es justo lo que la separación evita.
 */
final class PuertaDeAcceso
{
    /** El aviso que pinta el botón. Flash: dura un pantallazo. */
    private const AVISO = 'acceso.puerta';

    /** El correo que ya escribió, para no pedírselo dos veces. */
    private const CORREO = 'acceso.correo';

    public const A_ADMIN = 'admin';

    public const A_ORGANIZADOR = 'organizador';

    /**
     * @param  string  $destino  self::A_ADMIN o self::A_ORGANIZADOR
     */
    public static function sugerir(string $destino, string $correo): void
    {
        session()->flash(self::AVISO, $destino);

        // Con su destino dentro: lo recoge el acceso al que se manda y no
        // otro. Sin eso se lo comía la propia pantalla a la que se rebota,
        // que también es un `showLogin` y también va a buscarlo, antes de
        // que nadie hubiera pulsado el botón.
        session()->put(self::CORREO, ['destino' => $destino, 'correo' => $correo]);
    }

    /**
     * El aviso a pintar, o null.
     *
     * @return array{texto: string, boton: string, url: string}|null
     */
    public static function sugerida(): ?array
    {
        return match (session(self::AVISO)) {
            self::A_ORGANIZADOR => [
                'texto' => 'Ese correo es de una cuenta de organización, no de administración. El acceso de organizaciones es otro.',
                'boton' => 'Entrar como organización',
                'url' => route('account.login'),
            ],
            self::A_ADMIN => [
                'texto' => 'Ese correo es de una cuenta de administración. El panel tiene su propio acceso.',
                'boton' => 'Ir al panel administrativo',
                'url' => route('admin.login'),
            ],
            default => null,
        };
    }

    /**
     * El correo que escribió en la otra puerta, una sola vez.
     *
     * `$puerta` es quién pregunta: sólo se lo lleva el acceso al que se le
     * mandó. Y se saca de la sesión al leerlo, porque si no, quien vuelva
     * más tarde a esa pantalla se encontraría el campo relleno sin saber
     * por qué.
     *
     * @param  string  $puerta  self::A_ADMIN o self::A_ORGANIZADOR
     */
    public static function correoRecordado(string $puerta): ?string
    {
        $guardado = session(self::CORREO);

        if (! is_array($guardado) || ($guardado['destino'] ?? null) !== $puerta) {
            return null;
        }

        session()->forget(self::CORREO);

        $correo = $guardado['correo'] ?? '';

        return is_string($correo) && $correo !== '' ? $correo : null;
    }
}
