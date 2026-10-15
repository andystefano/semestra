<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asignatura_id',
    'fecha',
    'orden',
    'evaluacion',
    'archivo_path',
    'archivo_nombre',
])]
class EvaluacionDiagnostica extends Model
{
    protected $table = 'evaluaciones_diagnosticas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'orden' => 'integer',
            'evaluacion' => 'array',
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
