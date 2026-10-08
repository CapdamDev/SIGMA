<?php

namespace App\Models;

use App\Enums\ClaseUnidad;
use App\Enums\EstatusUnidad;
use App\Enums\EstatusVale;
use App\Enums\TipoMedidor;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Unidad extends Model
{
    protected $table = 'unidades';

    protected $fillable = [
        'numero_economico', 'clase', 'descripcion', 'marca', 'anio_modelo', 'placa',
        'tipo_combustible_id', 'capacidad_tanque_l', 'rendimiento_esperado', 'tipo_medidor',
        'estatus', 'direccion_id', 'responsable_id', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'clase' => ClaseUnidad::class,
            'tipo_medidor' => TipoMedidor::class,
            'estatus' => EstatusUnidad::class,
            'capacidad_tanque_l' => 'decimal:2',
            'rendimiento_esperado' => 'decimal:2',
        ];
    }

    public function tipoCombustible(): BelongsTo
    {
        return $this->belongsTo(TipoCombustible::class);
    }

    public function direccion(): BelongsTo
    {
        return $this->belongsTo(Direccion::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Responsable::class);
    }

    public function vales(): HasMany
    {
        return $this->hasMany(Vale::class);
    }

    /** Última carga registrada con lectura de medidor válida. */
    public function ultimaCargaConLectura(): HasOne
    {
        return $this->hasOne(Vale::class)->ofMany(
            ['fecha_carga' => 'max', 'id' => 'max'],
            fn ($query) => $query
                ->where('estatus', EstatusVale::Registrado)
                ->where('medidor_valido', true)
                ->whereNotNull('lectura_medidor'),
        );
    }

    protected function etiqueta(): Attribute
    {
        return Attribute::get(fn () => $this->numero_economico
            .($this->descripcion ? ' — '.$this->descripcion : '')
            .($this->placa ? ' ('.$this->placa.')' : ''));
    }
}
