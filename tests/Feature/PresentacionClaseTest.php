<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Presentacion;
use App\Models\User;
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
                            'aprendizaje_esperado' => 'Explica el origen',
                            'criterios_evaluacion' => ['Fundamenta con fuentes'],
                            'contenidos_obligatorios' => [
                                ['nombre' => 'Línea de tiempo', 'minutos_asignados' => 90],
                            ],
                        ]],
                    ],
                ],
            ],
        ]);

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'recordar' => [[
                            'titulo' => 'Lo ya visto',
                            'puntos' => ['Vimos el diagnóstico inicial.'],
                        ]],
                        'conocer' => [
                            [
                                'contenido' => 'Línea de tiempo',
                                'titulo' => 'Qué es',
                                'puntos' => ['Ordena hechos.'],
                            ],
                            [
                                'contenido' => 'Línea de tiempo',
                                'titulo' => 'Cómo usarla',
                                'puntos' => ['Ubica fechas clave.'],
                            ],
                        ],
                        'aplicar' => [
                            ['titulo' => 'Caso breve', 'actividad' => 'Ordena tres hechos de tu vida.'],
                            ['titulo' => 'Actividad', 'actividad' => 'Marca el hecho más antiguo.'],
                        ],
                    ], JSON_THROW_ON_ERROR)],
                ]],
            ]),
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
        $this->assertSame(['Línea de tiempo'], $presentacion->contenidos);
        $this->assertSame('Lo ya visto', $presentacion->diapositivas['recordar'][0]['titulo']);
        Storage::disk('local')->assertExists($presentacion->archivo_path);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($presentacion->archivo_path)) === true);
        $xml = $zip->getFromName('ppt/slides/slide1.xml');
        $datos = $zip->getFromName('ppt/slides/slide2.xml');
        $criterios = $zip->getFromName('ppt/slides/slide4.xml');
        $contenidos = $zip->getFromName('ppt/slides/slide5.xml');
        $cierre = $zip->getFromName('ppt/slides/slide3.xml');
        $conocer = $zip->getFromName('ppt/slides/slide7.xml');
        $segundaConocer = $zip->getFromName('ppt/slides/slide8.xml');
        $orden = $zip->getFromName('ppt/presentation.xml');
        $zip->close();
        $this->assertIsString($xml);
        $this->assertStringContainsString('Testing &amp; Calidad', $xml);
        $this->assertStringContainsString('09/03/2026', $xml);
        $this->assertStringContainsString('> 2</a:t>', $xml);
        $this->assertStringNotContainsString('{NUMERO_CLASE}', $xml);
        $this->assertIsString($datos);
        $this->assertStringContainsString('Aprendizaje esperado', $datos);
        $this->assertStringNotContainsString('Criterios de evaluación', $datos);
        $this->assertStringNotContainsString('{TITULO_SLIDE}', $datos);
        $this->assertStringNotContainsString('{CONTENIDO_SLIDE}', $datos);
        $this->assertStringContainsString('Criterios de evaluación', $criterios);
        $this->assertStringContainsString('Contenidos', $contenidos);
        $this->assertIsString($cierre);
        $this->assertStringNotContainsString('Momento para conocer', $cierre);
        $this->assertIsString($conocer);
        $this->assertStringContainsString('Momento para conocer', $conocer);
        $this->assertIsString($segundaConocer);
        $this->assertStringNotContainsString('Momento para conocer', $segundaConocer);
        $this->assertIsString($orden);
        $this->assertStringContainsString('r:id="rId12"', $orden);
        $this->assertStringContainsString('r:id="rId4"/></p:sldIdLst>', $orden);
        preg_match_all('/<p:sldId id="(\d+)"/', $orden, $identificadores);
        $this->assertSame($identificadores[1], array_values(array_unique($identificadores[1])));
        $this->assertNotFalse(simplexml_load_string($xml));

        $this->actingAs($user)
            ->get(route('asignaturas.presentaciones.descargar', [$asignatura, $presentacion]))
            ->assertOk();

        $this->actingAs($otro)
            ->get(route('asignaturas.presentaciones.descargar', [$asignatura, $presentacion]))
            ->assertNotFound();
    }
}
