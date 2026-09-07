<?php

namespace App\Http\Controllers;

use App\Http\Requests\EvaluationRequest;
use App\Models\Activity;
use App\Models\ActivityEvaluation;
use App\Services\Evaluaciones;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * La encuesta que se responde tras escanear el QR de una actividad.
 *
 * Es pública y sin cuenta: quien llega aquí acaba de apuntar el teléfono a un
 * cartel. Cada cosa que se le pida de más es gente que no la contesta.
 */
class EvaluationController extends Controller
{
    /** Dónde viven las fotos. Disco privado, ver `guardarFoto()`. */
    public const CARPETA_FOTOS = 'evaluaciones';

    public function show(Activity $activity, Evaluaciones $evaluaciones)
    {
        /*
         * Mismo permiso que la ficha pública, y 404 y no 403 por el mismo
         * motivo: un 403 confirmaría que esa dirección existe. De paso, deja
         * que el organizador abra la de su propia actividad sin publicar, que
         * es como puede comprobar su QR antes de imprimirlo.
         */
        abort_unless(Gate::allows('view', $activity), 404);

        $estado = $evaluaciones->estado($activity);

        if ($estado['estado'] !== Evaluaciones::ABIERTA) {
            return response()->view('public.evaluacion.cerrada', [
                'activity' => $activity,
                'estado' => $estado,
            ]);
        }

        return view('public.evaluacion.form', [
            'activity' => $activity,
            'origenes' => ActivityEvaluation::ORIGENES,
            'escalas' => ActivityEvaluation::ESCALAS,
        ]);
    }

    public function store(EvaluationRequest $request, Activity $activity)
    {
        abort_unless(Gate::allows('view', $activity), 404);

        /*
         * Si huele a robot, se responde que sí y no se guarda nada.
         *
         * Enseñarle un error le diría qué le delató y con qué corregirlo. La
         * pantalla de gracias no le dice nada, y a una persona que hubiera
         * caído aquí por accidente —un gestor de contraseñas rellenando el
         * campo trampa, por ejemplo— no la deja tirada delante de un error que
         * no puede entender.
         */
        if ($request->pareceRobot()) {
            return redirect()->route('evaluar.gracias', $activity);
        }

        $datos = $request->validated();

        /*
         * La foto se guarda ANTES de la fila, pero se borra si la fila no llega
         * a existir: un archivo huérfano en el disco no lo ve nadie nunca más.
         */
        $foto = $request->file('foto');
        $rutaFoto = $foto instanceof UploadedFile ? $this->guardarFoto($foto) : null;

        try {
            ActivityEvaluation::create([
                'activity_id' => $activity->id,
                'nombre' => $datos['nombre'],
                'correo' => mb_strtolower(trim($datos['correo'])),
                'experiencia' => $datos['experiencia'],
                'significado' => $datos['significado'],
                'motivacion' => $datos['motivacion'],
                'como_se_entero' => $datos['como_se_entero'] ?? null,
                'foto_path' => $rutaFoto,
                /*
                 * Sin foto no hay nada que autorizar. La casilla puede llegar
                 * marcada de un intento anterior —el navegador recuerda las
                 * casillas al volver atrás— y guardarla a true sin archivo
                 * dejaría una autorización sobre nada.
                 */
                'foto_autorizada' => $rutaFoto !== null && $request->boolean('foto_autorizada'),
                'ip_hash' => $this->huella($request),
            ]);
        } catch (UniqueConstraintViolationException) {
            /*
             * Ya había evaluado. **No es un error y no se le enseña como tal:**
             * la persona hizo lo que tenía que hacer, quizá desde otro teléfono
             * o porque volvió a escanear el cartel. Ve la misma pantalla de
             * gracias, con una línea que se lo aclara.
             *
             * Se resuelve por la excepción del índice único y no por un
             * `exists()` previo porque dos envíos a la vez pasarían los dos por
             * la comprobación antes de que ninguno guardara.
             */
            if ($rutaFoto) {
                Storage::disk('local')->delete($rutaFoto);
            }

            return redirect()->route('evaluar.gracias', $activity)->with('repetida', true);
        } catch (\Throwable $e) {
            if ($rutaFoto) {
                Storage::disk('local')->delete($rutaFoto);
            }

            throw $e;
        }

        return redirect()->route('evaluar.gracias', $activity);
    }

    public function gracias(Activity $activity)
    {
        abort_unless(Gate::allows('view', $activity), 404);

        return view('public.evaluacion.gracias', [
            'activity' => $activity,
            'repetida' => (bool) session('repetida'),
        ]);
    }

    /**
     * Guarda la foto en el disco PRIVADO.
     *
     * `storage/app/private`, no `storage/app/public`. Lo público se sirve por
     * URL directa: cualquiera con la ruta vería la foto, esté autorizada o no,
     * y son fotografías de asistentes con su nombre y su correo en la fila de
     * al lado. Se sirven por `admin.evaluaciones.foto`, que pide sesión de
     * administrador.
     *
     * Tampoco se indexan en la biblioteca de medios: la biblioteca es el
     * material de la ONG, y su detector de «dónde se usa» acabaría lleno de
     * archivos que no se usan en ninguna parte. Para las autorizadas hay un
     * botón que sí las pasa a la biblioteca, y entonces es la decisión de una
     * persona y no un efecto secundario.
     */
    private function guardarFoto(UploadedFile $foto): string
    {
        $extension = strtolower($foto->getClientOriginalExtension() ?: $foto->guessExtension() ?: 'jpg');

        // Nombre nuestro y no el del cliente: el original puede traer tildes,
        // barras o el nombre real de quien la subió.
        $nombre = Str::random(40).'.'.$extension;

        return $foto->storeAs(self::CARPETA_FOTOS.'/'.now()->format('Y/m'), $nombre, 'local');
    }

    /**
     * La huella de quien envió, para el anti-spam.
     *
     * Un hash y nunca la IP: sirve igual para contar y no deja un dato personal
     * en claro en un volcado de la base. Va con la APP_KEY dentro para que el
     * hash no se pueda revertir con una tabla de las cuatro mil millones de IP.
     */
    private function huella(Request $request): ?string
    {
        $ip = $request->ip();

        return $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
    }
}
