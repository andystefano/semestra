<?php

namespace App\Presentacion;

use App\Models\Asignatura;
use App\Models\AsignaturaHorario;
use Illuminate\Support\Carbon;

class IdentificacionModulo
{
    /**
     * @return array{
     *     nombre: string,
     *     horas: string,
     *     horas_semanales: string,
     *     dias: list<string>,
     *     modalidad: string,
     *     tributacion: string,
     *     unidad_competencia: string
     * }
     */
    public function desde(Asignatura $asignatura): array
    {
        $asignatura->loadMissing(['horarios', 'clases']);
        $markdown = (string) $asignatura->markdown;
        $horas = $this->dato($markdown, 'horas') ?? $this->horasTotales($asignatura);

        return [
            'nombre' => $this->dato($markdown, 'nombre') ?: $asignatura->nombre,
            'horas' => $this->horasTexto($horas).' horas',
            'horas_semanales' => $this->horasTexto($this->horasSemanales($asignatura)).' horas semanales',
            'dias' => $this->dias($asignatura),
            'modalidad' => $this->dato($markdown, 'modalidad') ?: '—',
            'tributacion' => $this->dato($markdown, 'tributacion') ?: '—',
            'unidad_competencia' => $this->dato($markdown, 'unidad_competencia') ?: '—',
        ];
    }

    private function dato(string $markdown, string $clave): ?string
    {
        $tabla = $this->primeraTabla($markdown);

        if ($tabla === '') {
            return null;
        }

        return match ($clave) {
            'nombre' => $this->celdaSiguiente($tabla, 'Nombre del Módulo') ?? $this->celdaSiguiente($tabla, 'Nombre de la Asignatura'),
            'horas' => $this->celdaSiguiente($tabla, 'Horas de Clases'),
            'modalidad' => $this->modalidad($tabla),
            'tributacion' => $this->celdaLarga($tabla, 'Tributación'),
            'unidad_competencia' => $this->unidadCompetencia($tabla),
            default => null,
        };
    }

    private function primeraTabla(string $markdown): string
    {
        if (preg_match('/<table\b[^>]*>.*?<\/table>/is', $markdown, $tabla) !== 1) {
            return '';
        }

        return $tabla[0];
    }

    private function celdaSiguiente(string $tabla, string $etiqueta): ?string
    {
        $patron = '/'.preg_quote($etiqueta, '/').'\s*<\/t[hd]>\s*<t[hd][^>]*>\s*(.*?)<\/t[hd]>/is';

        if (preg_match($patron, $tabla, $celda) !== 1) {
            return null;
        }

        $texto = $this->plano($celda[1]);

        return $texto !== '' ? $texto : null;
    }

    private function celdaLarga(string $tabla, string $etiqueta): ?string
    {
        $patron = '/'.preg_quote($etiqueta, '/').'.*?<\/t[hd]>\s*<t[hd][^>]*>\s*(.*?)<\/t[hd]>/is';

        if (preg_match($patron, $tabla, $celda) !== 1) {
            return null;
        }

        $texto = $this->plano($celda[1]);

        return $texto !== '' ? $texto : null;
    }

    private function modalidad(string $tabla): ?string
    {
        if (preg_match('/<tr\b[^>]*>\s*<t[hd][^>]*>\s*Modalidad.*?<\/tr>/is', $tabla, $fila) !== 1) {
            return null;
        }

        preg_match_all('/<t[hd][^>]*>(.*?)<\/t[hd]>/is', $fila[0], $celdas);
        $marcadas = [];

        foreach ($celdas[1] as $indice => $celda) {
            if (! str_contains($celda, '☒')) {
                continue;
            }

            $anterior = $this->plano($celdas[1][$indice - 1] ?? '');

            if ($anterior !== '' && ! in_array($anterior, ['Modalidad', '☒'], true)) {
                $marcadas[] = $anterior;
            }
        }

        return $marcadas !== [] ? implode(', ', $marcadas) : null;
    }

    private function unidadCompetencia(string $tabla): ?string
    {
        if (preg_match('/Unidad de Competencia\s*\(UC\):\s*(.*?)<\/t[hd]>/is', $tabla, $celda) !== 1) {
            return $this->celdaLarga($tabla, 'Unidad de Competencia');
        }

        $texto = $this->plano($celda[1]);
        $texto = preg_replace('/^Al finalizar el módulo, los participantes serán capaces de:\s*/iu', '', $texto) ?? $texto;

        return $texto !== '' ? $texto : null;
    }

    private function plano(string $html): string
    {
        $texto = html_entity_decode(strip_tags(str_replace(['<br />', '<br/>', '<br>'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    private function horasTotales(Asignatura $asignatura): string
    {
        $total = $asignatura->clases
            ->where('sin_clase', false)
            ->sum(fn ($clase): float => (float) ($clase->horas_cronologicas ?? 0));

        return (string) $total;
    }

    private function horasSemanales(Asignatura $asignatura): float
    {
        $minutos = 0;

        foreach ($asignatura->horarios as $horario) {
            $minutos += $this->minutos($horario);
        }

        return $minutos / 60;
    }

    /**
     * @return list<string>
     */
    private function dias(Asignatura $asignatura): array
    {
        return $asignatura->horarios
            ->map(fn (AsignaturaHorario $horario): string => '- '.$horario->dia->etiqueta())
            ->unique()
            ->values()
            ->all();
    }

    private function minutos(AsignaturaHorario $horario): int
    {
        $inicio = Carbon::createFromFormat('H:i', substr((string) $horario->hora_inicio, 0, 5));
        $termino = Carbon::createFromFormat('H:i', substr((string) $horario->hora_termino, 0, 5));

        if ($inicio === false || $termino === false) {
            return 0;
        }

        return ($termino->hour * 60 + $termino->minute) - ($inicio->hour * 60 + $inicio->minute);
    }

    private function horasTexto(string|int|float $valor): string
    {
        $numero = is_numeric($valor) ? (float) $valor : 0.0;

        if (abs($numero - round($numero)) < 0.05) {
            return (string) (int) round($numero);
        }

        return str_replace('.', ',', number_format($numero, 1, '.', ''));
    }
}
