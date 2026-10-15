<?php

namespace App\Presentacion;

use App\Models\Asignatura;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ConstruirPlanPedagogico
{
    /** Layouts de apertura, cierre o recuerdo: no tratan un contenido propio. */
    private const APOYO = ['recuerdo', 'seccion', 'objetivo', 'sintesis', 'agenda_bloques', 'pausa'];

    /** Diapositivas por pedido al llenar, para no pasar el tope de salida del modelo. */
    private const POR_PEDIDO = 8;

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
        $eleccion = $this->asegurar($this->elegir($entrada, $bloques, $contexto), $bloques, $contexto);
        $diapositivas = $this->rellenar($entrada, $bloques, $contexto, $eleccion);
        $fallos = $this->fallos($diapositivas, $contenidos, (string) ($bloque['aprendizaje_esperado'] ?? ''), $bloques);

        if ($fallos !== []) {
            $diapositivas = $this->rellenar($entrada, $bloques, $contexto, $eleccion, $diapositivas, $fallos, $this->bloquesSinCubrir($diapositivas, $bloques));
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
     * @return list<array{bloque: string, contenido: string, parte: string, layout: string}>
     */
    private function elegir(array $entrada, array $bloques, array $contexto): array
    {
        $cantidad = count($entrada['contenidos_obligatorios'] ?? []);
        $extra = 2 * count($bloques) + count($contexto['recordar']);

        $datos = $this->pedir(
            'Arma la secuencia de diapositivas de la clase. Cada contenido obligatorio necesita entre 2 y 3 diapositivas propias (primero explicarlo y luego un ejemplo, aplicación o actividad); no juntes varios contenidos en una misma diapositiva. Cada bloque abre con seccion u objetivo y cierra con sintesis o actividad. El recuerdo de la clase anterior va solo al inicio. El total de diapositivas debe quedar entre presupuesto.min y presupuesto.max. Usa solo nombres del catálogo. No uses datos_aprendizaje, datos_criterios ni datos_contenidos: esas láminas ya van fijas. En contenido escribe el nombre exacto del contenido obligatorio que trata la diapositiva, o déjalo vacío si es apertura, cierre o recuerdo. JSON: {"diapositivas":[{"bloque":"","contenido":"","parte":"","layout":""}]}',
            $entrada + [
                'bloques' => $bloques,
                'contexto' => $contexto,
                'layouts' => CatalogoLayouts::paraElegir(),
                'presupuesto' => ['min' => 2 * $cantidad + $extra, 'max' => 3 * $cantidad + $extra],
            ],
        );

        $eleccion = [];

        foreach ($this->filas($datos['diapositivas'] ?? []) as $fila) {
            $nombre = trim((string) ($fila['layout'] ?? ''));

            if (! isset(CatalogoLayouts::todos()[$nombre]) || in_array($nombre, ['datos_aprendizaje', 'datos_criterios', 'datos_contenidos'], true)) {
                continue;
            }

            $eleccion[] = [
                'bloque' => trim((string) ($fila['bloque'] ?? '')),
                'contenido' => trim((string) ($fila['contenido'] ?? '')),
                'parte' => trim((string) ($fila['parte'] ?? '')),
                'layout' => $nombre,
            ];
        }

        return $eleccion;
    }

    /**
     * Deja cada diapositiva en un bloque real, el recuerdo solo al inicio y al menos dos diapositivas por contenido.
     *
     * @param  list<array{bloque: string, contenido: string, parte: string, layout: string}>  $eleccion
     * @param  list<array<string, mixed>>  $bloques
     * @param  array{resumen: string, recordar: list<array<string, mixed>>}  $contexto
     * @return list<array{bloque: string, contenido: string, parte: string, layout: string}>
     */
    private function asegurar(array $eleccion, array $bloques, array $contexto): array
    {
        $limpia = [];
        $recuerdos = 0;

        foreach ($eleccion as $item) {
            if ($item['layout'] === 'recuerdo') {
                if ($recuerdos !== count($limpia) || $recuerdos >= count($contexto['recordar'])) {
                    continue;
                }

                $recuerdos++;
            }

            $contenido = '';
            $indice = null;

            foreach ($bloques as $posicion => $bloque) {
                foreach ($this->textos($bloque['contenidos'] ?? []) as $obligatorio) {
                    if ($item['contenido'] !== '' && $this->incluido($obligatorio, [$this->clave($item['contenido'])])) {
                        $contenido = $obligatorio;
                        $indice = $posicion;

                        break 2;
                    }
                }
            }

            if ($indice === null) {
                $indice = 0;

                foreach ($bloques as $posicion => $bloque) {
                    if ($this->clave((string) ($bloque['nombre'] ?? '')) === $this->clave($item['bloque'])) {
                        $indice = $posicion;

                        break;
                    }
                }
            }

            $propios = $this->textos($bloques[$indice]['contenidos'] ?? []);

            if ($contenido === '' && count($propios) === 1 && ! in_array($item['layout'], self::APOYO, true)) {
                $contenido = $propios[0];
            }

            $item['bloque'] = (string) ($bloques[$indice]['nombre'] ?? '');
            $item['contenido'] = $contenido;
            $limpia[] = $item;
        }

        foreach ($bloques as $bloque) {
            $nombre = (string) ($bloque['nombre'] ?? '');

            foreach ($this->textos($bloque['contenidos'] ?? []) as $contenido) {
                $tiene = count(array_filter($limpia, fn (array $item): bool => $item['contenido'] === $contenido));

                foreach (array_slice(['definicion', 'ejemplo_resuelto'], $tiene) as $layout) {
                    array_splice($limpia, $this->posicion($limpia, $nombre, $contenido), 0, [[
                        'bloque' => $nombre,
                        'contenido' => $contenido,
                        'parte' => $layout === 'definicion' ? 'explicar' : 'ejemplo',
                        'layout' => $layout,
                    ]]);
                }
            }
        }

        return $limpia;
    }

    /**
     * Lugar donde insertar una diapositiva nueva del contenido: tras las suyas, antes de la síntesis del bloque o al final del bloque.
     *
     * @param  list<array{bloque: string, contenido: string, parte: string, layout: string}>  $eleccion
     */
    private function posicion(array $eleccion, string $bloque, string $contenido): int
    {
        $ultima = null;
        $sintesis = null;
        $final = null;

        foreach ($eleccion as $indice => $item) {
            if ($item['contenido'] === $contenido) {
                $ultima = $indice;
            }

            if ($item['bloque'] === $bloque) {
                $final = $indice;

                if ($sintesis === null && $item['layout'] === 'sintesis') {
                    $sintesis = $indice;
                }
            }
        }

        return match (true) {
            $ultima !== null => $ultima + 1,
            $sintesis !== null => $sintesis,
            $final !== null => $final + 1,
            default => count($eleccion),
        };
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @param  list<array<string, mixed>>  $bloques
     * @param  array{resumen: string, recordar: list<array<string, mixed>>}  $contexto
     * @param  list<array{bloque: string, contenido: string, parte: string, layout: string}>  $eleccion
     * @param  list<array<string, mixed>>  $anteriores
     * @param  list<string>  $fallos
     * @param  list<string>|null  $solo  bloques a rehacer; null rehace todos
     * @return list<array<string, mixed>>
     */
    private function rellenar(array $entrada, array $bloques, array $contexto, array $eleccion, array $anteriores = [], array $fallos = [], ?array $solo = null): array
    {
        $instruccion = 'Redacta en español cada diapositiva de este bloque, solo con las marcas de su layout. En la diapositiva van frases completas y concisas que quepan en los topes (tope_titulo y tope_cuerpo son caracteres por marca); no cortes ideas. Cada diapositiva trata el contenido indicado y en conjunto cubren todos los contenidos del bloque. En notas escribe las notas del orador para el docente, entre 120 y 250 palabras: explicación desarrollada del contenido, un ejemplo concreto, una pregunta para el curso y la transición a la siguiente diapositiva. JSON: {"diapositivas":[{"bloque":"","contenido":"","layout":"","campos":{"TITULO":""},"notas":""}]}';

        if ($fallos !== []) {
            $instruccion = 'Corrige estas diapositivas según los fallos, sin cambiar el layout de cada una. Cubre todos los contenidos del bloque. '.$instruccion;
        }

        $base = array_diff_key($entrada, array_flip(['layouts', 'estrategias', 'recursos']));
        $pedidos = [];
        $tandas = [];

        foreach ($bloques as $bloque) {
            $nombre = (string) ($bloque['nombre'] ?? '');

            if ($solo !== null && ! in_array($nombre, $solo, true)) {
                continue;
            }

            $indices = array_keys(array_filter($eleccion, fn (array $item): bool => $item['bloque'] === $nombre));

            foreach (array_chunk($indices, self::POR_PEDIDO) as $tanda) {
                $pedido = [];

                foreach ($tanda as $indice) {
                    $item = $eleccion[$indice];
                    $def = CatalogoLayouts::obtener($item['layout']);
                    $pedido[] = [
                        'bloque' => $item['bloque'],
                        'contenido' => $item['contenido'],
                        'parte' => $item['parte'],
                        'layout' => $item['layout'],
                        'marcas' => $def['marcas'],
                        'tope_titulo' => $def['tope_titulo'],
                        'tope_cuerpo' => $def['tope_cuerpo'],
                        'texto_previo' => $anteriores[$indice]['campos'] ?? [],
                    ];
                }

                $pedidos[] = [$instruccion, array_merge($base, [
                    'contenidos_obligatorios' => $this->textos($bloque['contenidos'] ?? []),
                    'bloque' => $bloque,
                    'contexto' => $contexto,
                    'diapositivas' => $pedido,
                    'fallos' => $fallos,
                ])];
                $tandas[] = $tanda;
            }
        }

        $diapositivas = $anteriores;

        foreach ($this->pedirVarios($pedidos) as $posicion => $datos) {
            $filas = $this->filas($datos['diapositivas'] ?? []);

            foreach ($tandas[$posicion] as $orden => $indice) {
                $diapositivas[$indice] = $this->normalizar($eleccion[$indice], $filas[$orden] ?? []);
            }
        }

        ksort($diapositivas);

        return array_values($diapositivas);
    }

    /**
     * @param  array{bloque: string, contenido: string, parte: string, layout: string}  $item
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function normalizar(array $item, array $fila): array
    {
        $layout = trim((string) ($fila['layout'] ?? $item['layout']));

        if (! isset(CatalogoLayouts::todos()[$layout])) {
            $layout = $item['layout'];
        }

        $def = CatalogoLayouts::obtener($layout);
        $campos = is_array($fila['campos'] ?? null) ? $fila['campos'] : [];
        $notas = $fila['notas'] ?? $campos['NOTAS'] ?? '';
        $notas = is_array($notas) ? implode("\n", $this->textos($notas)) : (string) $notas;

        return [
            'bloque' => $item['bloque'],
            'contenido' => $item['contenido'],
            'parte' => $item['parte'],
            'layout' => $layout,
            'diapositiva' => $def['diapositiva'],
            'campos' => CatalogoLayouts::ajustarCampos($def, $campos),
            'notas' => CatalogoLayouts::cortar($notas, 1800),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $diapositivas
     * @param  list<string>  $contenidos
     * @param  list<array<string, mixed>>  $bloques
     * @return list<string>
     */
    private function fallos(array $diapositivas, array $contenidos, string $aprendizaje, array $bloques): array
    {
        $fallos = [];

        foreach ($this->sinCubrir($diapositivas, $contenidos) as $contenido) {
            $fallos[] = "Falta cubrir el contenido \"{$contenido}\" en las diapositivas.";
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
     * @param  list<array<string, mixed>>  $diapositivas
     * @param  list<string>  $contenidos
     * @return list<string>
     */
    private function sinCubrir(array $diapositivas, array $contenidos): array
    {
        $texto = '';

        foreach ($diapositivas as $diapositiva) {
            $texto .= ' '.implode(' ', $diapositiva['campos'] ?? []).' '.($diapositiva['notas'] ?? '');
        }

        $texto = $this->clave($texto);

        return array_values(array_filter($contenidos, fn (string $contenido): bool => ! $this->menciona($contenido, $texto)));
    }

    /**
     * Bloques con contenidos sin cubrir; null cuando el fallo no es de un bloque en particular.
     *
     * @param  list<array<string, mixed>>  $diapositivas
     * @param  list<array<string, mixed>>  $bloques
     * @return list<string>|null
     */
    private function bloquesSinCubrir(array $diapositivas, array $bloques): ?array
    {
        $nombres = [];

        foreach ($bloques as $bloque) {
            if ($this->sinCubrir($diapositivas, $this->textos($bloque['contenidos'] ?? [])) !== []) {
                $nombres[] = (string) ($bloque['nombre'] ?? '');
            }
        }

        return $nombres !== [] ? $nombres : null;
    }

    /**
     * El texto nombra el contenido tal cual o contiene la mayoría de sus palabras clave.
     */
    private function menciona(string $contenido, string $texto): bool
    {
        if ($this->incluido($contenido, [$texto])) {
            return true;
        }

        $palabras = array_values(array_unique(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', $this->clave($contenido)) ?: [],
            fn (string $palabra): bool => mb_strlen($palabra) >= 4,
        )));

        if ($palabras === []) {
            return false;
        }

        $presentes = count(array_filter($palabras, fn (string $palabra): bool => str_contains($texto, $palabra)));

        return $presentes / count($palabras) >= 0.6;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array{layout: string, titulo: string, campos: array<string, string>, diapositiva: int, notas: string}>
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
                'notas' => (string) ($fila['notas'] ?? ''),
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
        $cliente = $this->cliente(Http::getFacadeRoot());

        try {
            $respuesta = $cliente->post($this->url(), $this->cuerpo($sistema, $entrada));
        } catch (ConnectionException) {
            throw new RuntimeException('No se pudo conectar con DeepSeek.');
        }

        return $this->leer($respuesta);
    }

    /**
     * Envía varios pedidos a la vez y devuelve sus respuestas en el mismo orden.
     *
     * @param  list<array{0: string, 1: array<string, mixed>}>  $pedidos
     * @return list<array<string, mixed>>
     */
    private function pedirVarios(array $pedidos): array
    {
        if ($pedidos === []) {
            return [];
        }

        $respuestas = Http::pool(function (Pool $pool) use ($pedidos): void {
            foreach ($pedidos as $indice => [$sistema, $entrada]) {
                $this->cliente($pool->as((string) $indice))->post($this->url(), $this->cuerpo($sistema, $entrada));
            }
        }, 4);

        $datos = [];

        foreach (array_keys($pedidos) as $indice) {
            $respuesta = $respuestas[(string) $indice] ?? null;

            if (! $respuesta instanceof Response) {
                throw new RuntimeException('No se pudo conectar con DeepSeek.');
            }

            $datos[] = $this->leer($respuesta);
        }

        return $datos;
    }

    private function cliente(Factory|PendingRequest $base): PendingRequest
    {
        $clave = config('services.deepseek.key');

        if (! is_string($clave) || $clave === '') {
            throw new RuntimeException('Falta la clave de DeepSeek.');
        }

        set_time_limit(300);

        $cliente = $base->withToken($clave)->acceptJson()->connectTimeout(10)->timeout(180);
        $certificado = config('services.deepseek.ca');

        if (is_string($certificado) && $certificado !== '' && is_file($certificado)) {
            $cliente = $cliente->withOptions(['verify' => $certificado]);
        }

        return $cliente;
    }

    private function url(): string
    {
        return rtrim((string) config('services.deepseek.url'), '/').'/chat/completions';
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return array<string, mixed>
     */
    private function cuerpo(string $sistema, array $entrada): array
    {
        return [
            'model' => config('services.deepseek.model'),
            'temperature' => 0.3,
            'max_tokens' => 8000,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $sistema],
                ['role' => 'user', 'content' => json_encode($entrada, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leer(Response $respuesta): array
    {
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
        $nombre = mb_strtolower(trim($nombre));

        return preg_replace('/^(?:el|la|los|las|un|una|unos|unas)\s+/u', '', $nombre) ?? $nombre;
    }
}
