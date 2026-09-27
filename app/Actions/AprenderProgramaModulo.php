<?php

namespace App\Actions;

use App\Models\Asignatura;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AprenderProgramaModulo
{
    /**
     * @return array{cantidad: int, unidades: list<array<string, mixed>>}
     */
    public function handle(Asignatura $asignatura): array
    {
        if (! is_string($asignatura->markdown) || trim($asignatura->markdown) === '') {
            throw new RuntimeException('Primero procesa el archivo PDF.');
        }

        $clave = config('services.deepseek.key');

        if (! is_string($clave) || $clave === '') {
            throw new RuntimeException('Falta la clave de DeepSeek.');
        }

        set_time_limit(300);

        $respuesta = Http::withToken($clave)
            ->acceptJson()
            ->timeout(180)
            ->post(rtrim((string) config('services.deepseek.url'), '/').'/chat/completions', [
                'model' => config('services.deepseek.model'),
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->instrucciones(),
                    ],
                    [
                        'role' => 'user',
                        'content' => "Programa del módulo en Markdown:\n\n".$asignatura->markdown,
                    ],
                ],
            ]);

        $contenido = $respuesta->json('choices.0.message.content');

        if ($respuesta->failed() || ! is_string($contenido) || trim($contenido) === '') {
            throw new RuntimeException('DeepSeek no pudo leer el programa del módulo.');
        }

        return $this->unidades($contenido);
    }

    private function instrucciones(): string
    {
        return <<<'TXT'
Eres un especialista en programas de estudio. Confirma la cantidad de unidades a partir del plan en Markdown y extrae cada unidad por separado.
Responde solo un JSON con esta forma:
{"cantidad":2,"unidades":[{"numero":1,"nombre":"","horas_sugeridas":0,"aprendizaje_esperado":"","criterios_evaluacion":[""],"contenidos_obligatorios":[""],"tipo_habilidad":"","competencias_personales_sociales_valoricas":""}]}
cantidad es el número de unidades detectadas. Horas_sugeridas son las horas de clases de esa unidad. aprendizaje_esperado es el aprendizaje esperado. criterios_evaluacion y contenidos_obligatorios son listas. tipo_habilidad es el tipo de habilidad asociada al aprendizaje esperado. competencias_personales_sociales_valoricas son las competencias personales, sociales y valóricas. No inventes unidades que el texto no sustente. Si un dato no aparece, usa una cadena vacía o una lista vacía.
TXT;
    }

    /**
     * @return array{cantidad: int, unidades: list<array<string, mixed>>}
     */
    private function unidades(string $contenido): array
    {
        $contenido = trim($contenido);
        $contenido = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $contenido) ?? $contenido;
        $datos = json_decode($contenido, true);

        if (! is_array($datos) || ! is_array($datos['unidades'] ?? null) || $datos['unidades'] === []) {
            throw new RuntimeException('No se detectaron unidades en el programa.');
        }

        $unidades = [];

        foreach (array_values($datos['unidades']) as $indice => $unidad) {
            if (! is_array($unidad)) {
                continue;
            }

            $unidades[] = [
                'numero' => $indice + 1,
                'nombre' => $this->texto($unidad['nombre'] ?? ''),
                'horas_sugeridas' => $this->horas($unidad['horas_sugeridas'] ?? null),
                'aprendizaje_esperado' => $this->texto($unidad['aprendizaje_esperado'] ?? ''),
                'criterios_evaluacion' => $this->lista($unidad['criterios_evaluacion'] ?? []),
                'contenidos_obligatorios' => $this->lista($unidad['contenidos_obligatorios'] ?? []),
                'tipo_habilidad' => $this->texto($unidad['tipo_habilidad'] ?? ''),
                'competencias_personales_sociales_valoricas' => $this->texto($unidad['competencias_personales_sociales_valoricas'] ?? ''),
            ];
        }

        if ($unidades === []) {
            throw new RuntimeException('No se detectaron unidades en el programa.');
        }

        return [
            'cantidad' => count($unidades),
            'unidades' => $unidades,
        ];
    }

    private function texto(mixed $valor): string
    {
        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    private function horas(mixed $valor): int|float|null
    {
        if (is_int($valor) || is_float($valor)) {
            return $valor;
        }

        if (is_string($valor) && is_numeric(trim($valor))) {
            return str_contains($valor, '.') ? (float) $valor : (int) $valor;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function lista(mixed $valor): array
    {
        if (is_string($valor)) {
            $valor = $valor === '' ? [] : [$valor];
        }

        if (! is_array($valor)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            $valor,
        ), fn (string $item): bool => $item !== ''));
    }
}
