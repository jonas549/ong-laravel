<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROL_ADMIN = 'admin';
    public const ROL_ORGANIZER = 'organizer';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Devuelve el usuario si esas credenciales son válidas pero pertenecen a
     * un rol distinto del que pide el formulario: sirve para mandar a la
     * persona al login correcto en vez de decirle que la cuenta no existe.
     *
     * Sólo responde cuando la contraseña ya es correcta, así que no le revela
     * nada a quien no la sepa.
     */
    public static function credencialesDeOtroRol(string $email, string $password, string $rolEsperado): ?self
    {
        $usuario = static::where('email', $email)->first();

        if (! $usuario || $usuario->role === $rolEsperado) {
            return null;
        }

        return Hash::check($password, (string) $usuario->password) ? $usuario : null;
    }

    public function organization(): HasOne
    {
        return $this->hasOne(Organization::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ActivityStatusLog::class);
    }

    public function esAdmin(): bool
    {
        return $this->role === self::ROL_ADMIN;
    }

    public function esOrganizador(): bool
    {
        return $this->role === self::ROL_ORGANIZER;
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', self::ROL_ADMIN);
    }

    public function scopeActivos($query)
    {
        return $query->where('is_active', true);
    }
}
