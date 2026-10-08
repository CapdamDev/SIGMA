<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Direccion extends Model
{
    protected $table = 'direcciones';

    protected $fillable = ['nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidad::class);
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
