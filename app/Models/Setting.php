<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Configuración autoadministrable desde el panel.
 *
 * El valor se guarda siempre como texto y se convierte según `tipo`.
 * Los de tipo `encrypted` (contraseña SMTP) se cifran con la APP_KEY, así
 * que nunca quedan legibles en un dump de la base de datos.
 */
class Setting extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'settings.all';

    protected $fillable = ['grupo', 'clave', 'valor', 'tipo', 'label', 'descripcion', 'orden'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** Todos los ajustes como array clave => valor ya convertido. */
    public static function todos(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::all()
                ->mapWithKeys(fn (self $s) => [$s->clave => $s->valorTipado()])
                ->all();
        });
    }

    public static function get(string $clave, mixed $default = null): mixed
    {
        return static::todos()[$clave] ?? $default;
    }

    public static function set(string $clave, mixed $valor): void
    {
        $registro = static::firstOrNew(['clave' => $clave]);

        $registro->valor = $registro->tipo === 'encrypted' && filled($valor)
            ? Crypt::encryptString((string) $valor)
            : static::serializar($valor, $registro->tipo ?? 'string');

        $registro->save();
    }

    protected static function serializar(mixed $valor, string $tipo): ?string
    {
        return match ($tipo) {
            'bool' => $valor ? '1' : '0',
            'json' => json_encode($valor),
            default => $valor === null ? null : (string) $valor,
        };
    }

    public function valorTipado(): mixed
    {
        if ($this->valor === null) {
            return null;
        }

        return match ($this->tipo) {
            'bool' => (bool) $this->valor,
            'int' => (int) $this->valor,
            'json' => json_decode($this->valor, true),
            'encrypted' => $this->descifrar(),
            default => $this->valor,
        };
    }

    /**
     * Un valor cifrado con otra APP_KEY ya no se puede leer. Devolvemos null
     * en vez de reventar, para que el panel siga cargando y el usuario pueda
     * volver a escribir la contraseña.
     */
    protected function descifrar(): ?string
    {
        try {
            return Crypt::decryptString($this->valor);
        } catch (DecryptException) {
            return null;
        }
    }

    public function scopeGrupo($query, string $grupo)
    {
        return $query->where('grupo', $grupo);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('orden')->orderBy('clave');
    }
}
