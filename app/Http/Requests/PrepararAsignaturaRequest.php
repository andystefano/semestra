<?php

namespace App\Http\Requests;

use App\DiaSemana;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PrepararAsignaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $horarios = collect($this->input('horarios', []))
            ->filter(function (mixed $horario): bool {
                if (! is_array($horario)) {
                    return false;
                }

                return filled($horario['dia'] ?? null)
                    || filled($horario['hora_inicio'] ?? null)
                    || filled($horario['hora_termino'] ?? null);
            })
            ->values()
            ->all();

        $fechas = collect($this->input('fechas_sin_clase', []))
            ->map(function (mixed $item): ?array {
                if (is_string($item)) {
                    $item = ['fecha' => $item, 'comentario' => null];
                }

                if (! is_array($item)) {
                    return null;
                }

                $comentario = is_string($item['comentario'] ?? null) ? trim($item['comentario']) : null;

                return [
                    'fecha' => $this->normalizarFecha($item['fecha'] ?? null),
                    'comentario' => $comentario === '' ? null : $comentario,
                ];
            })
            ->filter(fn (?array $item): bool => is_array($item) && filled($item['fecha']))
            ->values()
            ->all();

        $this->merge([
            'nombre' => is_string($this->input('nombre')) ? trim($this->input('nombre')) : $this->input('nombre'),
            'descripcion' => is_string($this->input('descripcion')) ? trim($this->input('descripcion')) : $this->input('descripcion'),
            'fecha_inicio' => $this->normalizarFecha($this->input('fecha_inicio')),
            'fecha_termino' => $this->normalizarFecha($this->input('fecha_termino')),
            'horarios' => $horarios,
            'fechas_sin_clase' => $fechas,
        ]);

        if ($this->input('descripcion') === '') {
            $this->merge(['descripcion' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_termino' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'horarios' => ['array'],
            'horarios.*.dia' => ['required', Rule::enum(DiaSemana::class)],
            'horarios.*.hora_inicio' => ['required', 'date_format:H:i'],
            'horarios.*.hora_termino' => ['required', 'date_format:H:i'],
            'fechas_sin_clase' => ['array'],
            'fechas_sin_clase.*.fecha' => ['required', 'date', 'distinct', 'after_or_equal:fecha_inicio', 'before_or_equal:fecha_termino'],
            'fechas_sin_clase.*.comentario' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio no es válida.',
            'fecha_termino.required' => 'La fecha de término es obligatoria.',
            'fecha_termino.date' => 'La fecha de término no es válida.',
            'fecha_termino.after_or_equal' => 'La fecha de término debe ser igual o posterior a la fecha de inicio.',
            'horarios.*.dia.required' => 'Selecciona el día del horario.',
            'horarios.*.hora_inicio.required' => 'La hora de inicio del horario es obligatoria.',
            'horarios.*.hora_inicio.date_format' => 'La hora de inicio del horario no es válida.',
            'horarios.*.hora_termino.required' => 'La hora de término del horario es obligatoria.',
            'horarios.*.hora_termino.date_format' => 'La hora de término del horario no es válida.',
            'fechas_sin_clase.*.fecha.date' => 'La fecha sin clase no es válida.',
            'fechas_sin_clase.*.fecha.distinct' => 'La fecha sin clase está repetida.',
            'fechas_sin_clase.*.fecha.after_or_equal' => 'La fecha sin clase debe estar dentro del período de la asignatura.',
            'fechas_sin_clase.*.fecha.before_or_equal' => 'La fecha sin clase debe estar dentro del período de la asignatura.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['fecha_inicio', 'fecha_termino'])) {
                    return;
                }

                foreach ($this->input('horarios', []) as $indice => $horario) {
                    if ($validator->errors()->hasAny(["horarios.$indice.hora_inicio", "horarios.$indice.hora_termino"])) {
                        continue;
                    }

                    if (($horario['hora_termino'] ?? '') <= ($horario['hora_inicio'] ?? '')) {
                        $validator->errors()->add(
                            "horarios.$indice.hora_termino",
                            'La hora de término debe ser posterior a la hora de inicio.'
                        );
                    }
                }
            },
        ];
    }

    private function normalizarFecha(mixed $valor): mixed
    {
        if (! is_string($valor) || trim($valor) === '') {
            return $valor;
        }

        $valor = trim($valor);

        foreach (['Y-m-d', 'd/m/Y'] as $formato) {
            $fecha = \DateTime::createFromFormat('!'.$formato, $valor);
            $errores = \DateTime::getLastErrors();
            $esValida = $fecha instanceof \DateTime && ($errores === false || ($errores['warning_count'] === 0 && $errores['error_count'] === 0));

            if ($esValida && $fecha->format($formato) === $valor) {
                return $fecha->format('Y-m-d');
            }
        }

        return $valor;
    }
}
