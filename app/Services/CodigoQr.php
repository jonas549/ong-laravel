<?php

namespace App\Services;

use App\Models\Activity;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * El QR que lleva a la encuesta de evaluación de una actividad.
 *
 * ── Por qué una librería, habiendo evitado otras ──
 *
 * El `.ics` del calendario se escribió a mano porque son quince líneas de texto
 * plano. Un QR no: son Reed-Solomon, ocho patrones de enmascarado y la elección
 * de versión. Pero lo que de verdad decide es otra cosa — **un QR mal generado
 * se ve perfecto y no escanea**, y eso se descubre cuando ya está impreso en
 * cien carteles. Las dos formas de equivocarse son mudas: una zona tranquila
 * (el margen blanco obligatorio) demasiado corta, y una escala fraccionaria que
 * deja los bordes de cada módulo a medio píxel. `endroid/qr-code` resuelve las
 * dos, y su única dependencia es `bacon/bacon-qr-code`, que haría falta igual.
 *
 * ── No se guarda ──
 *
 * El PNG se genera en cada descarga, en un par de milisegundos. Guardarlo
 * obligaría a invalidarlo y no ahorra nada. Y tiene una ventaja que sí importa:
 * si `APP_URL` estuviera mal, arreglarlo arregla todas las descargas futuras
 * sin tener que acordarse de borrar nada.
 *
 * ── La trampa que hay que vigilar ──
 *
 * El QR codifica una URL absoluta, que sale de `APP_URL`. **Si `APP_URL` está
 * mal en el servidor, cada cartel impreso apunta a un sitio equivocado, para
 * siempre.** Por eso `dps:correo` lo comprueba ahora y lo dice en pantalla: es
 * el mismo patrón de fallo mudo que ya costó tres veces en este proyecto.
 */
class CodigoQr
{
    /**
     * Lado del PNG, en píxeles.
     *
     * 2048 es el tamaño para imprimir: a 300 ppp da algo menos de 17 cm de
     * lado, que es un QR de cartel. Bajarlo no ahorra nada apreciable —el
     * archivo ronda los 30 KB— y subirlo no aporta, porque para más grande
     * está el SVG.
     */
    public const LADO_IMPRESION = 2048;

    /** El que va dentro del correo: tiene que caber en la pantalla del móvil. */
    public const LADO_CORREO = 320;

    /**
     * Corrección de errores media (~15%).
     *
     * `Low` da un QR más pequeño y más fácil de escanear en pantalla, pero
     * estos se imprimen y se pegan en una pared: se manchan, se doblan y se
     * escanean torcidos. `Quartile` los haría innecesariamente densos para lo
     * corta que es la URL.
     */
    private const CORRECCION = ErrorCorrectionLevel::Medium;

    /** La dirección que codifica el QR de esta actividad. */
    public function destino(Activity $actividad): string
    {
        return route('evaluar.show', $actividad);
    }

    /** El QR en PNG, listo para escribir a un archivo o pegar en un correo. */
    public function png(Activity $actividad, int $lado = self::LADO_IMPRESION): string
    {
        return $this->construir($actividad, new PngWriter, $lado)->getString();
    }

    /**
     * El QR en SVG: vectorial, así que se imprime a cualquier tamaño sin
     * perder un solo punto. Es la respuesta de verdad a «buena resolución».
     */
    public function svg(Activity $actividad, int $lado = self::LADO_IMPRESION): string
    {
        return $this->construir($actividad, new SvgWriter, $lado)->getString();
    }

    /** Cómo se llamará el archivo que se descarga. */
    public function nombreArchivo(Activity $actividad, string $extension): string
    {
        return 'qr-evaluacion-'.$actividad->slug.'.'.$extension;
    }

    private function construir(Activity $actividad, PngWriter|SvgWriter $escritor, int $lado)
    {
        return (new Builder)->build(
            writer: $escritor,
            data: $this->destino($actividad),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: self::CORRECCION,
            size: $lado,
            /*
             * El margen NO es decorativo: el estándar exige una zona tranquila
             * de cuatro módulos alrededor. Sin ella, un QR pegado sobre fondo
             * de color o junto a otro elemento deja de leerse en la mitad de
             * los teléfonos, y en pantalla se ve perfectamente bien.
             */
            margin: 16,
            /*
             * `Margin` reparte el sobrante en el margen en vez de estirar los
             * módulos: así cada módulo cae en un número entero de píxeles y
             * ninguno queda con el borde a medias. Es la otra forma muda de
             * romper un QR.
             */
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );
    }
}
