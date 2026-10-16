<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Especialidad extends Model
{
    protected $table = 'especialidades';
    protected $fillable = ['nombre', 'descripcion'];

    public function personal(): BelongsToMany
    {
        return $this->belongsToMany(Personal::class, 'personal_especialidad', 'especialidad_id', 'personal_id');
    }
}
