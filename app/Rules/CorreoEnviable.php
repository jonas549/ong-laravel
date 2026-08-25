<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Un correo que además se pueda enviar.
 *
 * La regla `email` de Laravel acepta acentos en la parte local porque el RFC
 * los permite, pero SMTP sin la extensión SMTPUTF8 no: Symfony lanza
 * "non-ASCII characters not supported in local-part of email" al intentar
 * enviarlo. El resultado era que la persona se inscribía, veía "listo" y nunca
 * recibía nada, mientras la organización sí la veía en su lista.
 *
 * Mejor rechazarlo en el formulario, donde la persona puede corregirlo.
 */
class CorreoEnviable implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_contains($value, '@')) {
            return; // De la forma se encarga la regla `email`.
        }

        [$local] = explode('@', $value, 2);

        if (preg_match('/[^\x20-\x7E]/', $local)) {
            $fail(':Attribute no puede llevar tildes ni caracteres especiales antes de la arroba.');
        }
    }
}
