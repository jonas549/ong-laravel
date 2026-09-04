<?php

namespace App\Support;

/**
 * Le pone nombre al «Enlace a red social» que escribe el organizador.
 *
 * El wizard pide un enlace y no dice cuál —«Instagram, Facebook, LinkedIn o el
 * que prefieras»—, así que en la ficha pública habría que rotularlo como «Red
 * social», que no dice nada. Mirando el dominio se sabe, y «Instagram» es una
 * etiqueta que la gente reconoce antes de pulsar.
 *
 * Cuando no se reconoce el dominio se dice «Red social» y ya: inventarse el
 * nombre sería peor que la etiqueta genérica.
 */
class RedSocial
{
    /** Dominio (sin `www.`) => cómo se llama. */
    private const CONOCIDAS = [
        'instagram.com' => 'Instagram',
        'facebook.com' => 'Facebook',
        'fb.com' => 'Facebook',
        'linkedin.com' => 'LinkedIn',
        'x.com' => 'X',
        'twitter.com' => 'X',
        'tiktok.com' => 'TikTok',
        'youtube.com' => 'YouTube',
        'youtu.be' => 'YouTube',
        'threads.net' => 'Threads',
        'whatsapp.com' => 'WhatsApp',
        'wa.me' => 'WhatsApp',
    ];

    public static function nombre(?string $url): string
    {
        $host = mb_strtolower((string) parse_url((string) $url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);

        if ($host === '') {
            return 'Red social';
        }

        foreach (self::CONOCIDAS as $dominio => $nombre) {
            // Con el punto delante para que `noinstagram.com` no cuele como
            // Instagram, y la igualdad aparte para el dominio a secas.
            if ($host === $dominio || str_ends_with($host, '.'.$dominio)) {
                return $nombre;
            }
        }

        return 'Red social';
    }
}
