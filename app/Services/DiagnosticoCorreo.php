<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Mail\Transport\LogTransport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Responde a una sola pregunta: por qué no sale el correo.
 *
 * Existe porque hasta ahora el sistema no tenía forma de contestarla. El panel
 * decía "Enviado" con el mailer `log`, los correos encolados desaparecían sin
 * dejar fila, y la configuración del panel podía estar apagada sin que nada lo
 * dijera. Cada método de aquí comprueba un eslabón de la cadena y devuelve lo
 * que encontró, no lo que debería haber encontrado.
 *
 * Los transportes que no entregan nada a nadie. `log` escribe en
 * storage/logs y `array` se queda en memoria: los dos hacen que el mailer
 * termine sin excepción, que es justo lo que el log interpretaba como éxito.
 */
class DiagnosticoCorreo
{
    public const TRANSPORTES_FALSOS = [LogTransport::class, ArrayTransport::class];

    /** Si el trabajo más viejo lleva más de esto esperando, el worker no corre. */
    private const COLA_ATASCADA = 300;

    /** Lo último que dijo PHP al intentar cifrar, capturado del warning. */
    private ?string $ultimoAviso = null;

    public function __construct(private SmtpConfigService $smtp)
    {
    }

    /**
     * De dónde sale la configuración y qué transporte se resuelve de verdad.
     *
     * @return array<string, mixed>
     */
    public function transporte(): array
    {
        $mailerEnv = config('mail.default');

        $desdePanel = false;
        $errorPanel = null;

        try {
            $desdePanel = $this->smtp->aplicar();
        } catch (Throwable $e) {
            $errorPanel = $e->getMessage();
        }

        $clase = null;
        $descripcion = null;
        $errorTransporte = null;

        try {
            $transporte = Mail::mailer(config('mail.default'))->getSymfonyTransport();
            $clase = $transporte::class;
            $descripcion = (string) $transporte;
        } catch (Throwable $e) {
            $errorTransporte = $e->getMessage();
        }

        return [
            'mailer_env' => $mailerEnv,
            'mailer_efectivo' => config('mail.default'),
            'config_desde_panel' => $desdePanel,
            'error_panel' => $errorPanel,
            'transporte_clase' => $clase,
            'transporte' => $descripcion,
            'entrega_de_verdad' => $clase !== null && ! in_array($clase, self::TRANSPORTES_FALSOS, true),
            'error_transporte' => $errorTransporte,
            'remitente' => config('mail.from.address'),
            'remitente_nombre' => config('mail.from.name'),
        ];
    }

    /**
     * Por qué la configuración del panel se aplica o no.
     *
     * @return array<string, mixed>
     */
    public function configuracionPanel(): array
    {
        $activo = (bool) Setting::get('smtp_activo', false);
        $host = Setting::get('smtp_host');
        $usuario = Setting::get('smtp_username');
        $remitente = Setting::get('smtp_from_address');

        $motivo = match (true) {
            ! $activo => 'El interruptor "Usar esta configuración" está apagado: se usa el .env.',
            blank($host) => 'El interruptor está encendido pero el servidor SMTP está vacío: se usa el .env.',
            default => null,
        };

        return [
            'activo' => $activo,
            'host' => $host,
            'puerto' => Setting::get('smtp_port'),
            'encryption' => Setting::get('smtp_encryption'),
            'usuario' => $usuario,
            'remitente' => $remitente,
            'remitente_nombre' => Setting::get('smtp_from_name'),
            'contrasena' => $this->estadoContrasena(),
            'se_aplica' => $motivo === null,
            'motivo' => $motivo,
            'avisos' => $this->avisosDeConfiguracion($activo, $host, $usuario, $remitente),
        ];
    }

    /**
     * La contraseña se guarda cifrada con la APP_KEY. Si la clave cambió, el
     * valor sigue ahí pero ya no se puede leer, y el envío sale sin autenticar:
     * el servidor lo rechaza y desde fuera parece que "no hace nada".
     *
     * @return array{guardada: bool, legible: bool, nota: ?string}
     */
    private function estadoContrasena(): array
    {
        $fila = Setting::where('clave', 'smtp_password')->first();

        if (! $fila || blank($fila->valor)) {
            return ['guardada' => false, 'legible' => false, 'nota' => 'No hay contraseña guardada.'];
        }

        try {
            $claro = Crypt::decryptString($fila->valor);

            return [
                'guardada' => true,
                'legible' => filled($claro),
                'nota' => filled($claro) ? null : 'Se descifra pero está vacía.',
            ];
        } catch (DecryptException) {
            return [
                'guardada' => true,
                'legible' => false,
                'nota' => 'No se puede descifrar: la APP_KEY no es la misma con la que se guardó. '
                    . 'Hay que volver a escribirla en el panel.',
            ];
        }
    }

