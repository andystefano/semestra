<?php

namespace App\Presentacion;

class DiapositivaTabla
{
    /**
     * @param  list<array{celdas: list<array{texto: string, lineas?: list<string>, span?: int, encabezado?: bool}>}>  $filas
     * @param  list<int>  $anchos
     */
    public function insertar(string $plantilla, string $titulo, array $filas, array $anchos): string
    {
        $xml = str_replace('{TITULO}', $this->xml($titulo), $plantilla);
        $tabla = $this->marco($filas, $anchos);
        $reemplazo = preg_replace(
            '/<p:sp><p:nvSpPr><p:cNvPr id="\d+" name="Contenido".*?<\/p:sp>/s',
            $tabla,
            $xml,
            1,
        );

        return is_string($reemplazo) ? $reemplazo : $xml;
    }

    /**
     * @param  list<array{celdas: list<array{texto: string, lineas?: list<string>, span?: int, encabezado?: bool}>}>  $filas
     * @param  list<int>  $anchos
     */
    private function marco(array $filas, array $anchos): string
    {
        $columnas = count($anchos);
        $grilla = '';

        foreach ($anchos as $ancho) {
            $grilla .= '<a:gridCol w="'.$ancho.'"/>';
        }

        $alto = (int) floor(4800600 / max(1, count($filas)));
        $cuerpo = '';

        foreach ($filas as $fila) {
            $cuerpo .= $this->fila($fila['celdas'], $columnas, $alto);
        }

        return <<<XML
<p:graphicFrame>
<p:nvGraphicFramePr><p:cNvPr id="5" name="Tabla"/><p:cNvGraphicFramePr><a:graphicFrameLocks noGrp="1"/></p:cNvGraphicFramePr><p:nvPr/></p:nvGraphicFramePr>
<p:xfrm><a:off x="1092200" y="1422400"/><a:ext cx="10363200" cy="4800600"/></p:xfrm>
<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table">
<a:tbl>
<a:tblPr firstRow="1" bandRow="0"/>
<a:tblGrid>{$grilla}</a:tblGrid>
{$cuerpo}
</a:tbl>
</a:graphicData></a:graphic>
</p:graphicFrame>
XML;
    }

    /**
     * @param  list<array{texto: string, lineas?: list<string>, span?: int, encabezado?: bool}>  $celdas
     */
    private function fila(array $celdas, int $columnas, int $alto): string
    {
        $xml = '<a:tr h="'.$alto.'">';
        $indice = 0;

        foreach ($celdas as $celda) {
            $span = max(1, (int) ($celda['span'] ?? 1));
            $xml .= $this->celda($celda, $span);
            $indice += $span;

            for ($extra = 1; $extra < $span; $extra++) {
                $xml .= $this->celda(['texto' => '', 'encabezado' => (bool) ($celda['encabezado'] ?? false)], 0, true);
            }
        }

        while ($indice < $columnas) {
            $xml .= $this->celda(['texto' => '']);
            $indice++;
        }

        return $xml.'</a:tr>';
    }

    /**
     * @param  array{texto: string, lineas?: list<string>, span?: int, encabezado?: bool}  $celda
     */
    private function celda(array $celda, int $span = 1, bool $fusionada = false): string
    {
        $encabezado = (bool) ($celda['encabezado'] ?? false);
        $fondo = $encabezado ? 'C00000' : '000000';
        $atributos = $fusionada ? ' hMerge="1"' : ($span > 1 ? ' gridSpan="'.$span.'"' : '');
        $parrafos = '';
        $lineas = $celda['lineas'] ?? [($celda['texto'] ?? '')];

        if ($lineas === []) {
            $lineas = [''];
        }

        foreach ($lineas as $linea) {
            $parrafos .= $this->parrafo((string) $linea, $encabezado);
        }

        return <<<XML
<a:tc{$atributos}>
<a:txBody>
<a:bodyPr wrap="square" rtlCol="0" anchor="ctr"/>
<a:lstStyle/>
{$parrafos}
</a:txBody>
<a:tcPr anchor="ctr">
<a:lnL w="12700"><a:solidFill><a:srgbClr val="C00000"/></a:solidFill></a:lnL>
<a:lnR w="12700"><a:solidFill><a:srgbClr val="C00000"/></a:solidFill></a:lnR>
<a:lnT w="12700"><a:solidFill><a:srgbClr val="C00000"/></a:solidFill></a:lnT>
<a:lnB w="12700"><a:solidFill><a:srgbClr val="C00000"/></a:solidFill></a:lnB>
<a:solidFill><a:srgbClr val="{$fondo}"/></a:solidFill>
</a:tcPr>
</a:tc>
XML;
    }

    private function parrafo(string $texto, bool $negrita): string
    {
        $peso = $negrita ? '1' : '0';

        return '<a:p><a:pPr marL="91440" marR="91440" algn="l"/><a:r><a:rPr lang="es-CL" sz="1200" b="'.$peso.'" dirty="0"><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill><a:latin typeface="Arial"/></a:rPr><a:t>'.$this->xml($texto).'</a:t></a:r></a:p>';
    }

    private function xml(string $texto): string
    {
        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
