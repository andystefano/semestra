<?php

namespace App\Actions;

use App\Models\Asignatura;
use App\Models\GuiaEstudio;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;

class GenerarGuiaEstudio
{
    public function handle(Asignatura $asignatura, string $fecha, int $orden): GuiaEstudio
    {
        $bloque = $this->bloque($asignatura, $fecha, $orden);
        $contenidos = $this->contenidos($bloque);

        if ($contenidos === []) {
            throw new RuntimeException('Esta clase no tiene contenidos para una guía.');
        }

        $guia = $this->consultar($asignatura, $bloque, $contenidos);
        $archivo = $this->documento($asignatura, $fecha, $orden, $bloque, $guia);
        $anterior = GuiaEstudio::query()
            ->where('asignatura_id', $asignatura->id)
            ->whereDate('fecha', $fecha)
            ->where('orden', $orden)
            ->first();

        if ($anterior && $anterior->archivo_path !== $archivo['path']) {
            Storage::disk('local')->delete($anterior->archivo_path);
        }

        return GuiaEstudio::query()->updateOrCreate(
            [
                'asignatura_id' => $asignatura->id,
                'fecha' => $fecha,
                'orden' => $orden,
            ],
            [
                'contenidos' => $contenidos,
                'guia' => $guia,
                'archivo_path' => $archivo['path'],
                'archivo_nombre' => $archivo['nombre'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function bloque(Asignatura $asignatura, string $fecha, int $orden): array
    {
        foreach ($asignatura->planificacion['calendario'] ?? [] as $jornada) {
            if (($jornada['fecha'] ?? null) !== $fecha) {
                continue;
            }

            foreach ($jornada['bloques'] ?? [] as $bloque) {
                if (($bloque['tipo'] ?? '') === 'CLASE' && (int) ($bloque['orden'] ?? 0) === $orden) {
                    return $bloque;
                }
            }
        }

        throw new RuntimeException('No se encontró la clase en la planificación.');
    }

    /**
     * @param  array<string, mixed>  $bloque
     * @return list<string>
     */
    private function contenidos(array $bloque): array
    {
        $contenidos = [];

        foreach ($bloque['contenidos_obligatorios'] ?? [] as $contenido) {
            $nombre = is_array($contenido) ? trim((string) ($contenido['nombre'] ?? '')) : trim((string) $contenido);

            if ($nombre !== '') {
                $contenidos[] = $nombre;
            }
        }

        return array_values(array_unique($contenidos));
    }

    /**
     * @param  array<string, mixed>  $bloque
     * @param  list<string>  $contenidos
     * @return array<string, mixed>
     */
    private function consultar(Asignatura $asignatura, array $bloque, array $contenidos): array
    {
        $clave = config('services.deepseek.key');

        if (! is_string($clave) || $clave === '') {
            throw new RuntimeException('Falta la clave de DeepSeek.');
        }

        set_time_limit(300);

        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException('PHP no tiene la extensión ZIP. Reinicia Laragon y vuelve a intentar.');
        }

        $cliente = Http::withToken($clave)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(180);

        $certificado = config('services.deepseek.ca');

        if (is_string($certificado) && $certificado !== '' && is_file($certificado)) {
            $cliente = $cliente->withOptions(['verify' => $certificado]);
        }

        try {
            $respuesta = $cliente->post(rtrim((string) config('services.deepseek.url'), '/').'/chat/completions', [
                'model' => config('services.deepseek.model'),
                'temperature' => 0.4,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->instrucciones()],
                    ['role' => 'user', 'content' => json_encode([
                        'asignatura' => $asignatura->nombre,
                        'unidad' => $bloque['unidad'] ?? null,
                        'aprendizaje_esperado' => $bloque['aprendizaje_esperado'] ?? '',
                        'criterios_evaluacion' => $bloque['criterios_evaluacion'] ?? [],
                        'contenidos_obligatorios' => $contenidos,
                        'tipo_habilidad' => $bloque['tipo_habilidad'] ?? '',
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
                ],
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('No se pudo conectar con DeepSeek.');
        }

        $contenido = $respuesta->json('choices.0.message.content');

        if ($respuesta->failed() || ! is_string($contenido) || trim($contenido) === '') {
            throw new RuntimeException('DeepSeek no pudo generar la guía de estudios.');
        }

        return $this->guia($contenido, $contenidos);
    }

    public function reescribir(GuiaEstudio $guia): void
    {
        $asignatura = $guia->asignatura()->firstOrFail();
        $fecha = $guia->fecha->toDateString();

        try {
            $bloque = $this->bloque($asignatura, $fecha, $guia->orden);
        } catch (RuntimeException) {
            $bloque = [];
        }

        $archivo = $this->documento($asignatura, $fecha, $guia->orden, $bloque, $guia->guia);
        $guia->update([
            'archivo_path' => $archivo['path'],
            'archivo_nombre' => $archivo['nombre'],
        ]);
    }

    private function instrucciones(): string
    {
        return <<<'TXT'
Eres docente. Redacta una guía de estudios para el aprendizaje autónomo, con explicación suficiente, ejemplos y ejercicios. Usa solo los contenidos obligatorios recibidos, sin agregar otros temas.
Responde solo JSON con esta forma:
{"titulo":"","introduccion":"","apartados":[{"contenido":"","explicacion":"","ejemplo":""}],"ejercicios":[{"enunciado":"","orientacion":""}]}
Debe haber un apartado por cada contenido obligatorio, con el mismo nombre en "contenido". La explicación debe bastar para estudiar sin la clase presencial. El ejemplo es concreto. Incluye al menos dos ejercicios, cada uno con enunciado y una orientación breve de respuesta. Escribe en español.
TXT;
    }

    /**
     * @param  list<string>  $contenidos
     * @return array<string, mixed>
     */
    private function guia(string $contenido, array $contenidos): array
    {
        $contenido = trim($contenido);
        $contenido = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', $contenido) ?? $contenido;
        $datos = json_decode($contenido, true);

        if (! is_array($datos) || ! is_array($datos['apartados'] ?? null) || ! is_array($datos['ejercicios'] ?? null)) {
            throw new RuntimeException('La guía de estudios no tiene el formato esperado.');
        }

        $nombres = [];

        foreach ($datos['apartados'] as $apartado) {
            if (! is_array($apartado)) {
                continue;
            }

            $nombres[] = $this->claveContenido((string) ($apartado['contenido'] ?? ''));
        }

        foreach ($contenidos as $nombre) {
            if (! $this->contenidoIncluido($nombre, $nombres)) {
                throw new RuntimeException("La guía no desarrolló el contenido \"{$nombre}\".");
            }
        }

        if (count($datos['ejercicios']) < 2) {
            throw new RuntimeException('La guía debe incluir ejercicios.');
        }

        return [
            'titulo' => trim((string) ($datos['titulo'] ?? 'Guía de estudios')) ?: 'Guía de estudios',
            'introduccion' => trim((string) ($datos['introduccion'] ?? '')),
            'apartados' => array_values($datos['apartados']),
            'ejercicios' => array_values($datos['ejercicios']),
        ];
    }

    /**
     * @param  list<string>  $cubiertos
     */
    private function contenidoIncluido(string $nombre, array $cubiertos): bool
    {
        $clave = $this->claveContenido($nombre);

        if ($clave === '') {
            return true;
        }

        foreach ($cubiertos as $cubierto) {
            if ($cubierto === $clave) {
                return true;
            }

            $corto = mb_strlen($cubierto) < mb_strlen($clave) ? $cubierto : $clave;
            $largo = $corto === $cubierto ? $clave : $cubierto;

            if (mb_strlen($corto) >= 8 && str_contains($largo, $corto)) {
                return true;
            }
        }

        return false;
    }

    private function claveContenido(string $nombre): string
    {
        $nombre = trim($nombre);
        $nombre = preg_replace('/^[\p{Pd}\p{Po}\p{Zs}\d.]+/u', '', $nombre) ?? $nombre;
        $nombre = preg_replace('/\s+/u', ' ', $nombre) ?? $nombre;

        return mb_strtolower(trim($nombre));
    }

    private function xml(string $texto): string
    {
        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $bloque
     * @param  array<string, mixed>  $guia
     * @return array{path: string, nombre: string}
     */
    private function documento(Asignatura $asignatura, string $fecha, int $orden, array $bloque, array $guia): array
    {
        $phpWord = IOFactory::load(resource_path('plantillas/plantilla_guia.docx'));
        $section = $phpWord->getSections()[0];
        $section->addText($this->xml($guia['titulo']), ['bold' => true, 'size' => 16]);
        $section->addText($this->xml($asignatura->nombre.' · '.$fecha.(isset($bloque['unidad']) ? ' · Unidad '.$bloque['unidad'] : '')));

        if (($bloque['aprendizaje_esperado'] ?? '') !== '') {
            $section->addText('Aprendizaje esperado', ['bold' => true, 'size' => 13]);
            $section->addText($this->xml((string) $bloque['aprendizaje_esperado']));
        }

        if ($guia['introduccion'] !== '') {
            $section->addText('Introducción', ['bold' => true, 'size' => 13]);
            $section->addText($this->xml($guia['introduccion']));
        }

        foreach ($guia['apartados'] as $apartado) {
            if (! is_array($apartado)) {
                continue;
            }

            $section->addText($this->xml((string) ($apartado['contenido'] ?? '')), ['bold' => true, 'size' => 13]);
            $section->addText($this->xml((string) ($apartado['explicacion'] ?? '')));
            $section->addText('Ejemplo', ['bold' => true]);
            $section->addText($this->xml((string) ($apartado['ejemplo'] ?? '')));
        }

        $section->addText('Ejercicios', ['bold' => true, 'size' => 14]);

        foreach ($guia['ejercicios'] as $indice => $ejercicio) {
            if (! is_array($ejercicio)) {
                continue;
            }

            $section->addText($this->xml(($indice + 1).'. '.(string) ($ejercicio['enunciado'] ?? '')), ['bold' => true]);
            $section->addText($this->xml('Orientación: '.(string) ($ejercicio['orientacion'] ?? '')));
        }

        $nombre = 'Guia de estudios '.$fecha.' '.$orden.'.docx';
        $path = 'asignaturas/'.$asignatura->id.'/guias/'.$fecha.'-'.$orden.'.docx';
        Storage::disk('local')->makeDirectory('asignaturas/'.$asignatura->id.'/guias');
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save(Storage::disk('local')->path($path));

        return ['path' => $path, 'nombre' => $nombre];
    }
}