    /** @return array<int, string> */
    private function avisosDeConfiguracion(bool $activo, ?string $host, ?string $usuario, ?string $remitente): array
    {
        $avisos = [];

        if (! $activo) {
            return $avisos;
        }

        if (blank($remitente)) {
            $avisos[] = 'No hay correo remitente en el panel; se usa el del .env ('.config('mail.from.address').'). '
                . 'Un remitente que no sea del dominio del servidor suele acabar rechazado.';
        }

        if (filled($remitente) && filled($host) && ! $this->mismoDominio($remitente, $host)) {
            $avisos[] = "El remitente ({$remitente}) no es del dominio del servidor ({$host}). "
                . 'La mayoría de los servidores rechazan eso.';
        }

        if (blank($usuario)) {
            $avisos[] = 'No hay usuario SMTP: el envío saldrá sin autenticar y el servidor rechazará el relay.';
        }

        return $avisos;
    }

    private function mismoDominio(string $correo, string $host): bool
    {
        $dominioCorreo = strtolower((string) substr(strrchr($correo, '@') ?: '', 1));
        $host = strtolower($host);

        if ($dominioCorreo === '') {
            return false;
        }

        return str_ends_with($host, $dominioCorreo) || str_ends_with($dominioCorreo, $this->raiz($host));
    }

    private function raiz(string $host): string
    {
        $partes = explode('.', $host);

        return implode('.', array_slice($partes, -2));
    }

    /**
     * Estado de la cola. Todo el correo de este sistema es `ShouldQueue`, así
     * que sin worker no sale ni uno solo, y además no deja ninguna fila en el
     * log: el registro se escribe cuando el mailer envía, no al encolar.
     *
     * @return array<string, mixed>
     */
    public function cola(): array
    {
        $conexion = config('queue.default');

        if ($conexion !== 'database') {
            return [
                'conexion' => $conexion,
                'medible' => false,
                'nota' => "La cola no es `database` sino `{$conexion}`; desde aquí no se puede medir.",
            ];
        }

        try {
            $pendientes = DB::table('jobs')->count();
            $masViejo = DB::table('jobs')->min('created_at');
            $fallidos = DB::table('failed_jobs')->count();
        } catch (Throwable $e) {
            return ['conexion' => $conexion, 'medible' => false, 'nota' => $e->getMessage()];
        }

        $esperaSegundos = $masViejo ? max(0, now()->timestamp - (int) $masViejo) : 0;

        return [
            'conexion' => $conexion,
            'medible' => true,
            'pendientes' => $pendientes,
            'fallidos' => $fallidos,
            'espera_segundos' => $esperaSegundos,
            'mas_viejo' => $masViejo ? Carbon::createFromTimestamp((int) $masViejo) : null,
            'atascada' => $pendientes > 0 && $esperaSegundos > self::COLA_ATASCADA,
        ];
    }

    /**
     * Las cinco plantillas del catálogo. Si una falta, el correo que la usa no
     * se envía y no se registra nada: `CorreoTransaccional` devuelve false.
     *
     * @return array<string, mixed>
     */
    public function plantillas(): array
    {
        try {
            $filas = EmailTemplate::pluck('activo', 'clave');
        } catch (Throwable $e) {
            return ['medible' => false, 'nota' => $e->getMessage()];
        }

        $estado = [];

        foreach (EmailTemplate::CATALOGO as $clave => $meta) {
            $estado[$clave] = [
                'nombre' => $meta['nombre'],
                'existe' => $filas->has($clave),
                'activo' => (bool) $filas->get($clave, false),
            ];
        }

        return [
            'medible' => true,
            'total' => $filas->count(),
            'faltan' => collect($estado)->reject->existe->keys()->all(),
            'apagadas' => collect($estado)->filter(fn ($e) => $e['existe'] && ! $e['activo'])->keys()->all(),
            'detalle' => $estado,
        ];
    }

