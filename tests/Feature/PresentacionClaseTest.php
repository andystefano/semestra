<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Presentacion;
use App\Models\User;
use App\Presentacion\CatalogoLayouts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PresentacionClaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_class_presentation_is_stored_and_can_be_downloaded(): void
    {
        Storage::fake('local');
        config()->set('services.deepseek.key', 'sk-prueba');

        $user = User::factory()->create();
        $otro = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create([
            'nombre' => 'Taller de Testing & Calidad',
            'planificacion' => [
                'calendario' => [
                    [
                        'fecha' => '2026-03-02',
                        'bloques' => [[
                            'orden' => 1,
                            'tipo' => 'CLASE',
                            'unidad' => 1,
                            'aprendizaje_esperado' => 'Presenta el curso',
                            'criterios_evaluacion' => ['Participa'],
                            'contenidos_obligatorios' => [
                                ['nombre' => 'Diagnóstico', 'minutos_asignados' => 45],
                            ],
                        ]],
                    ],
                    [
                        'fecha' => '2026-03-09',
                        'bloques' => [[
                            'orden' => 1,
                            'tipo' => 'CLASE',
                            'unidad' => 1,
                            'aprendizaje_esperado' => '1.-Explica el origen de la línea de tiempo '.str_repeat('con detalle ', 18).'2.-Reconoce sus hitos históricos por completo.',
                            'criterios_evaluacion' => [
                                '1.1.-Fundamenta con fuentes '.str_repeat('y evidencia ', 28),
                                '1.2.-Cita el origen por completo.',
                            ],
                            'contenidos_obligatorios' => [
                                ['nombre' => '1.-Línea de tiempo', 'minutos_asignados' => 90],
                                ['nombre' => '-Patrones', 'minutos_asignados' => 45],
                            ],
                        ]],
                    ],
                ],
            ],
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push($this->respuesta([
                    'resumen' => 'Origen de la línea de tiempo',
                    'recordar' => [[
                        'titulo' => 'Lo ya visto',
                        'puntos' => ['Vimos el diagnóstico inicial.'],
                    ]],
                ]))
                ->push($this->respuesta([
                    'bloques' => [[
                        'nombre' => 'Historia',
                        'contenidos' => ['Línea de tiempo'],
                    ]],
                ]))
                ->push($this->respuesta([
                    'objetivos' => [[
                        'bloque' => 'Historia',
                        'objetivo' => 'Explicar el origen con una línea de tiempo',
                        'nivel' => 'comprender',
                    ]],
                ]))
                ->push($this->respuesta([
                    'estrategias' => [[
                        'bloque' => 'Historia',
                        'estrategias' => ['linea_tiempo'],
                        'justificacion' => '',
                    ]],
                ]))
                ->push($this->respuesta([
                    'recursos' => [[
                        'bloque' => 'Historia',
                        'recursos' => ['timeline'],
                        'justificacion' => '',
                    ]],
                ]))
                ->push($this->respuesta([
                    'estructuras' => [[
                        'bloque' => 'Historia',
                        'secuencia' => [
                            ['momento' => 'explicar', 'descripcion' => 'Mostrar hechos'],
                            ['momento' => 'ejemplo', 'descripcion' => 'Ubicar fechas'],
                        ],
                    ]],
                ]))
                ->push($this->respuesta([
                    'diapositivas' => [
                        ['bloque' => 'Historia', 'parte' => 'recordar', 'layout' => 'recuerdo'],
                        ['bloque' => 'Historia', 'parte' => 'explicar', 'layout' => 'definicion'],
                        ['bloque' => 'Historia', 'parte' => 'ejemplo', 'layout' => 'linea_tiempo'],
                    ],
                ]))
                ->push($this->respuesta([
                    'diapositivas' => [
                        [
                            'bloque' => 'Historia',
                            'layout' => 'recuerdo',
                            'campos' => [
                                'TITULO' => 'Lo ya visto',
                                'FICHA_1' => 'Vimos el diagnóstico inicial.',
                                'FICHA_2' => 'La arquitectura ordena el sistema.',
                                'FICHA_3' => 'Hoy seguimos con la línea de tiempo.',
                                'FICHA_4' => 'El diagnóstico quedó hecho.',
                                'PREGUNTA' => '¿Qué recuerdas de la clase anterior?',
                            ],
                        ],
                        [
                            'bloque' => 'Historia',
                            'layout' => 'definicion',
                            'campos' => [
                                'TITULO' => 'Momento para conocer: Qué es',
                                'DEFINICION' => 'La línea de tiempo ordena hechos.',
                                'EN_SIMPLE' => 'Sirve para ubicar fechas clave.',
                            ],
                        ],
                        [
                            'bloque' => 'Historia',
                            'layout' => 'linea_tiempo',
                            'campos' => [
                                'TITULO' => 'Cómo usarla',
                                'HITO_1_FECHA' => '1960',
                                'HITO_1' => 'Línea de tiempo',
                                'HITO_2_FECHA' => '1990',
                                'HITO_2' => 'Patrones',
                                'HITO_3_FECHA' => '2000',
                                'HITO_3' => 'Servicios',
                                'HITO_4_FECHA' => 'Hoy',
                                'HITO_4' => 'Nube',
                            ],
                        ],
                    ],
                ]))
                ->push($this->respuesta([
                    'diapositivas' => [
                        [
                            'bloque' => 'Otros contenidos',
                            'contenido' => 'Patrones',
                            'layout' => 'definicion',
                            'campos' => [
                                'TITULO' => 'Qué son los patrones',
                                'DEFINICION' => 'Los patrones son soluciones probadas.',
                                'EN_SIMPLE' => 'Recetas que ya funcionaron.',
                            ],
                            'notas' => "Explica que un patrón resume la experiencia de otros equipos.\nPregunta: ¿qué receta repetirías en tu proyecto?",
                        ],
                        [
                            'bloque' => 'Otros contenidos',
                            'contenido' => 'Patrones',
                            'layout' => 'ejemplo_resuelto',
                            'campos' => [
                                'TITULO' => 'Patrones en un caso',
                                'ENUNCIADO' => 'Un sistema repite la misma solución.',
                                'SOLUCION' => 'Se aplica un patrón conocido.',
                                'RESULTADO' => 'Menos errores & más orden.',
                            ],
                            'notas' => 'Muestra el antes y el después del caso con <patrones> & ejemplos.',
                        ],
                    ],
                ])),
        ]);

        $this->actingAs($user)
            ->postJson(route('asignaturas.presentaciones.generar', $asignatura), [
                'fecha' => '2026-03-09',
                'orden' => 1,
            ])
            ->assertOk()
            ->assertJsonStructure(['url']);

        $presentacion = Presentacion::query()->first();
        $this->assertNotNull($presentacion);
        $this->assertSame(['Línea de tiempo', 'Patrones'], $presentacion->contenidos);
        $this->assertSame('Lo ya visto', $presentacion->plan_pedagogico['contexto']['recordar'][0]['titulo']);
        $this->assertSame(['Línea de tiempo'], $presentacion->plan_pedagogico['bloques'][0]['contenidos']);
        $this->assertSame('Lo ya visto', $presentacion->diapositivas[0]['titulo']);
        $this->assertSame(['recuerdo', 'definicion', 'linea_tiempo', 'definicion', 'ejemplo_resuelto'], array_column($presentacion->diapositivas, 'layout'));
        $this->assertSame(['', 'Línea de tiempo', 'Línea de tiempo', 'Patrones', 'Patrones'], array_column($presentacion->plan_pedagogico['diapositivas'], 'contenido'));
        $this->assertSame([], $presentacion->plan_pedagogico['advertencias']);
        Storage::disk('local')->assertExists($presentacion->archivo_path);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($presentacion->archivo_path)) === true);
        $xml = $zip->getFromName('ppt/slides/slide1.xml');
        $datos = $zip->getFromName('ppt/slides/slide2.xml');
        $criterios = $zip->getFromName('ppt/slides/slide3.xml');
        $contenidos = $zip->getFromName('ppt/slides/slide4.xml');
        $conocer = $zip->getFromName('ppt/slides/slide6.xml');
        $segunda = $zip->getFromName('ppt/slides/slide7.xml');
        $orden = $zip->getFromName('ppt/presentation.xml');
        $vinculos = $zip->getFromName('ppt/_rels/presentation.xml.rels');
        $vinculosPortada = $zip->getFromName('ppt/slides/_rels/slide1.xml.rels');
        $propiedades = $zip->getFromName('docProps/app.xml');
        $notas = $zip->locateName('ppt/notesSlides/notesSlide1.xml');
        $sinNotas = $zip->locateName('ppt/notesSlides/notesSlide6.xml');
        $notaPatrones = $zip->getFromName('ppt/notesSlides/notesSlide8.xml');
        $notaCaso = $zip->getFromName('ppt/notesSlides/notesSlide9.xml');
        $vinculosNota = $zip->getFromName('ppt/notesSlides/_rels/notesSlide8.xml.rels');
        $vinculosPatrones = $zip->getFromName('ppt/slides/_rels/slide8.xml.rels');
        $tipos = $zip->getFromName('[Content_Types].xml');
        $zip->close();
        $this->assertIsString($xml);
        $this->assertStringContainsString('Testing &amp; Calidad', $xml);
        $this->assertStringContainsString('09/03/2026', $xml);
        $this->assertStringContainsString('> 2</a:t>', $xml);
        $this->assertStringNotContainsString('{NUMERO_CLASE}', $xml);
        $this->assertIsString($vinculosPortada);
        $this->assertStringNotContainsString('notesSlide', $vinculosPortada);
        $this->assertFalse($notas);
        $this->assertFalse($sinNotas);
        $this->assertIsString($notaPatrones);
        $this->assertStringContainsString('Explica que un patrón resume la experiencia de otros equipos.', $notaPatrones);
        $this->assertStringContainsString('¿qué receta repetirías en tu proyecto?', $notaPatrones);
        $this->assertStringNotContainsString('LAYOUT:', $notaPatrones);
        $this->assertNotFalse(simplexml_load_string($notaPatrones));
        $this->assertIsString($notaCaso);
        $this->assertStringContainsString('&lt;patrones&gt; &amp; ejemplos', $notaCaso);
        $this->assertNotFalse(simplexml_load_string($notaCaso));
        $this->assertIsString($vinculosNota);
        $this->assertStringContainsString('Target="../slides/slide8.xml"', $vinculosNota);
        $this->assertStringContainsString('notesMaster1.xml', $vinculosNota);
        $this->assertIsString($vinculosPatrones);
        $this->assertStringContainsString('Target="../notesSlides/notesSlide8.xml"', $vinculosPatrones);
        $this->assertIsString($tipos);
        $this->assertStringContainsString('PartName="/ppt/notesSlides/notesSlide8.xml"', $tipos);
        $this->assertStringContainsString('PartName="/ppt/notesSlides/notesSlide9.xml"', $tipos);
        $this->assertStringNotContainsString('PartName="/ppt/notesSlides/notesSlide6.xml"', $tipos);
        $this->assertIsString($datos);
        $this->assertStringContainsString('Aprendizaje esperado', $datos);
        $this->assertStringContainsString('Explica el origen de la línea de tiempo', $datos);
        $this->assertStringContainsString('Reconoce sus hitos históricos por completo', $datos);
        $this->assertStringContainsString('buAutoNum', $datos);
        $this->assertStringContainsString('normAutofit', $datos);
        $this->assertStringNotContainsString('1.-', $datos);
        $this->assertStringNotContainsString('2.-', $datos);
        $this->assertStringNotContainsString('Criterios de evaluación', $datos);
        $this->assertStringNotContainsString('{TITULO}', $datos);
        $this->assertStringNotContainsString('{CONTENIDO}', $datos);
        $this->assertStringContainsString('Criterios de evaluación', $criterios);
        $this->assertStringContainsString('Fundamenta con fuentes', $criterios);
        $this->assertStringContainsString('Cita el origen por completo', $criterios);
        $this->assertStringContainsString('buAutoNum', $criterios);
        $this->assertStringNotContainsString('1.1.-', $criterios);
        $this->assertStringNotContainsString('1.2.-', $criterios);
        $this->assertStringContainsString('Contenidos obligatorios', $contenidos);
        $this->assertStringContainsString('Línea de tiempo', $contenidos);
        $this->assertStringContainsString('Patrones', $contenidos);
        $this->assertStringContainsString('buAutoNum', $contenidos);
        $this->assertStringNotContainsString('1.-', $contenidos);
        $this->assertIsString($conocer);
        $this->assertStringContainsString('Momento para conocer', $conocer);
        $this->assertStringNotContainsString('{DEFINICION}', $conocer);
        $this->assertIsString($segunda);
        $this->assertStringNotContainsString('Momento para conocer', $segunda);
        $this->assertIsString($orden);
        $this->assertIsString($vinculos);
        preg_match('/<p:sldId id="\d+" r:id="(rId\d+)"\/>\s*<\/p:sldIdLst>/', $orden, $cierreId);
        $this->assertNotEmpty($cierreId);
        $this->assertStringContainsString('Id="'.$cierreId[1].'"', $vinculos);
        $this->assertMatchesRegularExpression('/Id="'.preg_quote($cierreId[1], '/').'"[^>]*Target="slides\/slide75.xml"/', $vinculos);
        preg_match_all('/<p:sldId id="(\d+)"/', $orden, $identificadores);
        $this->assertSame($identificadores[1], array_values(array_unique($identificadores[1])));
        $this->assertIsString($propiedades);
        $this->assertStringContainsString('<Slides>'.count($identificadores[1]).'</Slides>', $propiedades);
        $this->assertStringContainsString('<Notes>2</Notes>', $propiedades);
        $this->assertNotFalse(simplexml_load_string($xml));

        $this->actingAs($user)
            ->get(route('asignaturas.presentaciones.descargar', [$asignatura, $presentacion]))
            ->assertOk();

        $this->actingAs($otro)
            ->get(route('asignaturas.presentaciones.descargar', [$asignatura, $presentacion]))
            ->assertNotFound();
    }

    public function test_a_missing_content_is_repaired_once_and_text_stays_within_the_layout_cap(): void
    {
        Storage::fake('local');
        config()->set('services.deepseek.key', 'sk-prueba');
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create([
            'planificacion' => [
                'calendario' => [[
                    'fecha' => '2026-03-09',
                    'bloques' => [[
                        'orden' => 1,
                        'tipo' => 'CLASE',
                        'unidad' => 1,
                        'aprendizaje_esperado' => 'Explica el origen',
                        'criterios_evaluacion' => ['Fundamenta'],
                        'contenidos_obligatorios' => [
                            ['nombre' => 'Línea de tiempo', 'minutos_asignados' => 90],
                        ],
                    ]],
                ]],
            ],
        ]);

        $largo = 'Línea de tiempo '.str_repeat('hecho ', 80);

        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push($this->respuesta(['resumen' => 'Primera', 'recordar' => [['titulo' => 'Primera clase', 'puntos' => ['No hay clase anterior.']]]]))
                ->push($this->respuesta(['bloques' => [['nombre' => 'Historia', 'contenidos' => ['Línea de tiempo']]]]))
                ->push($this->respuesta(['objetivos' => [['bloque' => 'Historia', 'objetivo' => 'Explicar', 'nivel' => 'comprender']]]))
                ->push($this->respuesta(['estrategias' => [['bloque' => 'Historia', 'estrategias' => ['definicion'], 'justificacion' => '']]]))
                ->push($this->respuesta(['recursos' => [['bloque' => 'Historia', 'recursos' => ['texto'], 'justificacion' => '']]]))
                ->push($this->respuesta(['estructuras' => [['bloque' => 'Historia', 'secuencia' => [['momento' => 'explicar', 'descripcion' => 'Mostrar']]]]]))
                ->push($this->respuesta(['diapositivas' => [['bloque' => 'Historia', 'parte' => 'explicar', 'layout' => 'definicion']]]))
                ->push($this->respuesta(['diapositivas' => [['bloque' => 'Historia', 'layout' => 'definicion', 'campos' => ['TITULO' => 'Sin el tema', 'DEFINICION' => 'Nada que ver.', 'EN_SIMPLE' => 'Otro texto.']]]]))
                ->push($this->respuesta(['diapositivas' => [['bloque' => 'Historia', 'layout' => 'definicion', 'campos' => ['TITULO' => 'La idea', 'DEFINICION' => $largo, 'EN_SIMPLE' => 'Ordena hechos.']]]])),
        ]);

        $this->actingAs($user)
            ->postJson(route('asignaturas.presentaciones.generar', $asignatura), [
                'fecha' => '2026-03-09',
                'orden' => 1,
            ])
            ->assertOk();

        $this->assertCount(9, Http::recorded());

        $plan = Presentacion::query()->firstOrFail()->plan_pedagogico;
        $this->assertSame([], $plan['advertencias']);
        $definicion = $plan['diapositivas'][0]['campos']['DEFINICION'];
        $this->assertLessThanOrEqual(220, mb_strlen($definicion));
        $this->assertStringContainsString('Línea de tiempo', $definicion);
    }

    public function test_a_second_validation_failure_is_saved_with_a_warning(): void
    {
        Storage::fake('local');
        config()->set('services.deepseek.key', 'sk-prueba');
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create([
            'planificacion' => [
                'calendario' => [[
                    'fecha' => '2026-03-09',
                    'bloques' => [[
                        'orden' => 1,
                        'tipo' => 'CLASE',
                        'unidad' => 1,
                        'aprendizaje_esperado' => 'Aplica la línea de tiempo',
                        'criterios_evaluacion' => ['Resuelve un caso'],
                        'contenidos_obligatorios' => [
                            ['nombre' => 'Línea de tiempo', 'minutos_asignados' => 90],
                        ],
                    ]],
                ]],
            ],
        ]);

        $cubierta = ['diapositivas' => [[
            'bloque' => 'Historia',
            'layout' => 'definicion',
            'campos' => [
                'TITULO' => 'Línea de tiempo',
                'DEFINICION' => 'Ordena hechos.',
                'EN_SIMPLE' => 'Una línea de tiempo.',
            ],
        ]]];

        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push($this->respuesta(['resumen' => 'Aplicar', 'recordar' => [['titulo' => 'Primera clase', 'puntos' => ['No hay clase anterior.']]]]))
                ->push($this->respuesta(['bloques' => [['nombre' => 'Historia', 'contenidos' => ['Línea de tiempo']]]]))
                ->push($this->respuesta(['objetivos' => [['bloque' => 'Historia', 'objetivo' => 'Conocer la línea de tiempo', 'nivel' => 'conocer']]]))
                ->push($this->respuesta(['estrategias' => [['bloque' => 'Historia', 'estrategias' => ['definicion'], 'justificacion' => '']]]))
                ->push($this->respuesta(['recursos' => [['bloque' => 'Historia', 'recursos' => ['texto'], 'justificacion' => '']]]))
                ->push($this->respuesta(['estructuras' => [['bloque' => 'Historia', 'secuencia' => [['momento' => 'explicar', 'descripcion' => 'Mostrar']]]]]))
                ->push($this->respuesta(['diapositivas' => [['bloque' => 'Historia', 'parte' => 'explicar', 'layout' => 'definicion']]]))
                ->push($this->respuesta($cubierta))
                ->push($this->respuesta($cubierta)),
        ]);

        $this->actingAs($user)
            ->postJson(route('asignaturas.presentaciones.generar', $asignatura), [
                'fecha' => '2026-03-09',
                'orden' => 1,
            ])
            ->assertOk();

        $this->assertCount(9, Http::recorded());
        $advertencias = Presentacion::query()->firstOrFail()->plan_pedagogico['advertencias'];
        $this->assertNotSame([], $advertencias);
        $this->assertStringContainsString('aplicar', implode(' ', $advertencias));
    }

    public function test_each_content_gets_at_least_two_slides_and_articles_do_not_hide_coverage(): void
    {
        Storage::fake('local');
        config()->set('services.deepseek.key', 'sk-prueba');
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $asignatura = $this->asignaturaCon($user, ['Los sistemas centralizados', 'Los sistemas distribuidos']);

        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push($this->respuesta(['resumen' => 'Arquitecturas', 'recordar' => [['titulo' => 'Primera clase', 'puntos' => ['No hay clase anterior.']]]]))
                ->push($this->respuesta(['bloques' => [['nombre' => 'Cómputo', 'contenidos' => ['Los sistemas centralizados', 'Los sistemas distribuidos']]]]))
                ->push($this->respuesta(['objetivos' => [['bloque' => 'Cómputo', 'objetivo' => 'Distinguir', 'nivel' => 'comprender']]]))
                ->push($this->respuesta(['estrategias' => [['bloque' => 'Cómputo', 'estrategias' => ['definicion'], 'justificacion' => '']]]))
                ->push($this->respuesta(['recursos' => [['bloque' => 'Cómputo', 'recursos' => ['texto'], 'justificacion' => '']]]))
                ->push($this->respuesta(['estructuras' => [['bloque' => 'Cómputo', 'secuencia' => [['momento' => 'explicar', 'descripcion' => 'Comparar']]]]]))
                ->push($this->respuesta(['diapositivas' => [
                    ['bloque' => 'Cómputo', 'contenido' => '', 'parte' => 'comparar', 'layout' => 'comparacion'],
                    ['bloque' => 'Cómputo', 'contenido' => '', 'parte' => 'recordar', 'layout' => 'recuerdo'],
                    ['bloque' => 'Cómputo', 'contenido' => '', 'parte' => 'cerrar', 'layout' => 'sintesis'],
                ]]))
                ->push($this->respuesta(['diapositivas' => [
                    ['campos' => ['TITULO' => 'Dos formas de organizar el cómputo']],
                    ['campos' => ['TITULO' => 'Sistemas centralizados', 'DEFINICION' => 'Todo se procesa en un nodo central.']],
                    ['campos' => ['TITULO' => 'Un banco con un servidor central']],
                    ['campos' => ['TITULO' => 'Sistemas distribuidos', 'DEFINICION' => 'Varios nodos comparten el trabajo.']],
                    ['campos' => ['TITULO' => 'Una tienda con sucursales conectadas']],
                    ['campos' => ['TITULO' => 'Lo que nos llevamos']],
                ]])),
        ]);

        $this->actingAs($user)
            ->postJson(route('asignaturas.presentaciones.generar', $asignatura), ['fecha' => '2026-03-09', 'orden' => 1])
            ->assertOk();

        $this->assertCount(8, Http::recorded());

        $plan = Presentacion::query()->firstOrFail()->plan_pedagogico;
        $this->assertSame(
            ['comparacion', 'definicion', 'ejemplo_resuelto', 'definicion', 'ejemplo_resuelto', 'sintesis'],
            array_column($plan['diapositivas'], 'layout'),
        );
        $this->assertSame(
            ['', 'Los sistemas centralizados', 'Los sistemas centralizados', 'Los sistemas distribuidos', 'Los sistemas distribuidos', ''],
            array_column($plan['diapositivas'], 'contenido'),
        );
        $this->assertSame('Una tienda con sucursales conectadas', $plan['diapositivas'][4]['campos']['TITULO']);
        $this->assertSame([], $plan['advertencias']);
    }

    public function test_slides_are_filled_in_parallel_batches_and_keep_their_order(): void
    {
        Storage::fake('local');
        config()->set('services.deepseek.key', 'sk-prueba');
        Http::preventStrayRequests();

        $contenidos = ['Procesos físicos', 'Flujos de datos', 'Agentes externos', 'Almacenamientos', 'Arquitectura de red'];
        $user = User::factory()->create();
        $asignatura = $this->asignaturaCon($user, $contenidos);
        $titulos = fn (int $desde, int $hasta): array => array_map(
            fn (int $numero): array => ['campos' => ['TITULO' => 'Lámina '.$numero.' '.$contenidos[intdiv($numero - 1, 2)]]],
            range($desde, $hasta),
        );

        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push($this->respuesta(['resumen' => 'DFD físico', 'recordar' => [['titulo' => 'Primera clase', 'puntos' => ['No hay clase anterior.']]]]))
                ->push($this->respuesta(['bloques' => [['nombre' => 'DFD físico', 'contenidos' => $contenidos]]]))
                ->push($this->respuesta(['objetivos' => []]))
                ->push($this->respuesta(['estrategias' => []]))
                ->push($this->respuesta(['recursos' => []]))
                ->push($this->respuesta(['estructuras' => []]))
                ->push($this->respuesta(['diapositivas' => []]))
                ->push($this->respuesta(['diapositivas' => $titulos(1, 8)]))
                ->push($this->respuesta(['diapositivas' => $titulos(9, 10)])),
        ]);

        $this->actingAs($user)
            ->postJson(route('asignaturas.presentaciones.generar', $asignatura), ['fecha' => '2026-03-09', 'orden' => 1])
            ->assertOk();

        $this->assertCount(9, Http::recorded());
        $this->assertSame(8000, Http::recorded()[7][0]->data()['max_tokens']);

        $plan = Presentacion::query()->firstOrFail()->plan_pedagogico;
        $this->assertCount(10, $plan['diapositivas']);
        $this->assertSame('Lámina 1 Procesos físicos', $plan['diapositivas'][0]['campos']['TITULO']);
        $this->assertSame('Lámina 9 Arquitectura de red', $plan['diapositivas'][8]['campos']['TITULO']);
        $this->assertSame('Lámina 10 Arquitectura de red', $plan['diapositivas'][9]['campos']['TITULO']);
        $this->assertSame([], $plan['advertencias']);
    }

    public function test_all_generated_presentations_of_the_plan_download_as_one_zip(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $otro = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create([
            'nombre' => 'Diseño: Sistemas/Datos',
            'planificacion' => [
                'calendario' => [
                    ['fecha' => '2026-03-09', 'bloques' => [['orden' => 1, 'tipo' => 'CLASE']]],
                    ['fecha' => '2026-03-02', 'bloques' => [['orden' => 1, 'tipo' => 'CLASE'], ['orden' => 2, 'tipo' => 'EVALUACION']]],
                ],
            ],
        ]);

        foreach ([['2026-03-09', 1, 'segunda'], ['2026-03-02', 1, 'primera'], ['2026-04-01', 1, 'antigua']] as [$fecha, $orden, $texto]) {
            $path = 'asignaturas/'.$asignatura->id.'/presentaciones/'.$fecha.'-'.$orden.'.pptx';
            Storage::disk('local')->put($path, $texto);
            $asignatura->presentaciones()->create([
                'fecha' => $fecha,
                'orden' => $orden,
                'contenidos' => [],
                'diapositivas' => [],
                'plan_pedagogico' => [],
                'archivo_path' => $path,
                'archivo_nombre' => 'Presentacion '.$fecha.' '.$orden.'.pptx',
            ]);
        }

        $respuesta = $this->actingAs($user)
            ->get(route('asignaturas.presentaciones.descargar-todas', $asignatura))
            ->assertOk()
            ->assertDownload('Presentaciones Diseno Sistemas Datos.zip');
        $this->assertStringContainsString(rawurlencode('Presentaciones Diseño Sistemas Datos.zip'), (string) $respuesta->headers->get('content-disposition'));

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($respuesta->baseResponse->getFile()->getPathname()) === true);
        $nombres = [];
        for ($indice = 0; $indice < $zip->numFiles; $indice++) {
            $nombres[] = $zip->getNameIndex($indice);
        }
        $primera = $zip->getFromName('01 Presentacion 2026-03-02 1.pptx');
        $zip->close();

        $this->assertSame(['01 Presentacion 2026-03-02 1.pptx', '02 Presentacion 2026-03-09 1.pptx'], $nombres);
        $this->assertSame('primera', $primera);

        $this->actingAs($otro)
            ->get(route('asignaturas.presentaciones.descargar-todas', $asignatura))
            ->assertNotFound();

        $vacia = Asignatura::factory()->for($user)->create(['planificacion' => ['calendario' => []]]);

        $this->actingAs($user)
            ->get(route('asignaturas.presentaciones.descargar-todas', $vacia))
            ->assertNotFound();
    }

    public function test_text_over_the_cap_is_cut_on_a_word_boundary(): void
    {
        $this->assertSame('Texto corto', CatalogoLayouts::cortar('Texto corto', 20));
        $this->assertSame('Los sistemas…', CatalogoLayouts::cortar('Los sistemas distribuidos', 18));

        $campos = CatalogoLayouts::ajustarCampos(CatalogoLayouts::obtener('definicion'), [
            'DEFINICION' => str_repeat('arquitectura ', 30),
        ]);

        $this->assertLessThanOrEqual(220, mb_strlen($campos['DEFINICION']));
        $this->assertStringEndsWith('arquitectura…', $campos['DEFINICION']);
    }

    public function test_template_notes_define_the_layout_catalog(): void
    {
        $definicion = CatalogoLayouts::obtener('definicion');

        $this->assertSame(7, $definicion['diapositiva']);
        $this->assertContains('DEFINICION', $definicion['marcas']);
        $this->assertContains('EN_SIMPLE', $definicion['marcas']);
        $this->assertGreaterThan(60, count(CatalogoLayouts::todos()));
    }

    /**
     * @param  list<string>  $contenidos
     */
    private function asignaturaCon(User $user, array $contenidos): Asignatura
    {
        return Asignatura::factory()->for($user)->create([
            'planificacion' => [
                'calendario' => [[
                    'fecha' => '2026-03-09',
                    'bloques' => [[
                        'orden' => 1,
                        'tipo' => 'CLASE',
                        'unidad' => 1,
                        'aprendizaje_esperado' => 'Distingue alternativas',
                        'criterios_evaluacion' => ['Compara'],
                        'contenidos_obligatorios' => array_map(
                            fn (string $nombre): array => ['nombre' => $nombre, 'minutos_asignados' => 30],
                            $contenidos,
                        ),
                        'duracion_minutos' => 180,
                    ]],
                ]],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function respuesta(array $datos): array
    {
        return [
            'choices' => [[
                'message' => ['content' => json_encode($datos, JSON_THROW_ON_ERROR)],
            ]],
        ];
    }
}
