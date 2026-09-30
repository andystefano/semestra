<?php

namespace App\Actions;

use App\Models\Asignatura;
use App\Models\Presentacion;
use App\Presentacion\CatalogoLayouts;
use App\Presentacion\ConstruirPlanPedagogico;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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
        $resultado = (new ConstruirPlanPedagogico)->construir(
            $asignatura,
            $bloque,
            $contenidos,
            $anteriores,
            (int) ($bloque['duracion_minutos'] ?? 0),
        );
        $archivo = $this->documento($asignatura, $fecha, $orden, $indice + 1, $bloque, $contenidos, $resultado['laminas']);

        return Presentacion::query()->updateOrCreate(
            [
                'asignatura_id' => $asignatura->id,
                'fecha' => $fecha,
                'orden' => $orden,
            ],
            [
                'contenidos' => $contenidos,
                'diapositivas' => $resultado['laminas'],
                'plan_pedagogico' => $resultado['plan'],
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
     * @param  list<array{layout: string, titulo: string, campos: array<string, string>, diapositiva: int}>  $laminas
     * @return array{path: string, nombre: string}
     */
    private function documento(
        Asignatura $asignatura,
        string $fecha,
        int $orden,
        int $numero,
        array $bloque,
        array $contenidos,
        array $laminas,
    ): array {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP no tiene la extensión ZIP. Reinicia Laragon y vuelve a intentar.');
        }

        $criterios = array_values(array_filter(array_map(
            fn (mixed $criterio): string => trim((string) $criterio),
            is_array($bloque['criterios_evaluacion'] ?? null) ? $bloque['criterios_evaluacion'] : [],
        )));
        $diapositivas = array_merge($this->fijas($bloque, $contenidos, $criterios), $laminas);

        $nombre = 'Presentacion '.$fecha.' '.$orden.'.pptx';
        $path = 'asignaturas/'.$asignatura->id.'/presentaciones/'.$fecha.'-'.$orden.'.pptx';
        Storage::disk('local')->makeDirectory('asignaturas/'.$asignatura->id.'/presentaciones');
        $destino = Storage::disk('local')->path($path);
        copy(resource_path('plantillas/Clase_Clase.pptx'), $destino);
        $this->escribirPresentacion($destino, $this->xml($asignatura->nombre), (string) $numero, Carbon::parse($fecha)->format('d/m/Y'), $diapositivas);

        return ['path' => $path, 'nombre' => $nombre];
    }

    /**
     * @param  array<string, mixed>  $bloque
     * @param  list<string>  $contenidos
     * @param  list<string>  $criterios
     * @return list<array{layout: string, titulo: string, campos: array<string, string>, diapositiva: int}>
     */
    private function fijas(array $bloque, array $contenidos, array $criterios): array
    {
        return array_merge(
            [$this->enunciados('datos_aprendizaje', 'Aprendizaje esperado', (string) ($bloque['aprendizaje_esperado'] ?? ''))],
            [$this->enunciados('datos_criterios', 'Criterios de evaluación', implode("\n", $criterios))],
            $this->fija('datos_contenidos', 'Contenidos', $contenidos === [] ? [''] : $contenidos),
        );
    }

    /**
     * @param  list<string>  $lineas
     * @return list<array{layout: string, titulo: string, campos: array<string, string>, diapositiva: int}>
     */
    private function fija(string $layout, string $titulo, array $lineas): array
    {
        $def = CatalogoLayouts::obtener($layout);
        $grupos = array_chunk($lineas, $def['tope_items']);
        $salida = [];

        foreach ($grupos as $grupo) {
            $campos = CatalogoLayouts::ajustarCampos($def, [
                'TITULO' => $titulo,
                'CONTENIDO' => implode("\n", $grupo),
            ]);
            $salida[] = [
                'layout' => $layout,
                'titulo' => $campos['TITULO'] ?? $titulo,
                'campos' => $campos,
                'diapositiva' => $def['diapositiva'],
            ];
        }

        return $salida;
    }

    /**
     * @return array{layout: string, titulo: string, campos: array<string, string>, diapositiva: int, numerada: bool}
     */
    private function enunciados(string $layout, string $titulo, string $texto): array
    {
        $def = CatalogoLayouts::obtener($layout);
        $texto = trim(str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $texto));
        $marca = '/(?<=^|\s)(?:\d+\.)+(?:\p{Pd}+\s*|\s+)(?=\p{L})/u';
        $numerada = $texto !== '' && preg_match($marca, $texto) === 1;
        $partes = $numerada ? preg_split($marca, $texto) : [$texto];
        $partes = array_values(array_filter(array_map(
            fn (string $parte): string => trim($parte),
            $partes ?: [],
        ), fn (string $parte): bool => $parte !== ''));

        if (! $numerada || $partes === []) {
            $lineas = $partes !== [] ? $partes : [''];

            return $this->fija($layout, $titulo, $lineas)[0];
        }

        $contenido = implode("\n", $partes);
        $campos = CatalogoLayouts::ajustarCampos($def, [
            'TITULO' => $titulo,
            'CONTENIDO' => $contenido,
        ]);
        $campos['CONTENIDO'] = $contenido;

        return [
            'layout' => $layout,
            'titulo' => $campos['TITULO'] ?? $titulo,
            'campos' => $campos,
            'diapositiva' => $def['diapositiva'],
            'numerada' => true,
        ];
    }

    /**
     * @param  list<array{layout: string, titulo: string, campos: array<string, string>, diapositiva: int}>  $diapositivas
     */
    private function escribirPresentacion(string $destino, string $asignatura, string $numero, string $fecha, array $diapositivas): void
    {
        $zip = new ZipArchive;

        if ($zip->open($destino) !== true) {
            throw new RuntimeException('No se pudo abrir la plantilla de la presentación.');
        }

        $plantillas = [];

        foreach ($diapositivas as $diapositiva) {
            $origen = (int) $diapositiva['diapositiva'];

            if (! isset($plantillas[$origen])) {
                $xml = $zip->getFromName('ppt/slides/slide'.$origen.'.xml');
                $rels = $zip->getFromName('ppt/slides/_rels/slide'.$origen.'.xml.rels');

                if (! is_string($xml) || ! is_string($rels)) {
                    $zip->close();
                    throw new RuntimeException('La plantilla no tiene la lámina del layout '.$diapositiva['layout'].'.');
                }

                $plantillas[$origen] = ['xml' => $xml, 'rels' => $rels];
            }
        }

        $cierreXml = $zip->getFromName('ppt/slides/slide75.xml');
        $cierreRels = $zip->getFromName('ppt/slides/_rels/slide75.xml.rels');
        $tipos = $zip->getFromName('[Content_Types].xml');
        $presentacion = $zip->getFromName('ppt/presentation.xml');
        $vinculos = $zip->getFromName('ppt/_rels/presentation.xml.rels');

        if (! is_string($cierreXml) || ! is_string($cierreRels) || ! is_string($tipos) || ! is_string($presentacion) || ! is_string($vinculos)) {
            $zip->close();
            throw new RuntimeException('La plantilla de la presentación está incompleta.');
        }

        $portada = $zip->getFromName('ppt/slides/slide1.xml');
        $portadaRels = $zip->getFromName('ppt/slides/_rels/slide1.xml.rels');
        $portada = str_replace(
            ['{NOMBRE_ASIGNATURA}', '{NUMERO_CLASE}', '{FECHA_CLASE}', '{FEHCA CLASE}'],
            [$asignatura, $this->xml($numero), $this->xml($fecha), $this->xml($fecha)],
            is_string($portada) ? $portada : '',
        );
        $zip->addFromString('ppt/slides/slide1.xml', $portada);
        $zip->addFromString('ppt/slides/_rels/slide1.xml.rels', $this->sinNotas(is_string($portadaRels) ? $portadaRels : ''));

        $cantidad = count($diapositivas);
        $cierre = 75;
        $ultimoContenido = 1 + $cantidad;

        if ($ultimoContenido >= $cierre) {
            $cierre = $ultimoContenido + 1;
        }

        $usados = [1 => true, $cierre => true];

        foreach ($diapositivas as $indice => $diapositiva) {
            $numeroSlide = $indice + 2;
            $usados[$numeroSlide] = true;
            $base = $plantillas[(int) $diapositiva['diapositiva']];
            $zip->addFromString('ppt/slides/slide'.$numeroSlide.'.xml', $this->unicos($this->aplicarCampos($base['xml'], $diapositiva['campos'], (bool) ($diapositiva['numerada'] ?? false)), $indice));
            $zip->addFromString('ppt/slides/_rels/slide'.$numeroSlide.'.xml.rels', $this->sinNotas($base['rels']));
        }

        if ($cierre !== 75) {
            $zip->addFromString('ppt/slides/slide'.$cierre.'.xml', $cierreXml);
            $this->asegurarRelacion($vinculos, $tipos, $cierre);
        }

        $zip->addFromString('ppt/slides/_rels/slide'.$cierre.'.xml.rels', $this->sinNotas($cierreRels));

        for ($numeroSlide = 2; $numeroSlide <= 75; $numeroSlide++) {
            if (isset($usados[$numeroSlide])) {
                continue;
            }

            $zip->deleteName('ppt/slides/slide'.$numeroSlide.'.xml');
            $zip->deleteName('ppt/slides/_rels/slide'.$numeroSlide.'.xml.rels');
            $tipos = preg_replace('#<Override PartName="/ppt/slides/slide'.$numeroSlide.'\.xml"[^>]*/>#', '', $tipos) ?? $tipos;
            $vinculos = preg_replace('#<Relationship\b[^>]*Target="slides/slide'.$numeroSlide.'\.xml"[^>]*/>#', '', $vinculos) ?? $vinculos;
        }

        for ($nota = 1; $nota <= 75; $nota++) {
            $zip->deleteName('ppt/notesSlides/notesSlide'.$nota.'.xml');
            $zip->deleteName('ppt/notesSlides/_rels/notesSlide'.$nota.'.xml.rels');
        }

        $tipos = preg_replace('#<Override PartName="/ppt/notesSlides/notesSlide\d+\.xml"[^>]*/>#', '', $tipos) ?? $tipos;
        $presentacion = $this->ordenar($presentacion, $vinculos, $cantidad, $cierre);
        $propiedades = $zip->getFromName('docProps/app.xml');
        $zip->addFromString('[Content_Types].xml', $tipos);
        $zip->addFromString('ppt/presentation.xml', $presentacion);
        $zip->addFromString('ppt/_rels/presentation.xml.rels', $vinculos);

        if (is_string($propiedades)) {
            $titulos = array_merge(
                ['Portada'],
                array_map(fn (array $diapositiva): string => $diapositiva['titulo'] !== '' ? $diapositiva['titulo'] : 'Diapositiva', $diapositivas),
                ['Cierre'],
            );
            $zip->addFromString('docProps/app.xml', $this->propiedades($propiedades, $titulos));
        }

        $zip->close();
    }

    private function asegurarRelacion(string &$vinculos, string &$tipos, int $numero): void
    {
        if (! str_contains($vinculos, 'Target="slides/slide'.$numero.'.xml"')) {
            $relacion = $this->siguienteRelacion($vinculos);
            $vinculos = str_replace(
                '</Relationships>',
                '<Relationship Id="rId'.$relacion.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide'.$numero.'.xml"/></Relationships>',
                $vinculos,
            );
        }

        if (! str_contains($tipos, 'PartName="/ppt/slides/slide'.$numero.'.xml"')) {
            $tipos = str_replace(
                '</Types>',
                '<Override PartName="/ppt/slides/slide'.$numero.'.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/></Types>',
                $tipos,
            );
        }
    }

    private function ordenar(string $presentacion, string $vinculos, int $cantidad, int $cierre): string
    {
        preg_match_all('/<p:sldId id="(\d+)" r:id="(rId\d+)"\/>/', $presentacion, $ids, PREG_SET_ORDER);
        $idPorRelacion = [];

        foreach ($ids as $id) {
            $idPorRelacion[$id[2]] = $id[1];
        }

        $relacionPorSlide = [];
        preg_match_all('/Id="(rId\d+)"[^>]*Target="slides\/slide(\d+)\.xml"/', $vinculos, $rels, PREG_SET_ORDER);

        foreach ($rels as $rel) {
            $relacionPorSlide[(int) $rel[2]] = $rel[1];
        }

        $orden = [1];

        for ($numero = 2; $numero <= 1 + $cantidad; $numero++) {
            $orden[] = $numero;
        }

        $orden[] = $cierre;
        $lista = '';
        $identificador = $this->siguienteIdentificador($presentacion);

        foreach ($orden as $numero) {
            $relacion = $relacionPorSlide[$numero] ?? null;

            if ($relacion === null) {
                continue;
            }

            $id = $idPorRelacion[$relacion] ?? (string) $identificador++;
            $lista .= '<p:sldId id="'.$id.'" r:id="'.$relacion.'"/>';
        }

        return preg_replace('/<p:sldIdLst>.*?<\/p:sldIdLst>/s', '<p:sldIdLst>'.$lista.'</p:sldIdLst>', $presentacion) ?? $presentacion;
    }

    /**
     * @param  array<string, string>  $campos
     */
    private function aplicarCampos(string $xml, array $campos, bool $numerada = false): string
    {
        if ($numerada && isset($campos['CONTENIDO'])) {
            $xml = $this->numerar($xml, 'CONTENIDO', explode("\n", $campos['CONTENIDO']));
            unset($campos['CONTENIDO']);
        }

        foreach ($campos as $marca => $valor) {
            $lineas = explode("\n", str_replace("\r", '', $valor));
            $reemplazo = preg_replace_callback(
                '/(<a:r\b[^>]*>)(.*?)(<a:t[^>]*>)\{'.preg_quote($marca, '/').'\}(<\/a:t><\/a:r>)/s',
                function (array $coincidencia) use ($lineas): string {
                    $partes = [];

                    foreach ($lineas as $indice => $linea) {
                        if ($indice > 0) {
                            $partes[] = '<a:br/>';
                        }

                        $partes[] = $coincidencia[1].$coincidencia[2].$coincidencia[3].$this->xml($linea).$coincidencia[4];
                    }

                    return implode('', $partes);
                },
                $xml,
                1,
            );

            $xml = is_string($reemplazo) ? $reemplazo : $xml;

            if (str_contains($xml, '{'.$marca.'}')) {
                $xml = str_replace('{'.$marca.'}', $this->xml(implode(' ', $lineas)), $xml);
            }
        }

        return $xml;
    }

    /**
     * @param  list<string>  $lineas
     */
    private function numerar(string $xml, string $marca, array $lineas): string
    {
        $token = '{'.$marca.'}';

        if (preg_match('/<a:p\b(?:(?!<\/a:p>).)*'.preg_quote($token, '/').'.*?<\/a:p>/s', $xml, $coincidencia) !== 1) {
            return str_replace($token, $this->xml(implode(' ', $lineas)), $xml);
        }

        $parrafos = '';

        foreach ($lineas as $linea) {
            $linea = trim($linea);

            if ($linea === '') {
                continue;
            }

            $parrafo = str_replace($token, $this->xml($linea), $coincidencia[0]);
            if (str_contains($parrafo, '<a:buChar')) {
                $parrafo = preg_replace('/<a:buChar\b[^>]*\/>/', '<a:buAutoNum type="arabicPeriod"/>', $parrafo, 1) ?? $parrafo;
            } else {
                $parrafo = preg_replace('/<a:buNone\/>/', '<a:buClr><a:schemeClr val="bg1"/></a:buClr><a:buFont typeface="Arial"/><a:buAutoNum type="arabicPeriod"/>', $parrafo, 1) ?? $parrafo;
            }
            $parrafo = preg_replace('/(<a:pPr\b[^>]*\bmarL=")[^"]+(")/', '${1}285750$2', $parrafo, 1) ?? $parrafo;
            $parrafo = preg_replace('/(<a:pPr\b[^>]*\bindent=")[^"]+(")/', '${1}-285750$2', $parrafo, 1) ?? $parrafo;
            $parrafo = preg_replace('/\bsz="\d+"/', 'sz="'.$this->tamanoLista(count($lineas)).'"', $parrafo) ?? $parrafo;
            $parrafos .= $parrafo;
        }

        $xml = str_replace($coincidencia[0], $parrafos !== '' ? $parrafos : $coincidencia[0], $xml);

        return str_replace('<a:noAutofit/>', '<a:normAutofit/>', $xml);
    }

    private function tamanoLista(int $cantidad): string
    {
        return match (true) {
            $cantidad >= 12 => '1000',
            $cantidad >= 8 => '1100',
            $cantidad >= 4 => '1200',
            $cantidad === 3 => '1400',
            $cantidad === 2 => '1600',
            default => '2000',
        };
    }

    private function propiedades(string $xml, array $titulos): string
    {
        preg_match_all('/<vt:i4>(\d+)<\/vt:i4>/', $xml, $cantidades);
        $conservar = (int) ($cantidades[1][0] ?? 0) + (int) ($cantidades[1][1] ?? 0);
        preg_match('/<TitlesOfParts><vt:vector size="\d+" baseType="lpstr">(.*?)<\/vt:vector><\/TitlesOfParts>/s', $xml, $bloque);
        preg_match_all('/<vt:lpstr>(.*?)<\/vt:lpstr>/', $bloque[1] ?? '', $nombres);
        $partes = array_map(
            fn (string $nombre): string => html_entity_decode($nombre, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            array_slice($nombres[1], 0, $conservar),
        );
        $partes = array_merge($partes, $titulos);
        $xml = preg_replace('/<Slides>\d+<\/Slides>/', '<Slides>'.count($titulos).'</Slides>', $xml, 1) ?? $xml;
        $xml = preg_replace('/<Notes>\d+<\/Notes>/', '<Notes>0</Notes>', $xml, 1) ?? $xml;
        $xml = preg_replace(
            '/(<vt:lpstr>(?:Títulos de diapositiva|Slide titles)<\/vt:lpstr><\/vt:variant><vt:variant><vt:i4>)\d+/',
            '${1}'.count($titulos),
            $xml,
            1,
        ) ?? $xml;
        $lista = '';

        foreach ($partes as $parte) {
            $lista .= '<vt:lpstr>'.$this->xml($parte).'</vt:lpstr>';
        }

        return preg_replace(
            '/<TitlesOfParts><vt:vector size="\d+" baseType="lpstr">.*?<\/vt:vector><\/TitlesOfParts>/s',
            '<TitlesOfParts><vt:vector size="'.count($partes).'" baseType="lpstr">'.$lista.'</vt:vector></TitlesOfParts>',
            $xml,
            1,
        ) ?? $xml;
    }

    private function sinNotas(string $rels): string
    {
        return preg_replace('#<Relationship\b[^>]*notesSlide[^>]*/>#', '', $rels) ?? $rels;
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

    private function xml(string $texto): string
    {
        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