    /** @return array<string, mixed> */
    public function registro(): array
    {
        try {
            return [
                'medible' => true,
                'total' => EmailLog::count(),
                'enviados' => EmailLog::where('status', 'sent')->count(),
                'fallidos' => EmailLog::where('status', 'failed')->count(),
                'en_cola' => EmailLog::where('status', 'en_cola')->count(),
                'sin_salir' => EmailLog::where('status', 'no_entregado')->count(),
                'ultimo' => EmailLog::latest('id')->first(),
            ];
        } catch (Throwable $e) {
            return ['medible' => false, 'nota' => $e->getMessage()];
        }
    }

    /**
     * Habla con el servidor SMTP de verdad: TCP, TLS, EHLO y AUTH.
     *
     * Es la única comprobación que distingue "la contraseña está mal" de "el
     * puerto está cerrado" de "el remitente no se acepta". El mailer de Laravel
     * envuelve todo eso en una excepción genérica.
     *
     * @return array{ok: bool, pasos: array<int, string>, error: ?string}
     */
    public function sondaSmtp(?string $remitentePrueba = null): array
    {
        $cfg = $this->configuracionPanel();

        $host = $cfg['activo'] ? $cfg['host'] : config('mail.mailers.smtp.host');
        $puerto = (int) ($cfg['activo'] ? $cfg['puerto'] : config('mail.mailers.smtp.port'));
        $usuario = $cfg['activo'] ? $cfg['usuario'] : config('mail.mailers.smtp.username');
        $clave = $cfg['activo'] ? Setting::get('smtp_password') : config('mail.mailers.smtp.password');

        if (blank($host)) {
            return ['ok' => false, 'pasos' => [], 'error' => 'No hay servidor SMTP configurado.'];
        }

        $puerto = $puerto ?: 587;
        $pasos = [];
        $implicito = $puerto === 465;

        /*
         * Siempre se abre en claro y el TLS se negocia aparte, incluso en el
         * 465. Abriendo directamente con `ssl://`, un fallo de certificado y un
         * puerto cerrado dan el mismo error vacío, que es exactamente el tipo
         * de silencio que este comando existe para evitar.
         */
        $conexion = @stream_socket_client("tcp://{$host}:{$puerto}", $errno, $errstr, 15);

        if (! $conexion) {
            return [
                'ok' => false,
                'pasos' => ["conectar a {$host}:{$puerto} — FALLÓ"],
                'error' => trim($errstr ?: $this->ultimoErrorPhp()).($errno ? " (errno {$errno})" : ''),
            ];
        }

        $pasos[] = "conectado a {$host}:{$puerto}";
        stream_set_timeout($conexion, 15);

        if ($implicito && ! $this->negociarTls($conexion, $pasos)) {
            @fclose($conexion);

            return [
                'ok' => false,
                'pasos' => $pasos,
                'error' => 'El puerto 465 exige TLS desde el primer byte y el cifrado no se pudo establecer: '
                    .$this->ultimoErrorPhp(),
            ];
        }

        $leer = function () use ($conexion): string {
            $todo = '';
            while (($linea = fgets($conexion, 1024)) !== false) {
                $todo .= $linea;
                if (preg_match('/^\d{3} /', $linea)) {
                    break;
                }
            }

            return trim($todo);
        };
        $escribir = fn (string $orden) => fwrite($conexion, $orden."\r\n");
        $codigo = fn (string $r) => (int) substr(trim(strrchr("\n".$r, "\n")), 0, 3);

        try {
            $bienvenida = $leer();
            $pasos[] = 'saludo del servidor: '.$this->primeraLinea($bienvenida);

            $ehlo = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
            $escribir("EHLO {$ehlo}");
            $respuesta = $leer();

            if ($codigo($respuesta) !== 250) {
                return ['ok' => false, 'pasos' => $pasos, 'error' => 'EHLO rechazado: '.$this->primeraLinea($respuesta)];
            }

            $pasos[] = 'EHLO aceptado';

            // STARTTLS cuando el puerto no lleva TLS directo (587, 25).
            if (! $implicito && str_contains($respuesta, 'STARTTLS')) {
                $escribir('STARTTLS');
                $r = $leer();

                if ($codigo($r) !== 220) {
                    return ['ok' => false, 'pasos' => $pasos, 'error' => 'STARTTLS rechazado: '.$this->primeraLinea($r)];
                }

                if (! $this->negociarTls($conexion, $pasos)) {
                    return ['ok' => false, 'pasos' => $pasos, 'error' => 'STARTTLS falló al cifrar: '.$this->ultimoErrorPhp()];
                }

                $escribir("EHLO {$ehlo}");
                $respuesta = $leer();
            }

            if (blank($usuario)) {
                $pasos[] = 'sin usuario configurado: no se intenta AUTH';
            } elseif (! str_contains($respuesta, 'AUTH')) {
                $pasos[] = 'el servidor no ofrece AUTH en este puerto';
            } else {
                $escribir('AUTH LOGIN');
                $leer();
                $escribir(base64_encode((string) $usuario));
                $leer();
                $escribir(base64_encode((string) $clave));
                $r = $leer();

                if ($codigo($r) !== 235) {
                    return [
                        'ok' => false,
                        'pasos' => $pasos,
                        'error' => 'El servidor rechazó el usuario o la contraseña: '.$this->primeraLinea($r),
                    ];
                }

                $pasos[] = "autenticado como {$usuario}";
            }

            // Sin DATA: se pregunta si aceptaría el remitente y se corta ahí.
            $remitente = $remitentePrueba ?: ($cfg['remitente'] ?: config('mail.from.address'));

            if (filled($remitente)) {
                $escribir("MAIL FROM:<{$remitente}>");
                $r = $leer();

                if ($codigo($r) !== 250) {
                    return [
                        'ok' => false,
                        'pasos' => $pasos,
                        'error' => "El servidor no acepta {$remitente} como remitente: ".$this->primeraLinea($r),
                    ];
                }

                $pasos[] = "remitente {$remitente} aceptado";
                $escribir('RSET');
                $leer();
            }

            $escribir('QUIT');

            return ['ok' => true, 'pasos' => $pasos, 'error' => null];
        } catch (Throwable $e) {
            return ['ok' => false, 'pasos' => $pasos, 'error' => $e->getMessage()];
        } finally {
            @fclose($conexion);
        }
    }

