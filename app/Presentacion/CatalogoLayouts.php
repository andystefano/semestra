<?php

namespace App\Presentacion;

use RuntimeException;
use ZipArchive;

final class CatalogoLayouts
{
    /** @var array<string, array{nombre: string, diapositiva: int, marcas: list<string>, usa_cuando: string, tope_titulo: int, tope_cuerpo: int, tope_items: int}>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, array{nombre: string, diapositiva: int, marcas: list<string>, usa_cuando: string, tope_titulo: int, tope_cuerpo: int, tope_items: int}>
     */
    public static function todos(): array
    {
        if (self::$cache === null) {
            self::$cache = self::leer(resource_path('plantillas/Clase_Clase.pptx'));
        }

        return self::$cache;
    }

    /**
     * @return array{nombre: string, diapositiva: int, marcas: list<string>, usa_cuando: string, tope_titulo: int, tope_cuerpo: int, tope_items: int}
     */
    public static function obtener(string $nombre): array
    {
        $todos = self::todos();

        if (! isset($todos[$nombre])) {
            return $todos['definicion'] ?? throw new RuntimeException('La plantilla no tiene layouts con notas.');
        }

        return $todos[$nombre];
    }

    /**
     * @return list<array{layout: string, usa_cuando: string, marcas: list<string>, tope_titulo: int, tope_cuerpo: int, tope_items: int}>
     */
    public static function paraElegir(): array
    {
        $reservados = ['datos_aprendizaje', 'datos_criterios', 'datos_contenidos'];
        $lista = [];

        foreach (self::todos() as $layout) {
            if (in_array($layout['nombre'], $reservados, true)) {
                continue;
            }

            $lista[] = [
                'layout' => $layout['nombre'],
                'usa_cuando' => $layout['usa_cuando'],
                'marcas' => $layout['marcas'],
                'tope_titulo' => $layout['tope_titulo'],
                'tope_cuerpo' => $layout['tope_cuerpo'],
                'tope_items' => $layout['tope_items'],
            ];
        }

        return $lista;
    }

    /**
     * @param  array{nombre: string, diapositiva: int, marcas: list<string>, usa_cuando: string, tope_titulo: int, tope_cuerpo: int, tope_items: int}  $def
     * @param  array<string, mixed>  $campos
     * @return array<string, string>
     */
    public static function ajustarCampos(array $def, array $campos): array
    {
        $normalizados = [];

        foreach ($campos as $marca => $valor) {
            $clave = strtoupper(trim((string) $marca, " \t\n\r\0\x0B{}"));
            $normalizados[$clave] = trim((string) $valor);
        }

        $salida = [];

        foreach ($def['marcas'] as $marca) {
            $salida[$marca] = self::recortar($marca, $normalizados[$marca] ?? '', $def);
        }

        return $salida;
    }

    /**
     * @param  array{tope_titulo: int, tope_cuerpo: int, tope_items: int}  $def
     */
    private static function recortar(string $marca, string $valor, array $def): string
    {
        $tope = $marca === 'TITULO' ? $def['tope_titulo'] : $def['tope_cuerpo'];

        if ($tope <= 20 && $marca !== 'TITULO') {
            $lineas = preg_split('/\R/u', $valor) ?: [];
            $lineas = array_slice(array_map(
                fn (string $linea): string => mb_substr(trim($linea), 0, 80),
                $lineas,
            ), 0, $tope);

            return implode("\n", $lineas);
        }

        return mb_substr($valor, 0, max(1, $tope));
    }

    /**
     * @return array<string, array{nombre: string, diapositiva: int, marcas: list<string>, usa_cuando: string, tope_titulo: int, tope_cuerpo: int, tope_items: int}>
     */
    private static function leer(string $ruta): array
    {
        if (! is_file($ruta)) {
            throw new RuntimeException('No está la plantilla de la presentación.');
        }

        $zip = new ZipArchive;

        if ($zip->open($ruta) !== true) {
            throw new RuntimeException('No se pudo leer la plantilla de la presentación.');
        }

        $layouts = [];

        for ($numero = 1; $numero <= $zip->numFiles; $numero++) {
            $notas = $zip->getFromName('ppt/notesSlides/notesSlide'.$numero.'.xml');

            if (! is_string($notas)) {
                continue;
            }

            $relacion = $zip->getFromName('ppt/notesSlides/_rels/notesSlide'.$numero.'.xml.rels');
            $diapositiva = $numero;

            if (is_string($relacion) && preg_match('/Target="\.\.\/slides\/slide(\d+)\.xml"/', $relacion, $destino) === 1) {
                $diapositiva = (int) $destino[1];
            }

            $texto = self::texto($notas);

            if (preg_match('/LAYOUT:\s*([A-Za-z0-9_]+)/', $texto, $nombre) !== 1) {
                continue;
            }

            preg_match('/MARCAS:\s*(.+)/', $texto, $marcas);
            preg_match('/USA_CUANDO:\s*(.+)/', $texto, $uso);
            preg_match('/TOPE_TITULO:\s*(\d+)/', $texto, $topeTitulo);
            preg_match('/TOPE_CUERPO:\s*(\d+)/', $texto, $topeCuerpo);
            preg_match('/TOPE_ITEMS:\s*(\d+)/', $texto, $topeItems);
            preg_match_all('/\{([A-Z0-9_]+)\}/', $marcas[1] ?? '', $lista);

            $layouts[$nombre[1]] = [
                'nombre' => $nombre[1],
                'diapositiva' => $diapositiva,
                'marcas' => array_values(array_unique($lista[1] !== [] ? $lista[1] : ['TITULO'])),
                'usa_cuando' => trim($uso[1] ?? ''),
                'tope_titulo' => (int) ($topeTitulo[1] ?? 70),
                'tope_cuerpo' => (int) ($topeCuerpo[1] ?? 120),
                'tope_items' => max(1, (int) ($topeItems[1] ?? 4)),
            ];
        }

        $zip->close();

        if ($layouts === []) {
            throw new RuntimeException('La plantilla no trae layouts en las notas.');
        }

        return $layouts;
    }

    private static function texto(string $xml): string
    {
        preg_match_all('/<a:t[^>]*>(.*?)<\/a:t>/s', $xml, $textos);
        $lineas = [];

        foreach ($textos[1] as $texto) {
            $texto = trim(html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_XML1, 'UTF-8'));

            if ($texto !== '') {
                $lineas[] = preg_replace('/\s+/u', ' ', $texto) ?? $texto;
            }
        }

        return implode("\n", $lineas);
    }
}
