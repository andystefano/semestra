<?php

namespace App\Presentacion;

use App\Models\Asignatura;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ConstruirPlanPedagogico
{
    /**
     * @param  array<string, mixed>  $bloque
     * @param  list<string>  $contenidos
     * @param  list<string>  $anteriores
     * @return array{plan: array<string, mixed>, laminas: list<array{layout: string, titulo: string, campos: array<string, string>, diapositiva: int}>}
     */
    public function construir(Asignatura $asignatura, array $bloque, array $contenidos, array $anteriores, int $minutos): array
    {
        $entrada = [
            'asignatura' => $asignatura->nombre,
            'unidad' => $bloque['unidad'] ?? null,
            'minutos' => $minutos,
            'aprendizaje_esperado' => $bloque['aprendizaje_esperado'] ?? '',
            'criterios_evaluacion' => $bloque['criterios_evaluacion'] ?? [],
            'contenidos_obligatorios' => $contenidos,
            'contenidos_clase_anterior' => $anteriores,
            'estrategias' => EstrategiasDidacticas::LISTA,
            'recursos' => RecursosPedagogicos::LISTA,
            'layouts' => CatalogoLayouts::paraElegir(),
        ];

        $contexto = $this->contexto($entrada, $anteriores === []);
        $bloques = $this->cubrir($this->bloques($entrada), $contenidos);
        $bloques = $this->objetivos($entrada, $bloques);
        $bloques = $this->estrategias($entrada, $bloques);
        $bloques = $this->recursos($entrada, $bloques);
        $bloques = $this->estructura($entrada, $bloques);
        $eleccion = $this->elegir($entrada, $bloques, $contexto);
        $diapositivas = $this->rellenar($entrada, $bloques, $contexto, $eleccion);
        $fallos = $this->fallos($diapositivas, $contenidos, (string) ($bloque['aprendizaje_esperado'] ?? ''), $bloques);

        if ($fallos !== []) {
            $diapositivas = $this->rellenar($entrada, $bloques, $contexto, $eleccion, $diapositivas, $fallos);
            $fallos = $this->fallos($diapositivas, $contenidos, (string) ($bloque['aprendizaje_esperado'] ?? ''), $bloques);
        }

        return [
            'plan' => [
                'contexto' => $contexto,
                'bloques' => $bloques,
                'diapositivas' => $diapositivas,
                'advertencias' => $fallos,
            ],
            'laminas' => $this->laminas($diapositivas),
        ];
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return array{resumen: string, recordar: list<array<string, mixed>>}
     */
    private function contexto(array $entrada, bool $primera): array
    {
        $datos = $this->pedir(
            'Resume la clase en una frase. Si hay clase anterior, devuelve una o dos diapositivas breves que la recuerden. Si es la primera, una sola diapositiva que lo diga. JSON: {"resumen":"","recordar":[{"titulo":"","puntos":[""]}]}',
            $entrada,
        );
        $recordar = is_array($datos['recordar'] ?? null) ? array_values($datos['recordar']) : [];

        if ($recordar === []) {
            $recordar = [[
                'titulo' => $primera ? 'Primera clase' : 'Clase anterior',
                'puntos' => [$primera ? 'No hay una clase anterior.' : 'Retomar lo visto en la clase anterior.'],
            ]];
        }

        return [
            'resumen' => trim((string) ($datos['resumen'] ?? '')),
            'recordar' => array_slice($recordar, 0, $primera ? 1 : 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return list<array<string, mixed>>
     */
    private function bloques(array $entrada): array
    {
        $datos = $this->pedir(
            'Agrupa los contenidos obligatorios en bloques conceptuales, no en un título suelto por cada ítem. Cada contenido queda en un solo bloque. JSON: {"bloques":[{"nombre":"","contenidos":[""]}]}',
            $entrada,
        );

        return is_array($datos['bloques'] ?? null) ? array_values($datos['bloques']) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $bloques
     * @param  list<string>  $contenidos
     * @return list<array<string, mixed>>
     */
    private function cubrir(array $bloques, array $contenidos): array
    {
        $usados = [];
        $limpios = [];

        foreach ($bloques as $bloque) {
            if (! is_array($bloque)) {
                continue;
            }

            $asignados = [];

            foreach ($this->textos($bloque['contenidos'] ?? []) as $contenido) {
                foreach ($contenidos as $obligatorio) {
                    if ($this->incluido($obligatorio, [$this->clave($contenido)]) && ! in_array($obligatorio, $usados, true)) {
                        $asignados[] = $obligatorio;
                        $usados[] = $obligatorio;
                    }
                }
            }

            if ($asignados !== []) {
                $bloque['contenidos'] = $asignados;
                $limpios[] = $bloque;
            }
        }

        $pendientes = array_values(array_diff($contenidos, $usados));

        if ($pendientes !== []) {
            $limpios[] = ['nombre' => 'Otros contenidos', 'contenidos' => $pendientes];
        }

        return $limpios;
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @param  list<array<string, mixed>>  $bloques
     * @return list<array<string, mixed>>
     */
    private function objetivos(array $entrada, array $bloques): array
    {
        $datos = $this->pedir(
            'Para cada bloque define un objetivo de capacidad, adaptando el aprendizaje esperado de la clase sin cambiar el tema. nivel es conocer, comprender o aplicar. JSON: {"objetivos":[{"bloque":"","objetivo":"","nivel":"comprender"}]}',
            $entrada + ['bloques' => $bloques],
        );

        return $this->unir($bloques, $this->filas($datos['objetivos'] ?? []), 'objetivo', [
            'objetivo' => (string) ($entrada['aprendizaje_esperado'] ?? ''),
            'nivel' => 'comprender',
        ]);
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @param  list<array<string, mixed>>  $bloques
     * @return list<array<string, mixed>>
     */
    private function estrategias(array $entrada, array $bloques): array
    {
        $datos = $this->pedir(
            'Elige una o dos estrategias del catálogo para cada bloque. Si propones otra, llena justificacion. JSON: {"estrategias":[{"bloque":"","estrategias":["definicion"],"justificacion":""}]}',
            $entrada + ['bloques' => $bloques],
        );

        return $this->unir($bloques, $this->filas($datos['estrategias'] ?? []), 'estrategias', [
            'estrategias' => ['definicion'],
            'justificacion_estrategia' => '',
        ], function (array $bloque, array $fila): array {
            $elegidas = array_slice($this->textos($fila['estrategias'] ?? []), 0, 2);
            $porque = trim((string) ($fila['justificacion'] ?? ''));

            if (! EstrategiasDidacticas::acepta($elegidas, $porque)) {
                $elegidas = ['definicion'];
                $porque = '';
            }

            $bloque['estrategias'] = $elegidas;
            $bloque['justificacion_estrategia'] = $porque;

            return $bloque;
        });
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @param  list<array<string, mixed>>  $bloques
     * @return list<array<string, mixed>>
     */
    private function recursos(array $entrada, array $bloques): array
    {
        $datos = $this->pedir(
            'Elige uno o dos recursos del catálogo para cada bloque, distintos de la estrategia. Si propones otro, llena justificacion. JSON: {"recursos":[{"bloque":"","recursos":["texto"],"justificacion":""}]}',
            $entrada + ['bloques' => $bloques],
        );

        return $this->unir($bloques, $this->filas($datos['recursos'] ?? []), 'recursos', [
            'recursos' => ['texto'],
            'justificacion_recurso' => '',
        ], function (array $bloque, array $fila): array {
            $elegidos = array_values(array_diff(
                array_slice($this->textos($fila['recursos'] ?? []), 0, 2),
                $bloque['estrategias'] ?? [],
            ));
            $porque = trim((string) ($fila['justificacion'] ?? ''));

            if (! RecursosPedagogicos::acepta($elegidos, $porque)) {
                $elegidos = in_array('texto', $bloque['estrategias'] ?? [], true) ? ['diagrama'] : ['texto'];
                $porque = '';
            }

            $bloque['recursos'] = $elegidos;
            $bloque['justificacion_recurso'] = $porque;

            return $bloque;
        });
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @param  list<array<string, mixed>>  $bloques
     * @return list<array<string, mixed>>
     */
    private function estructura(array $entrada, array $bloques): array
    {
        $datos = $this->pedir(
            'Diseña la secuencia de aprendizaje de cada bloque, todavía sin diapositivas. JSON: {"estructuras":[{"bloque":"","secuencia":[{"momento":"","descripcion":""}]}]}',
            $entrada + ['bloques' => $bloques],
        );

        return $this->unir($bloques, $this->filas($datos['estructuras'] ?? []), 'secuencia', [
            'secuencia' => [['momento' => 'explicar', 'descripcion' => 'Explicar el contenido y mostrar un ejemplo.']],
        ]);
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @param  list<array<string, mixed>>  $bloques
     * @param  array{resumen: string, recordar: list<array<string, mixed>>}  $contexto
     * @return list<array{bloque: string, parte: string, layout: string}>
     */
    private function elegir(array $entrada, array $bloques, array $contexto): array
    {
        $datos = $this->pedir(
            'Elige el layout más indicado para cada parte de la clase. Puedes usar más de un layout en el mismo bloque si hace falta. Usa solo nombres del catálogo. No uses datos_aprendizaje, datos_criterios ni datos_contenidos: esas láminas ya van fijas. Incluye el recuerdo inicial si corresponde. JSON: {"diapositivas":[{"bloque":"","parte":"","layout":""}]}',
            $entrada + ['bloques' => $bloques, 'contexto' => $contexto, 'layouts' => CatalogoLayouts::paraElegir()],
        );

        $eleccion = [];

        foreach ($this->filas($datos['diapositivas'] ?? []) as $fila) {
            $nombre = trim((string) ($fila['layout'] ?? ''));

            if (! isset(CatalogoLayouts::todos()[$nombre]) || in_array($nombre, ['datos_aprendizaje', 'datos_criterios', 'datos_contenidos'], true)) {
                continue;
            }

            $eleccion[] = [
                'bloque' => trim((string) ($fila['bloque'] ?? '')),
                'parte' => trim((string) ($fila['parte'] ?? '')),
                'layout' => $nombre,
            ];
        }

        if ($eleccion === []) {
            foreach ($bloques as $bloque) {
                $eleccion[] = [
                    'bloque' => (string) ($bloque['nombre'] ?? ''),
                    'parte' => 'explicar',
                    'layout' => 'definicion',
                ];
            }
        }

        return $eleccion;
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @param  list<array<string, mixed>>  $bloques
     * @param  array{resumen: string, recordar: list<array<string, mixed>>}  $contexto
     * @param  list<array{bloque: string, parte: string, layout: string}>  $eleccion
     * @param  list<array<string, mixed>>  $anteriores
     * @param  list<string>  $fallos
     * @return list<array<string, mixed>>
     */
    private function rellenar(array $entrada, array $bloques, array $contexto, array $eleccion, array $anteriores = [], array $fallos = []): array
    {
        $pedido = [];

        foreach ($eleccion as $indice => $item) {
            $def = CatalogoLayouts::obtener($item['layout']);
            $pedido[] = [
                'bloque' => $item['bloque'],
                'parte' => $item['parte'],
                'layout' => $item['layout'],
                'marcas' => $def['marcas'],
                'tope_titulo' => $def['tope_titulo'],
                'tope_cuerpo' => $def['tope_cuerpo'],
                'texto_previo' => $anteriores[$indice]['campos'] ?? [],
            ];
        }

        $instruccion = 'Redacta cada diapositiva en español, breve, solo con las marcas de su layout. Respeta los topes. Cubre los contenidos obligatorios. JSON: {"diapositivas":[{"bloque":"","layout":"","campos":{"TITULO":""}}]}';

        if ($fallos !== []) {
            $instruccion = 'Corrige estas diapositivas según los fallos, sin cambiar el layout de cada una. Cubre todos los contenidos obligatorios. '.$instruccion;
        }

        $datos = $this->pedir($instruccion, $entrada + [
            'bloques' => $bloques,
            'contexto' => $contexto,
            'diapositivas' => $pedido,
            'fallos' => $fallos,
        ]);

        return $this->normalizar($eleccion, $this->filas($datos['diapositivas'] ?? []));
    }

    /**
     * @param  list<array{bloque: string, parte: string, layout: string}>  $eleccion
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function normalizar(array $eleccion, array $filas): array
    {
        $diapositivas = [];

        foreach ($eleccion as $indice => $item) {
            $fila = $filas[$indice] ?? [];
            $layout = trim((string) ($fila['layout'] ?? $item['layout']));

            if (! isset(CatalogoLayouts::todos()[$layout])) {
                $layout = $item['layout'];
            }

            $def = CatalogoLayouts::obtener($layout);
            $campos = is_array($fila['campos'] ?? null) ? $fila['campos'] : [];

            $diapositivas[] = [
                'bloque' => trim((string) ($fila['bloque'] ?? $item['bloque'])),
                'parte' => $item['parte'],
                'layout' => $layout,
                'diapositiva' => $def['diapositiva'],
                'campos' => CatalogoLayouts::ajustarCampos($def, $campos),
            ];
        }

        return $diapositivas;
    }

    /**
     * @param  list<array<string, mixed>>  $diapositivas
     * @param  list<string>  $contenidos
     * @param  list<array<string, mixed>>  $bloques
     * @return list<string>
     */
    private function fallos(array $diapositivas, array $contenidos, string $aprendizaje, array $bloques): array
    {
        $texto = '';

        foreach ($diapositivas as $diapositiva) {
            $texto .= ' '.implode(' ', $diapositiva['campos'] ?? []);
        }

        $fallos = [];

        foreach ($contenidos as $contenido) {
            if (! $this->incluido($contenido, [$this->clave($texto)])) {
                $fallos[] = "Falta cubrir el contenido \"{$contenido}\" en las diapositivas.";
            }
        }

        if (preg_match('/aplic/ui', $aprendizaje) === 1) {
            $aplica = false;

            foreach ($bloques as $bloque) {
                if (($bloque['nivel'] ?? '') === 'aplicar') {
                    $aplica = true;
                }
            }

            if (! $aplica) {
                $fallos[] = 'La clase pide aplicar y ningún bloque tiene nivel aplicar.';
            }
        }

        return $fallos;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array{layout: string, titulo: string, campos: array<string, string>, diapositiva: int}>
     */
    private function laminas(array $filas): array
    {
        $laminas = [];

        foreach ($filas as $fila) {
            $campos = is_array($fila['campos'] ?? null) ? $fila['campos'] : [];
            $laminas[] = [
                'layout' => (string) ($fila['layout'] ?? 'definicion'),
                'titulo' => (string) ($campos['TITULO'] ?? ''),
                'campos' => $campos,
                'diapositiva' => (int) ($fila['diapositiva'] ?? 0),
            ];
        }

        return $laminas;
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return array<string, mixed>
     */
    private function pedir(string $sistema, array $entrada): array
    {
        $clave = config('services.deepseek.key');

        if (! is_string($clave) || $clave === '') {
            throw new RuntimeException('Falta la clave de DeepSeek.');
        }

        set_time_limit(300);

        $cliente = Http::withToken($clave)->acceptJson()->connectTimeout(10)->timeout(180);
        $certificado = config('services.deepseek.ca');

        if (is_string($certificado) && $certificado !== '' && is_file($certificado)) {
            $cliente = $cliente->withOptions(['verify' => $certificado]);
        }

        try {
            $respuesta = $cliente->post(rtrim((string) config('services.deepseek.url'), '/').'/chat/completions', [
                'model' => config('services.deepseek.model'),
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $sistema],
                    ['role' => 'user', 'content' => json_encode($entrada, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
                ],
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('No se pudo conectar con DeepSeek.');
        }

        $contenido = $respuesta->json('choices.0.message.content');

        if ($respuesta->failed() || ! is_string($contenido) || trim($contenido) === '') {
            throw new RuntimeException('DeepSeek no pudo continuar el plan de la clase.');
        }

        $contenido = preg_replace('/^```(?:json)?\s*|\s*```$/s', '', trim($contenido)) ?? $contenido;
        $datos = json_decode($contenido, true);

        if (! is_array($datos)) {
            throw new RuntimeException('DeepSeek no devolvió un plan válido.');
        }

        return $datos;
    }

    /**
     * @param  list<array<string, mixed>>  $bloques
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $defecto
     * @return list<array<string, mixed>>
     */
    private function unir(array $bloques, array $filas, string $campo, array $defecto, ?callable $mezclar = null): array
    {
        foreach ($bloques as $indice => $bloque) {
            $fila = $this->filaDe($filas, (string) ($bloque['nombre'] ?? ''));

            if ($mezclar !== null && $fila !== []) {
                $bloques[$indice] = $mezclar($bloque, $fila);

                continue;
            }

            if ($fila !== [] && array_key_exists($campo, $fila)) {
                $bloques[$indice][$campo] = $fila[$campo];

                if (isset($fila['nivel'])) {
                    $bloques[$indice]['nivel'] = $fila['nivel'];
                }

                if (isset($fila['objetivo'])) {
                    $bloques[$indice]['objetivo'] = $fila['objetivo'];
                }
            }

            foreach ($defecto as $llave => $valor) {
                if (! isset($bloques[$indice][$llave]) || $bloques[$indice][$llave] === '' || $bloques[$indice][$llave] === []) {
                    $bloques[$indice][$llave] = $valor;
                }
            }
        }

        return $bloques;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function filaDe(array $filas, string $nombre): array
    {
        foreach ($filas as $fila) {
            if ($this->clave((string) ($fila['bloque'] ?? $fila['nombre'] ?? '')) === $this->clave($nombre)) {
                return $fila;
            }
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filas(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        return array_values(array_filter($valor, 'is_array'));
    }

    /**
     * @return list<string>
     */
    private function textos(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): string => trim((string) $item),
            $valor,
        )));
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
}
