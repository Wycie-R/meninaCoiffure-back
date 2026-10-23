<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'email', 'keycloak_id', 'password', 'rol_id', 'personal_id',
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

    public function hasRole(string $rolNombre): bool
    {
        return $this->role && strtolower($this->role->nombre) === strtolower($rolNombre);
    }

    public function esAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Adónde va la cuenta al iniciar sesión: el equipo a su panel, una clienta a la página del salón.
     */
    public function paginaInicial(): string
    {
        if ($this->hasRole('cliente')) {
            return '/salon';
        }

        return '/dashboard';
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
