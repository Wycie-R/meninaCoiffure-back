<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Cliente extends Model
{
    protected $table = 'clientes';
    protected $fillable = ['nombre', 'apellido', 'telefono', 'email', 'fecha_registro'];

    protected function casts(): array
    {
        return [
            // Ubicación de la casa ("Estoy en casa" en Mis citas), para el botón Cómo llegar
            'latitud' => 'float',
            'longitud' => 'float',
        ];
    }

    /** Cuenta con la que la clienta entra a "Mis citas" (opcional: la mayoría no tiene cuenta). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudCita::class, 'cliente_id');
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class, 'cliente_id');
    }

    public function preferencias(): HasMany
    {
        return $this->hasMany(PreferenciaCliente::class, 'cliente_id');
    }

    public function historial(): HasManyThrough
    {
        return $this->hasManyThrough(HistorialVisita::class, Reserva::class, 'cliente_id', 'reserva_id');
    }
}
