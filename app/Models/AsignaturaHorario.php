<?php

namespace App\Models;

use App\DiaSemana;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dia', 'hora_inicio', 'hora_termino'])]
class AsignaturaHorario extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dia' => DiaSemana::class,
        ];
    }

    /**
     * @return BelongsTo<Asignatura, $this>
     */
    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class);
    }

    public function rango(): string
    {
        return $this->dia->etiqueta().' '.substr((string) $this->hora_inicio, 0, 5).'–'.substr((string) $this->hora_termino, 0, 5);
    }
}
