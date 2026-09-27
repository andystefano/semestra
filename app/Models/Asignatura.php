<?php

namespace App\Models;

use Database\Factories\AsignaturaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

#[Fillable(['user_id', 'nombre', 'descripcion', 'fecha_inicio', 'fecha_termino', 'pdf_path', 'pdf_nombre', 'markdown', 'unidades', 'planificacion'])]
class Asignatura extends Model
{
    /** @use HasFactory<AsignaturaFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_termino' => 'date',
            'unidades' => 'array',
            'planificacion' => 'array',
        ];
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->whereKey($value)
            ->where('user_id', Auth::id())
            ->firstOrFail();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AsignaturaHorario, $this>
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(AsignaturaHorario::class)->orderBy('dia')->orderBy('hora_inicio');
    }

    /**
     * @return HasMany<AsignaturaFechaExcluida, $this>
     */
    public function fechasExcluidas(): HasMany
    {
        return $this->hasMany(AsignaturaFechaExcluida::class)->orderBy('fecha');
    }

    /**
     * @return HasMany<AsignaturaClase, $this>
     */
    public function clases(): HasMany
    {
        return $this->hasMany(AsignaturaClase::class)->orderBy('fecha')->orderBy('hora_inicio')->orderBy('numero');
    }
}
