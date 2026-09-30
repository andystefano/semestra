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
        $this->assertSame(['recuerdo', 'definicion', 'linea_tiempo'], array_column($presentacion->diapositivas, 'layout'));
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
        $zip->close();
        $this->assertIsString($xml);
        $this->assertStringContainsString('Testing &amp; Calidad', $xml);
        $this->assertStringContainsString('09/03/2026', $xml);
        $this->assertStringContainsString('> 2</a:t>', $xml);
        $this->assertStringNotContainsString('{NUMERO_CLASE}', $xml);
        $this->assertIsString($vinculosPortada);
        $this->assertStringNotContainsString('notesSlide', $vinculosPortada);
        $this->assertFalse($notas);
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
        $this->assertStringContainsString('<Notes>0</Notes>', $propiedades);
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

    public function test_template_notes_define_the_layout_catalog(): void
    {
        $definicion = CatalogoLayouts::obtener('definicion');

        $this->assertSame(7, $definicion['diapositiva']);
        $this->assertContains('DEFINICION', $definicion['marcas']);
        $this->assertContains('EN_SIMPLE', $definicion['marcas']);
        $this->assertGreaterThan(60, count(CatalogoLayouts::todos()));
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
