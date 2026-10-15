<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\EvaluacionDiagnostica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvaluacionDiagnosticaTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnostic_evaluation_is_packaged_as_qti_and_can_be_downloaded(): void
    {
        Storage::fake('local');
        config()->set('services.deepseek.key', 'sk-prueba');

        $user = User::factory()->create();
        $otro = User::factory()->create();
        $asignatura = $this->asignatura($user);

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'titulo' => 'Diagnóstico inicial',
                        'instrucciones' => 'Responde todas las preguntas.',
                        'secciones' => [
                            [
                                'tipo' => 'seleccion_multiple',
                                'titulo' => 'Alternativas',
                                'preguntas' => array_map(fn (int $i): array => [
                                    'enunciado' => "¿Qué evalúa una auditoría & control {$i}?",
                                    'alternativas' => ["Correcta {$i}", "Distractor A {$i}", "Distractor B {$i}", "Distractor C {$i}"],
                                    'correcta' => 'A',
                                    'tema' => 'Auditoría de Software',
                                ], range(1, 12)),
                            ],
                            [
                                'tipo' => 'verdadero_falso',
                                'titulo' => 'Verdadero o falso',
                                'preguntas' => array_map(fn (int $i): array => [
                                    'enunciado' => "Afirmación {$i}",
                                    'respuesta' => $i % 2 === 0,
                                    'tema' => 'Diseño de sistemas',
                                ], range(1, 10)),
                            ],
                        ],
                    ], JSON_THROW_ON_ERROR)],
                ]],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('asignaturas.evaluaciones.generar', $asignatura), [
                'fecha' => '2026-03-09',
                'orden' => 1,
            ])
            ->assertOk()
            ->assertJsonStructure(['url']);

        Http::assertSent(function (Request $request): bool {
            $datos = json_decode($request['messages'][1]['content'], true);

            return $datos['modulos_prerrequisito'] === ['Auditoría de Software']
                && $datos['primera_unidad']['contenidos'] === ['Principios de diseño', 'Estrategias basadas en modelos']
                && $datos['duracion_minutos'] === 45;
        });

        $evaluacion = EvaluacionDiagnostica::query()->first();
        $this->assertNotNull($evaluacion);
        $this->assertCount(12, $evaluacion->evaluacion['secciones'][0]['preguntas']);
        $this->assertCount(10, $evaluacion->evaluacion['secciones'][1]['preguntas']);
        Storage::disk('local')->assertExists($evaluacion->archivo_path);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($evaluacion->archivo_path)) === true);
        $manifiesto = $zip->getFromName('imsmanifest.xml');
        $prueba = $zip->getFromName('evaluacion.xml');
        $primera = $zip->getFromName('items/ITEM_001.xml');
        $verdaderoFalso = $zip->getFromName('items/ITEM_013.xml');
        $cantidad = $zip->numFiles;
        $zip->close();

        $this->assertSame(2 + 22, $cantidad);
        $this->assertNotFalse(simplexml_load_string($manifiesto));
        $this->assertStringContainsString('imsqti_test_xmlv2p1', $manifiesto);

        $test = simplexml_load_string($prueba);
        $this->assertNotFalse($test);
        $test->registerXPathNamespace('q', 'http://www.imsglobal.org/xsd/imsqti_v2p1');
        $this->assertCount(2, $test->xpath('//q:assessmentSection'));
        $this->assertSame('2700', (string) $test->xpath('//q:timeLimits')[0]['maxTime']);

        $item = simplexml_load_string($primera);
        $this->assertNotFalse($item);
        $item->registerXPathNamespace('q', 'http://www.imsglobal.org/xsd/imsqti_v2p1');
        $correcta = (string) $item->xpath('//q:correctResponse/q:value')[0];
        $this->assertSame('Correcta 1', (string) $item->xpath("//q:simpleChoice[@identifier='{$correcta}']")[0]);
        $this->assertStringContainsString('auditoría &amp; control', $primera);

        $this->assertStringContainsString('<value>FALSO</value>', $verdaderoFalso);

        $this->actingAs($user)
            ->get(route('asignaturas.evaluaciones.descargar', [$asignatura, $evaluacion]))
            ->assertOk();

        $this->actingAs($otro)
            ->get(route('asignaturas.evaluaciones.descargar', [$asignatura, $evaluacion]))
            ->assertNotFound();
    }

    public function test_incomplete_evaluation_is_rejected(): void
    {
        Storage::fake('local');
        config()->set('services.deepseek.key', 'sk-prueba');

        $user = User::factory()->create();
        $asignatura = $this->asignatura($user);

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'titulo' => 'Diagnóstico',
                        'secciones' => [[
                            'tipo' => 'seleccion_multiple',
                            'preguntas' => [[
                                'enunciado' => 'Única',
                                'alternativas' => ['A', 'B', 'C', 'D'],
                                'correcta' => 'B',
                            ]],
                        ]],
                    ], JSON_THROW_ON_ERROR)],
                ]],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('asignaturas.evaluaciones.generar', $asignatura), [
                'fecha' => '2026-03-09',
                'orden' => 1,
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('evaluaciones_diagnosticas', 0);
    }

    private function asignatura(User $user): Asignatura
    {
        return Asignatura::factory()->for($user)->create([
            'nombre' => 'Taller de Diseño de Sistemas',
            'markdown' => '<table><tr><td>Módulos Prerrequisito</td><td>NO</td><td>☐</td><td>SI</td><td>☒</td><td>Módulo(s)</td><td colspan="5">Auditoría de Software</td></tr></table>',
            'unidades' => [
                'cantidad' => 1,
                'unidades' => [[
                    'numero' => 1,
                    'nombre' => 'Introducción',
                    'aprendizaje_esperado' => 'Caracterizan diseño de sistemas.',
                    'contenidos_obligatorios' => ['Principios de diseño', '-Estrategias basadas en modelos'],
                ]],
            ],
            'planificacion' => [
                'calendario' => [[
                    'fecha' => '2026-03-09',
                    'bloques' => [[
                        'orden' => 1,
                        'tipo' => 'CLASE',
                        'duracion_minutos' => 90,
                        'descripcion' => 'Presentación de inicio y evaluación diagnóstica',
                        'contenidos_obligatorios' => [],
                    ]],
                ]],
            ],
        ]);
    }
}
