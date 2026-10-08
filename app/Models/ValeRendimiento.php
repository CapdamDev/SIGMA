<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de solo lectura sobre la vista v_vales_rendimiento.
 */
class ValeRendimiento extends Model
{
    protected $table = 'v_vales_rendimiento';

    public $timestamps = false;

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'lectura_anterior' => 'decimal:1',
            'recorrido' => 'decimal:1',
            'rendimiento' => 'decimal:2',
            'lectura_sospechosa' => 'boolean',
        ];
    }
}