    /**
     * Cifra una conexión ya abierta.
     *
     * Se intenta primero validando el certificado. Si eso falla se reintenta
     * sin validar, y si entonces sí cifra, el problema no es el servidor sino
     * el almacén de certificados de este PHP (`openssl.cafile` sin definir es
     * lo normal en Windows). Distinguirlo importa: un certificado que no se
     * puede validar aquí puede estar perfectamente bien en el servidor.
     *
     * @param  resource  $conexion
     * @param  array<int, string>  $pasos
     */
    private function negociarTls($conexion, array &$pasos): bool
    {
        $metodo = STREAM_CRYPTO_METHOD_TLS_CLIENT;

        if ($this->cifrar($conexion, $metodo)) {
            $pasos[] = 'TLS negociado y certificado validado';

            return true;
        }

        $primerError = $this->ultimoAviso;

        stream_context_set_option($conexion, 'ssl', 'verify_peer', false);
        stream_context_set_option($conexion, 'ssl', 'verify_peer_name', false);

        if ($this->cifrar($conexion, $metodo)) {
            $pasos[] = 'TLS negociado, pero el certificado NO se pudo validar desde esta máquina '
                .'(suele ser que a este PHP le falta el paquete de certificados, no un problema del servidor)';

            return true;
        }

        // Se conserva el error de la primera pasada: es el que explica de verdad
        // por qué falló, antes de que quitar la validación cambiara el motivo.
        $this->ultimoAviso = $primerError ?: $this->ultimoAviso;

        return false;
    }

    /**
     * `stream_socket_enable_crypto` sólo cuenta lo que pasó a través de un
     * warning de PHP. Con `@` se pierde, y `error_get_last()` no siempre lo
     * conserva, así que se captura con un manejador propio.
     *
     * @param  resource  $conexion
     */
    private function cifrar($conexion, int $metodo): bool
    {
        $this->ultimoAviso = null;

        set_error_handler(function (int $nivel, string $mensaje): bool {
            $this->ultimoAviso = trim(str_replace('stream_socket_enable_crypto(): ', '', $mensaje));

            return true;
        });

        try {
            return (bool) stream_socket_enable_crypto($conexion, true, $metodo);
        } finally {
            restore_error_handler();
        }
    }

    private function ultimoErrorPhp(): string
    {
        return $this->ultimoAviso ?: (error_get_last()['message'] ?? 'sin detalle del sistema');
    }

    private function primeraLinea(string $texto): string
    {
        return trim(strtok($texto, "\n") ?: $texto);
    }
}
