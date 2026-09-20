<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sugerencias de dirección, con su punto en el mapa.
 *
 * Usa **Photon** (photon.komoot.io), que es el geocodificador de OpenStreetMap
 * que se recomendó y se aprobó: no pide clave, no cobra y no obliga a meter
 * una cuenta de Google en el proyecto. A cambio no promete un servicio con
 * acuerdo de nivel, y de ahí sale casi todo lo que hay escrito aquí abajo.
 *
 * **La regla que no se puede romper: esto NUNCA bloquea.** La dirección sigue
 * siendo un campo de texto libre; la sugerencia ayuda a acertar y a guardar el
 * punto exacto, y si el servicio no responde el formulario tiene que seguir
 * funcionando igual. Por eso todo lo de aquí devuelve una lista vacía ante
 * cualquier fallo, y ninguna excepción sube al controlador.
 *
 * Tres cosas que no son adorno:
 *
 * 1. **Se llama desde el servidor y no desde el navegador.** Photon admite
 *    CORS, así que se podría llamar directo, pero entonces cada visitante le
 *    estaría contando a un tercero lo que escribe en el campo de dirección
 *    letra a letra. Yendo por aquí, quien habla con Komoot es el servidor.
 * 2. **Se cachea.** Mientras alguien escribe «Avenida Providencia» salen
 *    varias consultas con prefijos repetidos, y las mismas direcciones se
 *    escriben una y otra vez en una misma edición.
 * 3. **Se acota a Chile.** Sin el filtro, «Santiago» ofrece antes Santiago de
 *    Compostela, y «Las Condes» no sale por ninguna parte.
 */
class Geocodificador
{
    /** El servicio. Sin clave y sin coste; lo que pide es no abusar. */
    private const API = 'https://photon.komoot.io/api/';

    /**
     * Cuánto se espera. Corto a propósito: esto pasa mientras alguien escribe,
     * y una sugerencia que llega a los ocho segundos no la lee nadie.
     */
    private const SEGUNDOS = 4;

    /** Lo que se guarda una respuesta. */
    private const CACHE_MINUTOS = 60 * 24;

    /** Desde dónde se ordenan los resultados: el centro de Chile continental. */
    private const CENTRO_CL = ['lat' => -33.45, 'lon' => -70.65];

    /**
     * Direcciones que coinciden con lo escrito.
     *
     * @return array<int, array{etiqueta: string, direccion: string, ciudad: string, latitud: float, longitud: float}>
     */
    public function sugerencias(string $texto, int $tope = 6): array
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');

        // Con menos de tres letras no hay nada que sugerir que sirva, y sí
        // mucha consulta inútil contra un servicio que nos deja usarlo gratis.
        if (mb_strlen($texto) < 3) {
            return [];
        }

        $clave = 'photon:'.md5(mb_strtolower($texto).'|'.$tope);

        return Cache::remember($clave, now()->addMinutes(self::CACHE_MINUTOS), function () use ($texto, $tope) {
            return $this->preguntar($texto, $tope);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function preguntar(string $texto, int $tope): array
    {
        try {
            $respuesta = Http::timeout(self::SEGUNDOS)
                ->connectTimeout(self::SEGUNDOS)
                // Photon pide identificarse; sin esto responde a veces con 429.
                ->withHeaders(['User-Agent' => config('app.name').' (dps)'])
                ->get(self::API, [
                    'q' => $texto,
                    'limit' => $tope * 2,
                    /*
                     * No se manda `lang`: Photon sólo admite default, de, en y
                     * fr, y con `lang=es` responde 400. El valor por defecto ya
                     * devuelve los nombres locales, que en Chile es castellano.
                     */
                    'lat' => self::CENTRO_CL['lat'],
                    'lon' => self::CENTRO_CL['lon'],
                ]);

            if (! $respuesta->successful()) {
                return [];
            }

            return $this->limpiar($respuesta->json('features', []), $tope);
        } catch (Throwable $e) {
            /*
             * Un fallo aquí no es un error de la aplicación: es un servicio de
             * fuera que no contestó. Se anota para poder mirarlo y se devuelve
             * vacío, que es lo que deja el campo funcionando como texto libre.
             */
            Log::info('Photon no respondió', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Deja cada resultado en lo que necesita el formulario.
     *
     * Se quedan sólo los de Chile —el `lat`/`lon` de la consulta ordena, pero
     * no filtra— y se descartan los repetidos: Photon devuelve a menudo la
     * misma calle como «street» y como «house», y en la lista se leen igual.
     *
     * @param  array<int, array<string, mixed>>  $features
     * @return array<int, array<string, mixed>>
     */
    private function limpiar(array $features, int $tope): array
    {
        $vistos = [];
        $salida = [];

        foreach ($features as $f) {
            $p = $f['properties'] ?? [];
            $coordenadas = $f['geometry']['coordinates'] ?? null;

            if (($p['countrycode'] ?? '') !== 'CL' || ! is_array($coordenadas) || count($coordenadas) < 2) {
                continue;
            }

            // Photon devuelve [longitud, latitud], en ese orden. Invertirlo
            // deja el punto en mitad del océano Índico y nadie lo nota hasta
            // que abre el mapa.
            [$longitud, $latitud] = $coordenadas;

            $calle = trim(collect([
                $p['name'] ?? null,
                $p['housenumber'] ?? null,
            ])->filter()->implode(' '));

            $ciudad = trim(collect([
                $p['city'] ?? $p['district'] ?? null,
                $p['state'] ?? null,
            ])->filter()->unique()->implode(', '));

            if ($calle === '') {
                continue;
            }

            $etiqueta = trim($calle.($ciudad !== '' ? ', '.$ciudad : ''));
            $huella = mb_strtolower($etiqueta);

            if (isset($vistos[$huella])) {
                continue;
            }

            $vistos[$huella] = true;

            $salida[] = [
                'etiqueta' => $etiqueta,
                'direccion' => $calle,
                'ciudad' => $ciudad,
                'latitud' => round((float) $latitud, 7),
                'longitud' => round((float) $longitud, 7),
            ];

            if (count($salida) >= $tope) {
                break;
            }
        }

        return $salida;
    }
}
