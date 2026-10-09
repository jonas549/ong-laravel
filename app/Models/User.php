<?php

namespace App\Models;

use App\Mail\PasswordResetLink;
use App\Mail\VerificacionCorreo;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

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
            'organizacion_desde' => 'datetime',
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
        $usuario = static::porCredenciales($email, $password);

        return $usuario && $usuario->role !== $rolEsperado ? $usuario : null;
    }

    /**
     * El usuario al que pertenecen esas credenciales, sin mirar ni el rol ni
     * si está activo.
     *
     * `Auth::attempt` lleva `is_active => true` entre las condiciones, así que
     * una cuenta desactivada falla igual que una contraseña equivocada. Con
     * esto se puede distinguir un caso del otro después del fallo, para
     * registrarlo bien y dar un mensaje que sea verdad.
     *
     * Nunca responde a quien no sepa ya la contraseña, así que no sirve para
     * averiguar qué cuentas existen.
     */
    public static function porCredenciales(string $email, string $password): ?self
    {
        $usuario = static::where('email', $email)->first();

        if (! $usuario) {
            return null;
        }

        return Hash::check($password, (string) $usuario->password) ? $usuario : null;
    }

    /**
     * Correo de verificación.
     *
     * Se manda desde aquí y no con `VerifyEmail::toMailUsing()` porque por esa
     * vía el canal de notificaciones llama a `$mailable->send()` directamente,
     * y eso ignora el `ShouldQueue` del mailable: el correo salía síncrono,
     * metiendo el SMTP dentro de la petición y llegando antes que el de
     * bienvenida, que sí va por la cola.
     */
    public function sendEmailVerificationNotification(): void
    {
        $minutos = (int) config('auth.verification.expire', 60);

        Mail::to($this->getEmailForVerification())->send(new VerificacionCorreo(
            nombre: $this->name,
            enlace: URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes($minutos),
                ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())],
            ),
            minutos: $minutos,
        ));
    }

    /** El enlace de recuperación, por la cola y con el layout del sitio. */
    public function sendPasswordResetNotification($token): void
    {
        $correo = $this->getEmailForPasswordReset();
        $broker = config('auth.defaults.passwords');

        Mail::to($correo)->send(new PasswordResetLink(
            enlace: route('password.reset', ['token' => $token, 'email' => $correo]),
            minutos: (int) config("auth.passwords.{$broker}.expire", 60),
        ));
    }

    /**
     * La organización de esta cuenta.
     *
     * Hasta el bloque de varias cuentas era al revés —la organización colgaba
     * de la cuenta, `hasOne`— y una organización tenía como mucho una. Ahora
     * cada cuenta cuelga de una organización y una organización puede tener
     * muchas. El nombre de la relación no cambia, así que todo lo que pregunta
     * «¿cuál es mi organización?» sigue igual.
     *
     * `organization_id` no es asignable en masa: lo pone
     * `Organization::enlazarCuenta()`, que es quien decide además si la cuenta
     * queda como principal.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Las actividades que creó esta cuenta. */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Si es la cuenta principal de su organización: la que recibe el aviso
     * cuando alguien se suma y la única que edita la ficha.
     */
    public function esPrincipal(): bool
    {
        return $this->organization_id !== null
            && $this->organization?->user_id === $this->id;
    }

    /**
     * Si puede cambiar la ficha de su organización (nombre, tipo, logo,
     * enlaces…). Sólo la principal: con varias personas publicando, que
     * cualquiera pudiera renombrarla o cambiarle el logo desde su actividad
     * sería abrirle la ficha a todas.
     */
    public function editaLaFicha(): bool
    {
        return $this->esPrincipal();
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
