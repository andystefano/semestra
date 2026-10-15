<?php

namespace App\Actions;

use App\Models\Asignatura;
use App\Models\EvaluacionDiagnostica;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class GenerarEvaluacionDiagnostica
{
    private const MINUTOS = 45;

    private const MINIMO_POR_SECCION = 10;

    public function handle(Asignatura $asignatura, string $fecha, int $orden): EvaluacionDiagnostica
    {
        $this->bloque($asignatura, $fecha, $orden);

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP no tiene la extensión ZIP. Reinicia Laragon y vuelve a intentar.');
        }

        $prerrequisitos = $this->prerrequisitos((string) $asignatura->markdown);
        $unidad = $this->primeraUnidad($asignatura);
        $evaluacion = $this->consultar($asignatura, $prerrequisitos, $unidad);
        $archivo = $this->paquete($asignatura, $fecha, $orden, $evaluacion);
        $anterior = EvaluacionDiagnostica::query()
            ->where('asignatura_id', $asignatura->id)
            ->whereDate('fecha', $fecha)
            ->where('orden', $orden)
            ->first();

        if ($anterior && $anterior->archivo_path !== $archivo['path']) {
            Storage::disk('local')->delete($anterior->archivo_path);
        }

        return EvaluacionDiagnostica::query()->updateOrCreate(
            [
                'asignatura_id' => $asignatura->id,
                'fecha' => $fecha,
                'orden' => $orden,
            ],
            [
                'evaluacion' => $evaluacion,
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
     * @return list<string>
     */
    private function prerrequisitos(string $markdown): array
    {
        if (preg_match('/<tr\b[^>]*>(?:(?!<\/tr>).)*?Prerrequisito(?:(?!<\/tr>).)*<\/tr>/isu', $markdown, $fila) !== 1) {
            return [];
        }

        if (preg_match('/M[oó]dulo\(s\)\s*<\/t[hd]>\s*<t[hd][^>]*>(.*?)<\/t[hd]>/isu', $fila[0], $celda) !== 1) {
            return [];
        }

        $texto = html_entity_decode(strip_tags(str_replace(['<br />', '<br/>', '<br>'], "\n", $celda[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return array_values(array_filter(
            array_map(fn (string $modulo): string => trim(preg_replace('/\s+/u', ' ', $modulo) ?? $modulo), preg_split('/[\n,;]+/u', $texto) ?: []),
            fn (string $modulo): bool => $modulo !== '' && ! in_array(mb_strtolower($modulo), ['—', '-', 'no', 'ninguno'], true),
        ));
    }

    /**
     * @return array{nombre: string, aprendizaje_esperado: string, contenidos: list<string>}
     */
    private function primeraUnidad(Asignatura $asignatura): array
    {
        $unidad = $asignatura->unidades['unidades'][0] ?? [];
        $contenidos = [];

        foreach ($unidad['contenidos_obligatorios'] ?? [] as $contenido) {
            $nombre = trim(ltrim(trim((string) $contenido), '-–•'));

            if ($nombre !== '') {
                $contenidos[] = $nombre;
            }
        }

        if ($contenidos === []) {
            throw new RuntimeException('La asignatura no tiene contenidos de la primera unidad para la evaluación diagnóstica.');
        }

        return [
            'nombre' => trim((string) ($unidad['nombre'] ?? '')),
            'aprendizaje_esperado' => trim((string) ($unidad['aprendizaje_esperado'] ?? '')),
            'contenidos' => array_values(array_unique($contenidos)),
        ];
    }

    /**
     * @param  list<string>  $prerrequisitos
     * @param  array{nombre: string, aprendizaje_esperado: string, contenidos: list<string>}  $unidad
     * @return array<string, mixed>
     */
    private function consultar(Asignatura $asignatura, array $prerrequisitos, array $unidad): array
    {
        $clave = config('services.deepseek.key');

        if (! is_string($clave) || $clave === '') {
            throw new RuntimeException('Falta la clave de DeepSeek.');
        }

        set_time_limit(300);

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
                        'descripcion' => $asignatura->descripcion,
                        'modulos_prerrequisito' => $prerrequisitos,
                        'primera_unidad' => $unidad,
                        'duracion_minutos' => self::MINUTOS,
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
                ],
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('No se pudo conectar con DeepSeek.');
        }

        $contenido = $respuesta->json('choices.0.message.content');

        if ($respuesta->failed() || ! is_string($contenido) || trim($contenido) === '') {
            throw new RuntimeException('DeepSeek no pudo generar la evaluación diagnóstica.');
        }

        return $this->evaluacion($contenido);
    }

    private function instrucciones(): string
    {
        return <<<'TXT'
Eres docente de educación superior. Redacta una evaluación diagnóstica para la primera clase de la asignatura, que se responde en 45 minutos.
Evalúa conocimientos previos: alrededor del 60 % de las preguntas sobre los módulos prerrequisito (si no hay, sobre los conocimientos básicos que la asignatura supone) y el resto sobre los contenidos de la primera unidad recibidos, a nivel introductorio.
Responde solo JSON con esta forma:
{"titulo":"","instrucciones":"","secciones":[{"tipo":"seleccion_multiple","titulo":"","preguntas":[{"enunciado":"","alternativas":["","","",""],"correcta":"A","tema":""}]},{"tipo":"verdadero_falso","titulo":"","preguntas":[{"enunciado":"","respuesta":true,"tema":""}]}]}
La sección seleccion_multiple tiene 15 preguntas con exactamente 4 alternativas y una sola correcta; "correcta" es la letra (A, B, C o D) de la alternativa correcta. Las alternativas incorrectas son plausibles y no se usa "todas las anteriores" ni "ninguna de las anteriores".
La sección verdadero_falso tiene 15 afirmaciones; "respuesta" es true si la afirmación es verdadera y false si es falsa. Equilibra verdaderas y falsas, y no uses dobles negaciones.
"tema" indica el módulo prerrequisito o el contenido evaluado. Las preguntas no se repiten entre secciones. Escribe en español, con enunciados claros y breves.
TXT;
    }

    /**
     * @return array{titulo: string, instrucciones: string, minutos: int, secciones: list<array{tipo: string, titulo: string, preguntas: list<array<string, mixed>>}>}
     */
    private function evaluacion(string $contenido): array
    {
        $contenido = trim($contenido);
        $contenido = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', $contenido) ?? $contenido;
        $datos = json_decode($contenido, true);

        if (! is_array($datos) || ! is_array($datos['secciones'] ?? null)) {
            throw new RuntimeException('La evaluación diagnóstica no tiene el formato esperado.');
        }

        $alternativas = [];
        $verdaderoFalso = [];
        $titulos = [];

        foreach ($datos['secciones'] as $seccion) {
            if (! is_array($seccion) || ! is_array($seccion['preguntas'] ?? null)) {
                continue;
            }

            $tipo = (string) ($seccion['tipo'] ?? '');
            $titulos[$tipo] ??= trim((string) ($seccion['titulo'] ?? ''));

            foreach ($seccion['preguntas'] as $pregunta) {
                if (! is_array($pregunta)) {
                    continue;
                }

                if ($tipo === 'seleccion_multiple' && ($normalizada = $this->preguntaAlternativas($pregunta))) {
                    $alternativas[] = $normalizada;
                }

                if ($tipo === 'verdadero_falso' && ($normalizada = $this->preguntaVerdaderoFalso($pregunta))) {
                    $verdaderoFalso[] = $normalizada;
                }
            }
        }

        if (count($alternativas) < self::MINIMO_POR_SECCION) {
            throw new RuntimeException('La evaluación diagnóstica no trae suficientes preguntas de alternativas.');
        }

        if (count($verdaderoFalso) < self::MINIMO_POR_SECCION) {
            throw new RuntimeException('La evaluación diagnóstica no trae suficientes ítems de verdadero o falso.');
        }

        return [
            'titulo' => trim((string) ($datos['titulo'] ?? '')) ?: 'Evaluación diagnóstica',
            'instrucciones' => trim((string) ($datos['instrucciones'] ?? '')),
            'minutos' => self::MINUTOS,
            'secciones' => [
                [
                    'tipo' => 'seleccion_multiple',
                    'titulo' => ($titulos['seleccion_multiple'] ?? '') ?: 'Selección múltiple',
                    'preguntas' => $alternativas,
                ],
                [
                    'tipo' => 'verdadero_falso',
                    'titulo' => ($titulos['verdadero_falso'] ?? '') ?: 'Verdadero o falso',
                    'preguntas' => $verdaderoFalso,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $pregunta
     * @return array{enunciado: string, alternativas: list<string>, correcta: int, tema: string}|null
     */
    private function preguntaAlternativas(array $pregunta): ?array
    {
        $enunciado = trim((string) ($pregunta['enunciado'] ?? ''));
        $opciones = array_values(array_filter(
            array_map(fn (mixed $opcion): string => is_scalar($opcion) ? trim((string) $opcion) : '', is_array($pregunta['alternativas'] ?? null) ? $pregunta['alternativas'] : []),
            fn (string $opcion): bool => $opcion !== '',
        ));
        $correcta = $pregunta['correcta'] ?? null;
        $indice = match (true) {
            is_int($correcta) => $correcta,
            is_string($correcta) && preg_match('/^\s*([A-Za-z])\b/', $correcta, $letra) === 1 => ord(strtoupper($letra[1])) - ord('A'),
            default => -1,
        };

        if ($enunciado === '' || count($opciones) < 3 || ! isset($opciones[$indice])) {
            return null;
        }

        // Los modelos tienden a dejar la correcta en las primeras letras; se reparte al azar.
        $texto = $opciones[$indice];
        shuffle($opciones);

        return [
            'enunciado' => $enunciado,
            'alternativas' => $opciones,
            'correcta' => (int) array_search($texto, $opciones, true),
            'tema' => trim((string) ($pregunta['tema'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $pregunta
     * @return array{enunciado: string, respuesta: bool, tema: string}|null
     */
    private function preguntaVerdaderoFalso(array $pregunta): ?array
    {
        $enunciado = trim((string) ($pregunta['enunciado'] ?? ''));
        $respuesta = $pregunta['respuesta'] ?? null;

        if (is_string($respuesta)) {
            $respuesta = match (mb_strtolower(trim($respuesta))) {
                'true', 'verdadero', 'v' => true,
                'false', 'falso', 'f' => false,
                default => null,
            };
        }

        if ($enunciado === '' || ! is_bool($respuesta)) {
            return null;
        }

        return [
            'enunciado' => $enunciado,
            'respuesta' => $respuesta,
            'tema' => trim((string) ($pregunta['tema'] ?? '')),
        ];
    }

    private function xml(string $texto): string
    {
        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Arma un paquete IMS QTI 2.1 (manifiesto, prueba con secciones e ítems) que Blackboard importa.
     *
     * @param  array{titulo: string, instrucciones: string, minutos: int, secciones: list<array{tipo: string, titulo: string, preguntas: list<array<string, mixed>>}>}  $evaluacion
     * @return array{path: string, nombre: string}
     */
    private function paquete(Asignatura $asignatura, string $fecha, int $orden, array $evaluacion): array
    {
        $titulo = $evaluacion['titulo'].' · '.$asignatura->nombre;
        $items = [];
        $secciones = [];
        $numero = 0;

        foreach ($evaluacion['secciones'] as $indiceSeccion => $seccion) {
            $referencias = [];

            foreach ($seccion['preguntas'] as $pregunta) {
                $numero++;
                $identificador = sprintf('ITEM_%03d', $numero);
                $href = 'items/'.$identificador.'.xml';
                $items[$identificador] = [
                    'href' => $href,
                    'xml' => $seccion['tipo'] === 'verdadero_falso'
                        ? $this->itemVerdaderoFalso($identificador, $numero, $pregunta)
                        : $this->itemAlternativas($identificador, $numero, $pregunta),
                ];
                $referencias[] = '<assessmentItemRef identifier="'.$identificador.'" href="'.$href.'"/>';
            }

            $instrucciones = $indiceSeccion === 0 && $evaluacion['instrucciones'] !== ''
                ? '<rubricBlock view="candidate"><p>'.$this->xml($evaluacion['instrucciones']).'</p></rubricBlock>'
                : '';
            $secciones[] = '<assessmentSection identifier="SECCION_'.($indiceSeccion + 1).'" title="'.$this->xml($seccion['titulo']).'" visible="true">'
                .$instrucciones
                .implode('', $referencias)
                .'</assessmentSection>';
        }

        $prueba = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<assessmentTest xmlns="http://www.imsglobal.org/xsd/imsqti_v2p1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            .' xsi:schemaLocation="http://www.imsglobal.org/xsd/imsqti_v2p1 http://www.imsglobal.org/xsd/qti/qtiv2p1/imsqti_v2p1.xsd"'
            .' identifier="EVALUACION_DIAGNOSTICA" title="'.$this->xml($titulo).'">'
            .'<outcomeDeclaration identifier="SCORE" cardinality="single" baseType="float"><defaultValue><value>0</value></defaultValue></outcomeDeclaration>'
            .'<timeLimits maxTime="'.($evaluacion['minutos'] * 60).'"/>'
            .'<testPart identifier="PARTE_1" navigationMode="nonlinear" submissionMode="simultaneous">'
            .implode('', $secciones)
            .'</testPart>'
            .'<outcomeProcessing><setOutcomeValue identifier="SCORE"><sum><testVariables variableIdentifier="SCORE"/></sum></setOutcomeValue></outcomeProcessing>'
            .'</assessmentTest>';

        $dependencias = '';
        $recursos = '';

        foreach ($items as $identificador => $item) {
            $dependencias .= '<dependency identifierref="'.$identificador.'"/>';
            $recursos .= '<resource identifier="'.$identificador.'" type="imsqti_item_xmlv2p1" href="'.$item['href'].'"><file href="'.$item['href'].'"/></resource>';
        }

        $manifiesto = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<manifest xmlns="http://www.imsglobal.org/xsd/imscp_v1p1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            .' xsi:schemaLocation="http://www.imsglobal.org/xsd/imscp_v1p1 http://www.imsglobal.org/xsd/qti/qtiv2p1/qtiv2p1_imscpv1p2_v1p0.xsd"'
            .' identifier="MANIFIESTO_EVALUACION_DIAGNOSTICA">'
            .'<metadata><schema>QTIv2.1 Package</schema><schemaversion>1.0.0</schemaversion></metadata>'
            .'<organizations/>'
            .'<resources>'
            .'<resource identifier="EVALUACION_DIAGNOSTICA" type="imsqti_test_xmlv2p1" href="evaluacion.xml"><file href="evaluacion.xml"/>'.$dependencias.'</resource>'
            .$recursos
            .'</resources>'
            .'</manifest>';

        $nombre = 'Evaluacion diagnostica '.$fecha.' '.$orden.'.zip';
        $path = 'asignaturas/'.$asignatura->id.'/evaluaciones/'.$fecha.'-'.$orden.'.zip';
        Storage::disk('local')->makeDirectory('asignaturas/'.$asignatura->id.'/evaluaciones');
        $destino = Storage::disk('local')->path($path);
        $zip = new ZipArchive;

        if ($zip->open($destino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo ZIP de la evaluación.');
        }

        $zip->addFromString('imsmanifest.xml', $manifiesto);
        $zip->addFromString('evaluacion.xml', $prueba);

        foreach ($items as $item) {
            $zip->addFromString($item['href'], $item['xml']);
        }

        $zip->close();

        return ['path' => $path, 'nombre' => $nombre];
    }

    /**
     * @param  array<string, mixed>  $pregunta
     */
    private function itemAlternativas(string $identificador, int $numero, array $pregunta): string
    {
        $opciones = '';

        foreach ($pregunta['alternativas'] as $indice => $alternativa) {
            $opciones .= '<simpleChoice identifier="'.chr(ord('A') + $indice).'">'.$this->xml($alternativa).'</simpleChoice>';
        }

        return $this->item($identificador, 'Pregunta '.$numero, chr(ord('A') + $pregunta['correcta']), $pregunta['enunciado'], 'true', $opciones);
    }

    /**
     * @param  array<string, mixed>  $pregunta
     */
    private function itemVerdaderoFalso(string $identificador, int $numero, array $pregunta): string
    {
        $opciones = '<simpleChoice identifier="VERDADERO">Verdadero</simpleChoice><simpleChoice identifier="FALSO">Falso</simpleChoice>';

        return $this->item($identificador, 'Pregunta '.$numero, $pregunta['respuesta'] ? 'VERDADERO' : 'FALSO', $pregunta['enunciado'], 'false', $opciones);
    }

    private function item(string $identificador, string $titulo, string $correcta, string $enunciado, string $mezclar, string $opciones): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<assessmentItem xmlns="http://www.imsglobal.org/xsd/imsqti_v2p1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            .' xsi:schemaLocation="http://www.imsglobal.org/xsd/imsqti_v2p1 http://www.imsglobal.org/xsd/qti/qtiv2p1/imsqti_v2p1.xsd"'
            .' identifier="'.$identificador.'" title="'.$this->xml($titulo).'" adaptive="false" timeDependent="false">'
            .'<responseDeclaration identifier="RESPONSE" cardinality="single" baseType="identifier"><correctResponse><value>'.$correcta.'</value></correctResponse></responseDeclaration>'
            .'<outcomeDeclaration identifier="SCORE" cardinality="single" baseType="float" normalMaximum="1"><defaultValue><value>0</value></defaultValue></outcomeDeclaration>'
            .'<itemBody><choiceInteraction responseIdentifier="RESPONSE" shuffle="'.$mezclar.'" maxChoices="1">'
            .'<prompt>'.$this->xml($enunciado).'</prompt>'
            .$opciones
            .'</choiceInteraction></itemBody>'
            .'<responseProcessing template="http://www.imsglobal.org/question/qti_v2p1/rptemplates/match_correct"/>'
            .'</assessmentItem>';
    }
}
