<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Mail\CorreoDelSalon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;

#[Fillable([
    'name', 'email', 'password', 'rol_id', 'personal_id',
    'puede_crear_reservas', 'puede_cambiar_estado', 'puede_reservar_fuera_de_horario',
    'puede_ver_clientes',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Permisos que la dueña habilita a cada empleada desde Configuración (columnas de users).
     * La administración los tiene todos sin depender de las columnas.
     */
    public const PERMISOS = [
        'puede_crear_reservas' => [
            'nombre' => 'Crear y reprogramar reservas',
            'descripcion' => 'Agendar citas en su agenda, cambiarles fecha, hora o servicios y consultar la disponibilidad.',
        ],
        'puede_cambiar_estado' => [
            'nombre' => 'Cambiar el estado de las citas',
            'descripcion' => 'Confirmar, completar, marcar como no asistida o cancelar sus citas.',
        ],
        'puede_reservar_fuera_de_horario' => [
            'nombre' => 'Atender fuera del horario del salón',
            'descripcion' => 'Marcar una cita como fuera de horario (entre las 06:00 y las 22:00).',
        ],
        'puede_ver_clientes' => [
            'nombre' => 'Ver el expediente de las clientas',
            'descripcion' => 'Datos de contacto, preferencias, alergias e historial de visitas.',
        ],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'puede_crear_reservas' => 'boolean',
            'puede_cambiar_estado' => 'boolean',
            'puede_reservar_fuera_de_horario' => 'boolean',
            'puede_ver_clientes' => 'boolean',
        ];
    }

    public function role(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol_id');
    }

    public function personal(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    /** Ficha de clienta vinculada a la cuenta (solo para el rol cliente). */
    public function cliente(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Cliente::class, 'user_id');
    }

    public function hasRole(string $rolNombre): bool
    {
        return $this->role && strtolower($this->role->nombre) === strtolower($rolNombre);
    }

    public function esAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Una profesional sin ficha o con la ficha dada de baja no puede operar: así, desactivarla en
     * Personal le corta el acceso aunque tenga la sesión abierta o el "Seguir conectado".
     */
    public function esProfesionalSinFichaActiva(): bool
    {
        if (!$this->hasRole('profesional')) {
            return false;
        }

        if ($this->personal === null) {
            return true;
        }

        return !$this->personal->activo;
    }

    /**
     * Adónde va la cuenta al iniciar sesión: el equipo a su panel, una clienta a sus citas.
     */
    public function paginaInicial(): string
    {
        if ($this->hasRole('cliente')) {
            return '/mi-cuenta';
        }

        return '/dashboard';
    }

    /**
     * Correo con el enlace para crear una contraseña nueva. Lo llama Password::sendResetLink;
     * se reemplaza el aviso en inglés de Laravel por uno en español con el enlace a nuestra pantalla.
     */
    public function sendPasswordResetNotification($token): void
    {
        // Con APP_URL y no con url(): así el enlace nunca depende de las cabeceras de quien lo pidió
        $enlace = rtrim((string) config('app.url'), '/') . '/restablecer-contrasena/' . $token . '?email=' . urlencode($this->email);
        $minutosDeValidez = config('auth.passwords.users.expire');

        // Sin el nombre: lo escribe quien se registra y no se verifica el correo, así que alguien
        // podría usar este correo legítimo del salón para mandarle un texto propio a un tercero
        $lineas = [
            'Hola.',
            '',
            'Pediste crear una contraseña nueva para tu cuenta de Menina Coiffure.',
            'Abre este enlace para elegirla (vence en ' . $minutosDeValidez . ' minutos):',
            '',
            $enlace,
            '',
            'Si no lo pediste, ignora este correo: tu contraseña no cambia.',
        ];

        Mail::to($this->email)->send(new CorreoDelSalon('Crea tu contraseña nueva - Menina Coiffure', implode("\n", $lineas)));
    }

    /**
     * La administración tiene todos los permisos; una profesional, solo los que la dueña le habilitó.
     */
    public function tienePermiso(string $permiso): bool
    {
        if ($this->esAdmin()) {
            return true;
        }

        // Los permisos son del equipo: una clienta no tiene ninguno
        if ($this->hasRole('cliente') || !array_key_exists($permiso, self::PERMISOS)) {
            return false;
        }

        // Una columna sin cargar (null) cuenta como habilitada, igual que el valor por defecto
        return $this->getAttribute($permiso) !== false;
    }

    /**
     * Los permisos de la cuenta como {clave: bool}, para el frontend y Configuración.
     */
    public function permisos(): array
    {
        $permisos = [];
        foreach (array_keys(self::PERMISOS) as $clave) {
            $permisos[$clave] = $this->tienePermiso($clave);
        }

        return $permisos;
    }

    /**
     * La administración opera sobre cualquier reserva; un profesional, solo sobre las de su agenda.
     */
    public function puedeOperarReserva(Reserva $reserva): bool
    {
        if ($this->esAdmin()) {
            return true;
        }

        return $this->hasRole('profesional')
            && $this->personal_id !== null
            && (int) $reserva->personal_id === (int) $this->personal_id;
    }
}
