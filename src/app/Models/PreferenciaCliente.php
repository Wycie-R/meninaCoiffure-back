<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreferenciaCliente extends Model
{
    public const CATEGORIAS = ['alergia', 'color', 'profesional_preferido', 'producto', 'otro'];

    protected $table = 'preferencias_cliente';
    protected $fillable = ['cliente_id', 'categoria', 'descripcion'];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
