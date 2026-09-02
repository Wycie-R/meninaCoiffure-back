<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reserva extends Model
{
    protected $table = 'reservas';
    protected $fillable = ['cliente_id', 'personal_id', 'fecha', 'hora_inicio', 'hora_fin', 'estado', 'descuento_id'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleReserva::class, 'reserva_id');
    }
}