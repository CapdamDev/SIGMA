<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Responsable extends Model
{
    protected $table = 'responsables';

    protected $fillable = ['titulo', 'nombre', 'direccion_id', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function direccion(): BelongsTo
    {
        return $this->belongsTo(Direccion::class);
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidad::class);
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', true);
    }

    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => trim(($this->titulo ? $this->titulo.' ' : '').$this->nombre));
    }
}
