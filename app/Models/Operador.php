<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Operador extends Model
{
    protected $table = 'operadores';

    protected $fillable = ['nombre', 'numero_empleado', 'direccion_id', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function direccion(): BelongsTo
    {
        return $this->belongsTo(Direccion::class);
    }

    public function vales(): HasMany
    {
        return $this->hasMany(Vale::class);
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }
}
