<?php

namespace App\Support;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Los archivos que el usuario ya subió, conservados cuando el formulario rebota.
 *
 * ── El fallo que esto arregla (punto 4 de la tanda del 11/09) ──
 *
 * El ticket decía «el logo cargado por la organización no aparece en la ficha».
 * La ficha estaba bien: lo que pasaba es que el logo **nunca llegaba a
 * guardarse**, y de una forma completamente muda.
 *
 * Un `<input type="file">` NO se puede rellenar desde el servidor —ningún
 * navegador lo permite, y con razón: si se pudiera, una página podría subir un
 * archivo del disco de quien la visita sin que lo eligiera—. Así que cuando el
 * wizard rebota por cualquier error de validación, el archivo que la persona ya
 * había elegido desaparece del formulario. Si no se da cuenta y vuelve a
 * enviar, **la organización se crea sin logo y nadie se entera**: no hay error,
 * no hay aviso, y el campo era opcional.
 *
 * Es exactamente lo que reportó Natalia el 08/09 —«me arrojó error por peso de
 * imágenes, cambié la imagen, y me dice que la cuenta ya existe»—: cada rebote
 * le tiraba lo subido.
 *
 * ── Cómo se resuelve ──
 *
 * El archivo se guarda en el disco PRIVADO en cuanto el formulario rebota, y se
 * recuerda en la sesión. Al reenviar, si no viene archivo nuevo, se usa el
 * retenido. La vista lo dice en pantalla, para que nadie tenga que adivinar si
 * sigue ahí.
 *
 * ── Tres decisiones que no son de detalle ──
 *
 * 1. **Sólo se retiene lo que PASÓ su propia validación.** Retener un archivo
 *    que falló —el logo de 4 MB, justamente— lo dejaría pegado a la sesión y se
 *    reenviaría solo una y otra vez, convirtiendo un error corregible en uno
 *    eterno. Por eso hace falta el `Validator`, y no basta con la petición.
 * 2. **Disco privado y ruta por sesión.** Son archivos de alguien que todavía
 *    no tiene cuenta. En `storage/app/public` quedarían accesibles por URL a
 *    cualquiera que la adivinara.
 * 3. **En sesión normal y no en `flash`.** Un `flash` dura una petición: el
 *    usuario vería el archivo al volver del rebote y lo perdería al reenviar,
 *    que es el momento exacto en el que hace falta. Se borra al publicar bien,
 *    o cuando el usuario lo quita a mano.
 */
class ArchivosRetenidos
{
    /** Dónde viven, dentro del disco privado. */
    private const CARPETA = 'retenidos';

    /** La rama de la sesión donde se apunta lo retenido. */
    private const SESION = 'archivos_retenidos';

    /**
     * Guarda los archivos que venían bien en una petición que se va a rechazar.
     *
     * @param  array<int, string>  $campos  nombres de los `<input type="file">`
     */
    public static function guardar(Request $peticion, Validator $validador, array $campos): void
    {
        foreach ($campos as $campo) {
            $archivo = $peticion->file($campo);

            // Sin archivo nuevo: se conserva lo que ya hubiera retenido de un
            // rebote anterior. No hacer nada es justo lo correcto aquí.
            if (! $archivo instanceof UploadedFile || ! $archivo->isValid()) {
                continue;
            }

            // El archivo es el que falló: no se retiene (ver la decisión 1).
            if ($validador->errors()->has($campo)) {
                self::olvidar($campo);

                continue;
            }

            self::olvidar($campo);

            $ruta = $archivo->store(self::CARPETA.'/'.$peticion->session()->getId(), 'local');

            $peticion->session()->put(self::SESION.'.'.$campo, [
                'ruta' => $ruta,
                'nombre' => $archivo->getClientOriginalName(),
                'peso' => $archivo->getSize(),
                'tipo' => $archivo->getClientMimeType(),
            ]);
        }
    }

    /** Lo retenido para este campo, o null. @return array<string, mixed>|null */
    public static function recuperar(string $campo): ?array
    {
        $datos = session(self::SESION.'.'.$campo);

        if (! is_array($datos) || ! isset($datos['ruta'])) {
            return null;
        }

        // El archivo pudo borrarse por fuera (limpieza, otro despliegue). Si no
        // está, la sesión miente y hay que quitarlo de en medio.
        if (! Storage::disk('local')->exists($datos['ruta'])) {
            self::olvidar($campo);

            return null;
        }

        return $datos;
    }

    /** La ruta absoluta del archivo retenido, para poder moverlo a su destino. */
    public static function rutaAbsoluta(string $campo): ?string
    {
        $datos = self::recuperar($campo);

        return $datos ? Storage::disk('local')->path($datos['ruta']) : null;
    }

    /** Se acabó: borra el archivo y su apunte. */
    public static function olvidar(string $campo): void
    {
        $datos = session(self::SESION.'.'.$campo);

        if (is_array($datos) && isset($datos['ruta'])) {
            Storage::disk('local')->delete($datos['ruta']);
        }

        session()->forget(self::SESION.'.'.$campo);
    }

    /** Todo fuera. Se llama al publicar bien. */
    public static function limpiar(): void
    {
        foreach (array_keys((array) session(self::SESION, [])) as $campo) {
            self::olvidar($campo);
        }

        session()->forget(self::SESION);
    }
}
