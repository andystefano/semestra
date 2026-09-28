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
    'guia',
    'archivo_path',
    'archivo_nombre',
])]
class GuiaEstudio extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'orden' => 'integer',
            'contenidos' => 'array',
            'guia' => 'array',
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
