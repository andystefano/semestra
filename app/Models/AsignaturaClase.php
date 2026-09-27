<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'numero',
    'fecha',
    'hora_inicio',
    'hora_termino',
    'horas_cronologicas',
    'horas_pedagogicas',
    'sin_clase',
    'comentario',
])]
class AsignaturaClase extends Model
{
    /**
     * Una hora pedagógica equivale a 45 minutos.
     */
    public const MINUTOS_HORA_PEDAGOGICA = 45;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'horas_cronologicas' => 'decimal:2',
            'horas_pedagogicas' => 'decimal:2',
            'sin_clase' => 'boolean',
            'numero' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Asignatura, $this>
     */
    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class);
    }

    public function horario(): ?string
    {
        if ($this->hora_inicio === null || $this->hora_termino === null) {
            return null;
        }

        return substr((string) $this->hora_inicio, 0, 5).'–'.substr((string) $this->hora_termino, 0, 5);
    }
}
