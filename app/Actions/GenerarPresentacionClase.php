<?php

namespace App\Actions;

use App\Models\Asignatura;
use App\Models\Presentacion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpPresentation\PhpPresentation;
use RuntimeException;
use ZipArchive;

class GenerarPresentacionClase
{
    public function handle(Asignatura $asignatura, string $fecha, int $orden): Presentacion
    {
        $clases = $this->clases($asignatura);
        $indice = $this->indice($clases, $fecha, $orden);
        $bloque = $clases[$indice]['bloque'];
        $contenidos = $clases[$indice]['contenidos'];
        $anteriores = $indice > 0 ? $clases[$indice - 1]['contenidos'] : [];
        $desarrollo = $this->consultar($asignatura, $bloque, $contenidos, $anteriores);
        $archivo = $this->documento($asignatura, $fecha, $orden, $indice + 1, $bloque, $contenidos, $desarrollo);

        return Presentacion::query()->updateOrCreate(
            [
                'asignatura_id' => $asignatura->id,
                'fecha' => $fecha,
                'orden' => $orden,
            ],
            [
                'contenidos' => $contenidos,
                'diapositivas' => $desarrollo,
                'archivo_path' => $archivo['path'],
                'archivo_nombre' => $archivo['nombre'],
            ],
        );
    }

    /**
     * @return list<array{fecha: string, orden: int, bloque: array<string, mixed>, contenidos: list<string>}>
     */
    private function clases(Asignatura $asignatura): array
    {
        $clases = [];

        foreach ($asignatura->planificacion['calendario'] ?? [] as $jornada) {
            foreach ($jornada['bloques'] ?? [] as $bloque) {
                if (($bloque['tipo'] ?? '') !== 'CLASE') {
                    continue;
                }

                $contenidos = $this->contenidos($bloque);

                if ($contenidos === []) {
                    continue;
                }

                $clases[] = [
                    'fecha' => (string) $jornada['fecha'],
                    'orden' => (int) ($bloque['orden'] ?? 0),
                    'bloque' => $bloque,
                    'contenidos' => $contenidos,
                ];
            }
        }

        return $clases;
    }

