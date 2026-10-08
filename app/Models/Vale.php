<?php

namespace App\Models;

use App\Enums\EstatusVale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Vale extends Model
{
    protected $table = 'vales_combustible';

    // precio_litro es columna generada: nunca se asigna.
    protected $fillable = [
        'folio', 'unidad_id', 'operador_id', 'direccion_id', 'responsable_id',
        'tipo_combustible_id', 'fecha_carga', 'lectura_medidor', 'medidor_valido',
        'litros', 'importe', 'observaciones', 'estatus', 'motivo_cancelacion', 'capturado_por',
    ];

    protected $attributes = [
        'medidor_valido' => true,
        'estatus' => 'registrado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_carga' => 'datetime',
            'lectura_medidor' => 'decimal:1',
            'medidor_valido' => 'boolean',
            'litros' => 'decimal:2',
            'importe' => 'decimal:2',
            'precio_litro' => 'decimal:3',
            'estatus' => EstatusVale::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Vale $vale) {
            // Snapshot de la asignación actual de la unidad si no se indicó otra.
            if ($vale->unidad_id && (! $vale->direccion_id || ! $vale->responsable_id || ! $vale->tipo_combustible_id)) {
                $unidad = Unidad::find($vale->unidad_id);
                $vale->direccion_id ??= $unidad?->direccion_id;
                $vale->responsable_id ??= $unidad?->responsable_id;
                $vale->tipo_combustible_id ??= $unidad?->tipo_combustible_id;
            }

            $vale->capturado_por ??= auth()->id();
        });

        static::saving(function (Vale $vale) {
            if ($vale->medidor_valido === false) {
                $vale->lectura_medidor = null;
            }
        });
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(Operador::class);
    }

    public function direccion(): BelongsTo
    {
        return $this->belongsTo(Direccion::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Responsable::class);
    }

    public function tipoCombustible(): BelongsTo
    {
        return $this->belongsTo(TipoCombustible::class);
    }

    public function capturadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'capturado_por');
    }

    public function rendimiento(): HasOne
    {
        return $this->hasOne(ValeRendimiento::class, 'id', 'id');
    }

    public function scopeRegistrados(Builder $query): void
    {
        $query->where('estatus', EstatusVale::Registrado);
    }

    /**
     * Carga válida inmediatamente anterior a $fecha para la unidad,
     * con lectura de medidor. Sirve para validar la captura.
     */
    public static function cargaAnteriorConLectura(int $unidadId, Carbon|string|null $fecha = null, ?int $excluirId = null): ?self
    {
        return static::query()
            ->registrados()
            ->where('unidad_id', $unidadId)
            ->where('medidor_valido', true)
            ->whereNotNull('lectura_medidor')
            ->when($fecha, fn (Builder $q) => $q->where('fecha_carga', '<=', $fecha))
            ->when($excluirId, fn (Builder $q) => $q->whereKeyNot($excluirId))
            ->orderByDesc('fecha_carga')
            ->orderByDesc('id')
            ->first();
    }
}
