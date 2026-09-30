<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asignatura_id',
    'fecha',
    'orden',
    'contenidos',
    'diapositivas',
    'plan_pedagogico',
    'archivo_path',
    'archivo_nombre',
])]
class Presentacion extends Model
{
    protected $table = 'presentaciones';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'orden' => 'integer',
            'contenidos' => 'array',
            'diapositivas' => 'array',
            'plan_pedagogico' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Asignatura, $this>
     */
    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class);
    }
}
