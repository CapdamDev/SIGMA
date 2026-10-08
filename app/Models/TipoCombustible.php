<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoCombustible extends Model
{
    protected $table = 'tipos_combustible';

    protected $fillable = ['clave', 'nombre'];

    public function vales(): HasMany
    {
        return $this->hasMany(Vale::class);
    }
}
