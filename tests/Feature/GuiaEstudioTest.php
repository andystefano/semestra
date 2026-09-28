<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\GuiaEstudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuiaEstudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_study_guide_is_stored_and_can_be_downloaded(): void
    {
        Storage::fake('local');
        config()->set('services.deepseek.key', 'sk-prueba');

        $user = User::factory()->create();
        $otro = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create([
            'nombre' => 'Taller de Testing & Calidad',
            'planificacion' => [
                'calendario' => [[
                    'fecha' => '2026-03-09',
                    'bloques' => [[
                        'orden' => 1,
                        'tipo' => 'CLASE',
                        'duracion_minutos' => 90,
                        'unidad' => 1,
                        'aprendizaje_esperado' => 'Explica el origen',
                        'criterios_evaluacion' => ['Fundamenta con fuentes'],
                        'contenidos_obligatorios' => [
                            ['nombre' => 'Línea de tiempo', 'minutos_asignados' => 90],
                        ],
                        'tipo_habilidad' => 'Análisis',
                    ]],
                ]],
            ],
        ]);

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'titulo' => 'Guía de orígenes',
                        'introduccion' => 'Esta guía permite estudiar sin la clase.',
                        'apartados' => [[
                            'contenido' => 'Línea de tiempo',
                            'explicacion' => 'Una línea de tiempo ordena hechos.',
                            'ejemplo' => '1810, 1818 y 1973.',
                        ]],
                        'ejercicios' => [
                            ['enunciado' => 'Ordena tres hechos.', 'orientacion' => 'Del más antiguo al más reciente.'],
                            ['enunciado' => 'Explica para qué sirve.', 'orientacion' => 'Sirve para ver secuencia.'],
                        ],
                    ], JSON_THROW_ON_ERROR)],
                ]],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('asignaturas.guias.generar', $asignatura), [
                'fecha' => '2026-03-09',
                'orden' => 1,
            ])
            ->assertOk()
            ->assertJsonStructure(['url']);

        $guia = GuiaEstudio::query()->first();
        $this->assertNotNull($guia);
        $this->assertSame(['Línea de tiempo'], $guia->contenidos);
        $this->assertSame('Guía de orígenes', $guia->guia['titulo']);
        Storage::disk('local')->assertExists($guia->archivo_path);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($guia->archivo_path)) === true);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        $this->assertNotFalse($xml);
        $this->assertStringContainsString('Testing &amp; Calidad', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));

        $this->actingAs($user)
            ->get(route('asignaturas.guias.descargar', [$asignatura, $guia]))
            ->assertOk();

        $this->actingAs($otro)
            ->get(route('asignaturas.guias.descargar', [$asignatura, $guia]))
            ->assertNotFound();
    }
}
