<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoStock extends Model
{
    public const TIPOS = ['entrada', 'salida'];

    protected $table = 'movimientos_stock';
    protected $fillable = ['recurso_id', 'tipo', 'cantidad', 'fecha', 'motivo'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'fecha' => 'datetime',
        ];
    }

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(StockRecurso::class, 'recurso_id');
    }
}