    /**
     * @param  list<array{fecha: string, orden: int, bloque: array<string, mixed>, contenidos: list<string>}>  $clases
     */
    private function indice(array $clases, string $fecha, int $orden): int
    {
        foreach ($clases as $indice => $clase) {
            if ($clase['fecha'] === $fecha && $clase['orden'] === $orden) {
                return $indice;
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
     * @param  list<string>  $anteriores
     * @return array<string, mixed>
     */
    private function consultar(Asignatura $asignatura, array $bloque, array $contenidos, array $anteriores): array
    {
        $clave = config('services.deepseek.key');

        if (! is_string($clave) || $clave === '') {
            throw new RuntimeException('Falta la clave de DeepSeek.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP no tiene la extensión ZIP. Reinicia Laragon y vuelve a intentar.');
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
                    ['role' => 'system', 'content' => $this->instrucciones($anteriores === [])],
                    ['role' => 'user', 'content' => json_encode([
                        'asignatura' => $asignatura->nombre,
                        'aprendizaje_esperado' => $bloque['aprendizaje_esperado'] ?? '',
                        'criterios_evaluacion' => $bloque['criterios_evaluacion'] ?? [],
                        'contenidos_de_esta_clase' => $contenidos,
                        'contenidos_clase_anterior' => $anteriores,
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
                ],
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('No se pudo conectar con DeepSeek.');
        }

        $contenido = $respuesta->json('choices.0.message.content');

        if ($respuesta->failed() || ! is_string($contenido) || trim($contenido) === '') {
            throw new RuntimeException('DeepSeek no pudo generar la presentación.');
        }

        return $this->desarrollo($contenido, $contenidos, $anteriores === []);
    }

    private function instrucciones(bool $primera): string
    {
        $recordar = $primera
            ? 'recordar: una sola diapositiva que diga que esta es la primera clase y no hay clase anterior.'
            : 'recordar: una o dos diapositivas breves que resuman lo tratado en la clase anterior, usando solo contenidos_clase_anterior.';

        return <<<TXT
Eres docente. Prepara el texto de una presentación de clase, en español, breve y simple.
Responde solo JSON:
{"recordar":[{"titulo":"","puntos":[""]}],"conocer":[{"contenido":"","titulo":"","puntos":[""]}],"aplicar":[{"titulo":"","actividad":""}]}
{$recordar}
conocer: dos diapositivas por cada contenido de esta clase. El campo contenido debe repetir el nombre exacto del contenido. Cada diapositiva enseña ese tema con un título y tres o cuatro puntos cortos.
aplicar: entre 2 y 4 diapositivas. Cada una es un caso, ejemplo o actividad muy breve para que el alumno aplique lo aprendido. No agregues temas que no estén en los contenidos.
TXT;
    }

    /**
     * @param  list<string>  $contenidos
     * @return array{recordar: list<array<string, mixed>>, conocer: list<array<string, mixed>>, aplicar: list<array<string, mixed>>}
     */
    private function desarrollo(string $contenido, array $contenidos, bool $primera): array
    {
        $contenido = trim($contenido);
        $contenido = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', $contenido) ?? $contenido;
        $datos = json_decode($contenido, true);

        if (! is_array($datos['recordar'] ?? null) || ! is_array($datos['conocer'] ?? null) || ! is_array($datos['aplicar'] ?? null)) {
            throw new RuntimeException('La presentación no tiene el formato esperado.');
        }

        $cubiertos = [];

        foreach ($datos['conocer'] as $diapositiva) {
            if (is_array($diapositiva)) {
                $cubiertos[] = $this->clave((string) ($diapositiva['contenido'] ?? $diapositiva['titulo'] ?? ''));
            }
        }

        foreach ($contenidos as $nombre) {
            if (! $this->incluido($nombre, $cubiertos)) {
                throw new RuntimeException("La presentación no enseñó el contenido \"{$nombre}\".");
            }
        }

        if (count($datos['recordar']) < 1 || (! $primera && count($datos['recordar']) > 2)) {
            throw new RuntimeException('El momento para recordar no tiene la cantidad de diapositivas esperada.');
        }

        if (count($datos['aplicar']) < 2 || count($datos['aplicar']) > 6) {
            throw new RuntimeException('El momento para aplicar debe tener entre 2 y 6 diapositivas.');
        }

        return [
            'recordar' => array_values($datos['recordar']),
            'conocer' => array_values($datos['conocer']),
            'aplicar' => array_values($datos['aplicar']),
        ];
    }

    /**
     * @param  array<string, mixed>  $bloque
     * @param  list<string>  $contenidos
     * @param  array{recordar: list<array<string, mixed>>, conocer: list<array<string, mixed>>, aplicar: list<array<string, mixed>>}  $desarrollo
     * @return array{path: string, nombre: string}
     */
    private function documento(
        Asignatura $asignatura,
        string $fecha,
        int $orden,
        int $numero,
        array $bloque,
        array $contenidos,
        array $desarrollo,
    ): array {
        $criterios = array_map(
            fn (mixed $criterio): string => trim((string) $criterio),
            is_array($bloque['criterios_evaluacion'] ?? null) ? $bloque['criterios_evaluacion'] : [],
        );
        $diapositivas = [
            $this->disenar('Aprendizaje esperado', array_values(array_filter([
                (string) ($bloque['aprendizaje_esperado'] ?? ''),
            ]))),
            $this->disenar('Criterios de evaluación', $criterios),
            $this->disenar('Contenidos', $contenidos),
        ];

        foreach ($desarrollo['recordar'] as $indice => $diapositiva) {
            $titulo = is_array($diapositiva) ? trim((string) ($diapositiva['titulo'] ?? '')) : '';

            if ($indice === 0) {
                $titulo = $titulo === '' ? 'Momento para recordar' : 'Momento para recordar: '.$titulo;
            }

            $diapositivas[] = $this->disenar(
                $titulo !== '' ? $titulo : 'Momento para recordar',
                is_array($diapositiva) ? $this->lineas($diapositiva['puntos'] ?? []) : [],
            );
        }

        foreach ($desarrollo['conocer'] as $indice => $diapositiva) {
            if (! is_array($diapositiva)) {
                continue;
            }

            $titulo = trim((string) ($diapositiva['titulo'] ?? $diapositiva['contenido'] ?? ''));

            if ($indice === 0) {
                $titulo = $titulo === '' ? 'Momento para conocer' : 'Momento para conocer: '.$titulo;
            }

            $diapositivas[] = $this->disenar(
                $titulo !== '' ? $titulo : 'Momento para conocer',
                $this->lineas($diapositiva['puntos'] ?? []),
            );
        }

        foreach ($desarrollo['aplicar'] as $indice => $diapositiva) {
            if (! is_array($diapositiva)) {
                continue;
            }

            $titulo = trim((string) ($diapositiva['titulo'] ?? ''));

            if ($indice === 0) {
                $titulo = $titulo === '' ? 'Momento para aplicar' : 'Momento para aplicar: '.$titulo;
            }

            $diapositivas[] = $this->disenar(
                $titulo !== '' ? $titulo : 'Momento para aplicar',
                [trim((string) ($diapositiva['actividad'] ?? ''))],
            );
        }

        $nombre = 'Presentacion '.$fecha.' '.$orden.'.pptx';
        $path = 'asignaturas/'.$asignatura->id.'/presentaciones/'.$fecha.'-'.$orden.'.pptx';
        Storage::disk('local')->makeDirectory('asignaturas/'.$asignatura->id.'/presentaciones');
        $destino = Storage::disk('local')->path($path);
        copy(resource_path('plantillas/Clase_Clase.pptx'), $destino);
        $this->escribirPresentacion($destino, $this->xml($asignatura->nombre), (string) $numero, Carbon::parse($fecha)->format('d/m/Y'), $diapositivas);

        return ['path' => $path, 'nombre' => $nombre];
    }

    /**
     * @param  list<string>  $lineas
     * @return array{titulo: string, lineas: list<string>}
     */
    private function disenar(string $titulo, array $lineas): array
    {
        $presentacion = new PhpPresentation;
        $forma = $presentacion->getActiveSlide()->createRichTextShape();
        $forma->createTextRun($titulo)->getFont()->setBold(true)->setSize(28);

        foreach ($lineas as $linea) {
            if (trim($linea) === '') {
                continue;
            }

            $forma->createParagraph()->createTextRun($linea)->getFont()->setSize(18);
        }

        $tituloDisenado = $titulo;
        $puntos = [];

        foreach ($forma->getParagraphs() as $indice => $parrafo) {
            $texto = '';

            foreach ($parrafo->getRichTextElements() as $elemento) {
                if (method_exists($elemento, 'getText')) {
                    $texto .= $elemento->getText();
                }
            }

            if ($indice === 0) {
                $tituloDisenado = $texto;
            } elseif (trim($texto) !== '') {
                $puntos[] = $texto;
            }
        }

        return ['titulo' => $tituloDisenado, 'lineas' => $puntos];
    }

    /**
     * @return list<string>
     */
    private function lineas(mixed $puntos): array
    {
        if (! is_array($puntos)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $punto): string => trim((string) $punto),
            $puntos,
        )));
    }

    /**
     * @param  list<array{titulo: string, lineas: list<string>}>  $diapositivas
     */
    private function escribirPresentacion(string $destino, string $asignatura, string $numero, string $fecha, array $diapositivas): void
    {
        $zip = new ZipArchive;

        if ($zip->open($destino) !== true) {
            throw new RuntimeException('No se pudo abrir la plantilla de la presentación.');
        }

        $portada = $zip->getFromName('ppt/slides/slide1.xml');
        $portada = str_replace(
            ['{NOMBRE_ASIGNATURA}', '{NUMERO_CLASE}', '{FEHCA CLASE}'],
            [$asignatura, $this->xml($numero), $this->xml($fecha)],
            $portada,
        );
        $zip->addFromString('ppt/slides/slide1.xml', $portada);

        $base = $zip->getFromName('ppt/slides/slide2.xml');
        $relaciones = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout8.xml"/></Relationships>';
        $tipos = $zip->getFromName('[Content_Types].xml');
        $presentacion = $zip->getFromName('ppt/presentation.xml');
        $vinculos = $zip->getFromName('ppt/_rels/presentation.xml.rels');
        $identificador = $this->siguienteIdentificador($presentacion);
        $relacion = $this->siguienteRelacion($vinculos);
        $numeroExtra = 4;

        foreach ($diapositivas as $indice => $diapositiva) {
            $xml = $this->unicos($this->diapositiva($base, $diapositiva['titulo'], $diapositiva['lineas']), $indice);

            if ($indice === 0) {
                $zip->addFromString('ppt/slides/slide2.xml', $xml);

                continue;
            }

            $numeroSlide = $numeroExtra;

            $idRelacion = 'rId'.$relacion;
            $zip->addFromString('ppt/slides/slide'.$numeroSlide.'.xml', $xml);
            $zip->addFromString('ppt/slides/_rels/slide'.$numeroSlide.'.xml.rels', $relaciones);
            $tipos = str_replace(
                '</Types>',
                '<Override PartName="/ppt/slides/slide'.$numeroSlide.'.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/></Types>',
                $tipos,
            );
            $vinculos = str_replace(
                '</Relationships>',
                '<Relationship Id="'.$idRelacion.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide'.$numeroSlide.'.xml"/></Relationships>',
                $vinculos,
            );
            $presentacion = preg_replace(
                '/(<p:sldId\b[^>]*\/>)(\s*<\/p:sldIdLst>)/',
                '<p:sldId id="'.$identificador.'" r:id="'.$idRelacion.'"/>$1$2',
                $presentacion,
                1,
            ) ?? $presentacion;
            $identificador++;
            $relacion++;
            $numeroExtra++;
        }

        $zip->addFromString('[Content_Types].xml', $tipos);
        $zip->addFromString('ppt/presentation.xml', $presentacion);
        $zip->addFromString('ppt/_rels/presentation.xml.rels', $vinculos);
        $zip->close();
    }

    private function siguienteIdentificador(string $presentacion): int
    {
        preg_match_all('/<p:sldId id="(\d+)"/', $presentacion, $ids);
        $maximo = 256;

        foreach ($ids[1] as $id) {
            $maximo = max($maximo, (int) $id);
        }

        return $maximo + 1;
    }

    private function siguienteRelacion(string $vinculos): int
    {
        preg_match_all('/Id="rId(\d+)"/', $vinculos, $ids);
        $maximo = 1;

        foreach ($ids[1] as $id) {
            $maximo = max($maximo, (int) $id);
        }

        return $maximo + 1;
    }

    /**
     * @param  list<string>  $lineas
     */
    private function diapositiva(string $base, string $titulo, array $lineas): string
    {
        $xml = str_replace('{TITULO_SLIDE}', $this->xml($titulo), $base);

        if (! preg_match('/<a:p>(?:(?!<\/a:p>).)*\{CONTENIDO_SLIDE\}.*?<\/a:p>/s', $xml, $coincidencia)) {
            return str_replace('{CONTENIDO_SLIDE}', $this->xml(implode(' ', $lineas)), $xml);
        }

        $parrafos = '';
        $items = $lineas === [] ? [''] : $lineas;

        foreach ($items as $linea) {
            $parrafos .= str_replace('{CONTENIDO_SLIDE}', $this->xml($linea), $coincidencia[0]);
        }

        return str_replace($coincidencia[0], $parrafos, $xml);
    }

    private function unicos(string $xml, int $indice): string
    {
        $xml = preg_replace_callback('/id="\{[0-9A-Fa-f-]{36}\}"/', function () use ($indice): string {
            return 'id="{'.strtoupper($this->guid($indice)).'}"';
        }, $xml) ?? $xml;

        return preg_replace('/(<p14:creationId\b[^>]*\bval=")\d+(")/', '${1}'.(100000 + $indice).'$2', $xml, 1) ?? $xml;
    }

    private function guid(int $indice): string
    {
        $bytes = md5('presentacion-'.$indice.'-'.spl_object_id($this), true);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * @param  list<string>  $cubiertos
     */
    private function incluido(string $nombre, array $cubiertos): bool
    {
        $clave = $this->clave($nombre);

        if ($clave === '') {
            return true;
        }

        foreach ($cubiertos as $cubierto) {
            if ($cubierto === $clave || (mb_strlen($clave) >= 8 && str_contains($cubierto, $clave)) || (mb_strlen($cubierto) >= 8 && str_contains($clave, $cubierto))) {
                return true;
            }
        }

        return false;
    }

    private function clave(string $nombre): string
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
}
