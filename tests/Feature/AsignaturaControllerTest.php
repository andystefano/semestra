<?php

namespace Tests\Feature;

use App\DiaSemana;
use App\Models\Asignatura;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AsignaturaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_the_subject_list(): void
    {
        $this->get(route('asignaturas.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_from_subject_preparation(): void
    {
        $this->get(route('asignaturas.create'))
            ->assertRedirect(route('login'));
    }

    public function test_preparation_page_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('asignaturas.create'))
            ->assertOk()
            ->assertSee('Definir')
            ->assertSee('horario semanal');
    }

    public function test_menu_groups_the_subject_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('asignaturas.index'))
            ->assertOk()
            ->assertSee('Asignaturas')
            ->assertSee('Listado de Asignaturas')
            ->assertSee('Preparar asignatura');
    }

    public function test_valid_subject_is_stored_with_schedule_and_excluded_dates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('asignaturas.store'), $this->payload())
            ->assertRedirect(route('asignaturas.show', Asignatura::query()->where('nombre', 'Cálculo I')->first()));

        $asignatura = Asignatura::query()->where('nombre', 'Cálculo I')->first();

        $this->assertNotNull($asignatura);
        $this->assertSame($user->id, $asignatura->user_id);
        $this->assertSame('Primera asignatura', $asignatura->descripcion);
        $this->assertSame('2026-03-02', $asignatura->fecha_inicio->toDateString());
        $this->assertSame('2026-07-03', $asignatura->fecha_termino->toDateString());
        $horario = $asignatura->horarios()->first();
        $excluida = $asignatura->fechasExcluidas()->first();

        $this->assertSame(DiaSemana::Lunes, $horario->dia);
        $this->assertSame('08:00', substr((string) $horario->hora_inicio, 0, 5));
        $this->assertSame('09:30', substr((string) $horario->hora_termino, 0, 5));
        $this->assertSame('2026-05-01', $excluida->fecha->toDateString());
        $this->assertNull($excluida->comentario);

        $primera = $asignatura->clases()->where('numero', 1)->first();
        $sinClase = $asignatura->clases()->whereDate('fecha', '2026-05-01')->first();

        $this->assertSame('2026-03-02', $primera->fecha->toDateString());
        $this->assertFalse($primera->sin_clase);
        $this->assertSame('1.50', $primera->horas_cronologicas);
        $this->assertSame('2.00', $primera->horas_pedagogicas);
        $this->assertSame(18, $asignatura->clases()->where('sin_clase', false)->count());
        $this->assertTrue($sinClase->sin_clase);
        $this->assertNull($sinClase->numero);
    }

    public function test_cancelled_class_dates_are_not_numbered_and_keep_their_comment(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('asignaturas.store'), $this->payload([
                'fecha_inicio' => '02/03/2026',
                'fecha_termino' => '16/03/2026',
                'fechas_sin_clase' => [[
                    'fecha' => '09/03/2026',
                    'comentario' => 'Feriado',
                ]],
            ]))
            ->assertRedirect();

        $asignatura = Asignatura::query()->first();
        $clases = $asignatura->clases()->get();

        $this->assertSame([1, null, 2], $clases->pluck('numero')->all());
        $this->assertSame('Feriado', $clases->firstWhere(fn ($clase) => $clase->sin_clase)->comentario);
        $this->assertSame('1.50', $clases->firstWhere(fn ($clase) => $clase->sin_clase)->horas_cronologicas);

        $this->actingAs($user)
            ->get(route('asignaturas.show', $asignatura))
            ->assertOk()
            ->assertSee('Cálculo I')
            ->assertSee('Calendario')
            ->assertSee('Lista')
            ->assertSee('text-danger', false)
            ->assertSee('Feriado')
            ->assertSee('show active', false)
            ->assertSee('fc-sin-clase', false);
    }

    public function test_subject_preparation_ignores_a_spoofed_owner(): void
    {
        $user = User::factory()->create();
        $intruso = User::factory()->create();

        $this->actingAs($user)->post(route('asignaturas.store'), [
            ...$this->payload(),
            'user_id' => $intruso->id,
        ]);

        $this->assertSame($user->id, Asignatura::query()->first()->user_id);
    }

    public function test_empty_preparation_rejects_the_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('asignaturas.create'))
            ->post(route('asignaturas.store'), [])
            ->assertSessionHasErrors([
                'nombre' => 'El nombre es obligatorio.',
                'fecha_inicio' => 'La fecha de inicio es obligatoria.',
                'fecha_termino' => 'La fecha de término es obligatoria.',
            ]);

        $this->assertDatabaseCount('asignaturas', 0);
    }

    public function test_end_date_before_the_start_date_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('asignaturas.create'))
            ->post(route('asignaturas.store'), $this->payload([
                'fecha_inicio' => '03/07/2026',
                'fecha_termino' => '02/03/2026',
            ]))
            ->assertSessionHasErrors([
                'fecha_termino' => 'La fecha de término debe ser igual o posterior a la fecha de inicio.',
            ]);

        $this->assertDatabaseCount('asignaturas', 0);
    }

    public function test_schedule_end_time_must_be_after_the_start_time(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('asignaturas.create'))
            ->post(route('asignaturas.store'), $this->payload([
                'horarios' => [[
                    'dia' => 1,
                    'hora_inicio' => '10:00',
                    'hora_termino' => '09:00',
                ]],
            ]))
            ->assertSessionHasErrors([
                'horarios.0.hora_termino' => 'La hora de término debe ser posterior a la hora de inicio.',
            ]);

        $this->assertDatabaseCount('asignaturas', 0);
    }

    public function test_excluded_date_outside_the_period_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('asignaturas.create'))
            ->post(route('asignaturas.store'), $this->payload([
                'fechas_sin_clase' => ['15/08/2026'],
            ]))
            ->assertSessionHasErrors([
                'fechas_sin_clase.0.fecha' => 'La fecha sin clase debe estar dentro del período de la asignatura.',
            ]);

        $this->assertDatabaseCount('asignaturas', 0);
    }

    public function test_list_only_shows_subjects_of_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $propia = Asignatura::factory()->for($user)->create(['nombre' => 'Álgebra']);
        Asignatura::factory()->create(['nombre' => 'Física ajena']);

        $this->actingAs($user)
            ->get(route('asignaturas.index'))
            ->assertOk()
            ->assertSee($propia->nombre)
            ->assertDontSee('Física ajena');
    }

    public function test_editing_another_users_subject_returns_not_found(): void
    {
        $user = User::factory()->create();
        $ajena = Asignatura::factory()->create();

        $this->actingAs($user)
            ->get(route('asignaturas.edit', $ajena))
            ->assertNotFound();
    }

    public function test_updating_replaces_the_schedule_and_excluded_dates(): void
    {
        $user = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create();
        $asignatura->horarios()->create([
            'dia' => 1,
            'hora_inicio' => '08:00',
            'hora_termino' => '09:00',
        ]);
        $asignatura->fechasExcluidas()->create(['fecha' => '2026-05-01']);

        $this->actingAs($user)
            ->put(route('asignaturas.update', $asignatura), $this->payload([
                'nombre' => 'Cálculo II',
                'horarios' => [[
                    'dia' => 3,
                    'hora_inicio' => '14:00',
                    'hora_termino' => '15:30',
                ]],
                'fechas_sin_clase' => ['18/05/2026'],
            ]))
            ->assertRedirect(route('asignaturas.show', $asignatura));

        $asignatura->refresh();

        $this->assertSame('Cálculo II', $asignatura->nombre);
        $this->assertSame(DiaSemana::Miercoles, $asignatura->horarios()->first()->dia);
        $this->assertSame(1, $asignatura->horarios()->count());
        $this->assertSame('2026-05-18', $asignatura->fechasExcluidas()->first()->fecha->toDateString());
        $this->assertSame(1, $asignatura->fechasExcluidas()->count());
    }

    public function test_subject_name_is_escaped_in_the_list(): void
    {
        $user = User::factory()->create();
        Asignatura::factory()->for($user)->create([
            'nombre' => "<script>alert('xss')</script>",
        ]);

        $response = $this->actingAs($user)->get(route('asignaturas.index'));

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
    }

    public function test_subject_page_offers_the_next_step(): void
    {
        $user = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('asignaturas.show', $asignatura))
            ->assertOk()
            ->assertSee('Paso siguiente');
    }

    public function test_guest_is_redirected_from_the_pdf_step(): void
    {
        $asignatura = Asignatura::factory()->create();

        $this->get(route('asignaturas.documento', $asignatura))
            ->assertRedirect(route('login'));
    }

    public function test_pdf_step_of_another_user_returns_not_found(): void
    {
        $user = User::factory()->create();
        $ajena = Asignatura::factory()->create();

        $this->actingAs($user)
            ->get(route('asignaturas.documento', $ajena))
            ->assertNotFound();
    }

    public function test_valid_pdf_is_stored_for_the_subject(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create();
        $archivo = UploadedFile::fake()->create('programa.pdf', 120, 'application/pdf');

        $this->actingAs($user)
            ->post(route('asignaturas.documento.store', $asignatura), [
                'pdf' => $archivo,
            ])
            ->assertRedirect(route('asignaturas.documento', $asignatura));

        $asignatura->refresh();

        $this->assertSame('programa.pdf', $asignatura->pdf_nombre);
        $this->assertNotNull($asignatura->pdf_path);
        Storage::disk('local')->assertExists($asignatura->pdf_path);
    }

    public function test_non_pdf_upload_is_rejected(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create();

        $this->actingAs($user)
            ->from(route('asignaturas.documento', $asignatura))
            ->post(route('asignaturas.documento.store', $asignatura), [
                'pdf' => UploadedFile::fake()->create('notas.txt', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors([
                'pdf' => 'El archivo debe ser un PDF.',
            ]);

        $this->assertNull($asignatura->refresh()->pdf_path);
    }

    public function test_document_page_offers_file_processing_when_a_pdf_exists(): void
    {
        $user = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create([
            'pdf_path' => 'asignaturas/1/programa.pdf',
            'pdf_nombre' => 'programa.pdf',
        ]);

        $this->actingAs($user)
            ->get(route('asignaturas.documento', $asignatura))
            ->assertOk()
            ->assertSee('Procesar archivo');
    }

    public function test_processing_stores_the_markdown_from_llamaparse(): void
    {
        Storage::fake('local');
        config()->set('services.llamacloud.key', 'llx-prueba');

        $user = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create([
            'pdf_path' => 'asignaturas/1/programa.pdf',
            'pdf_nombre' => 'programa.pdf',
        ]);
        Storage::disk('local')->put($asignatura->pdf_path, '%PDF-1.4');

        Http::fake(function ($request) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/api/v1/beta/files')) {
                return Http::response(['id' => 'file-1']);
            }

            if ($request->method() === 'POST' && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/api/v2/parse')) {
                return Http::response(['id' => 'job-1', 'status' => 'PENDING']);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/api/v2/parse/job-1')) {
                return Http::response([
                    'job' => ['status' => 'COMPLETED'],
                    'markdown_full' => "# Programa\n\nContenido",
                ]);
            }

            return Http::response(['message' => 'unexpected'], 500);
        });

        $this->actingAs($user)
            ->postJson(route('asignaturas.documento.procesar', $asignatura))
            ->assertOk()
            ->assertJsonPath('message', 'Proceso terminado')
            ->assertJsonPath('markdown', "# Programa\n\nContenido");

        $this->assertSame("# Programa\n\nContenido", $asignatura->refresh()->markdown);
    }

    public function test_processing_without_a_pdf_is_rejected(): void
    {
        $user = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson(route('asignaturas.documento.procesar', $asignatura))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Primero carga un archivo PDF.');
    }

    public function test_learning_button_is_available_after_the_file_is_processed(): void
    {
        $user = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create([
            'markdown' => '# Programa',
        ]);

        $this->actingAs($user)
            ->get(route('asignaturas.documento', $asignatura))
            ->assertOk()
            ->assertSee('Aprender a partir de programa del módulo');
    }

    public function test_deepseek_stores_units_as_json_and_the_accordion_lists_them(): void
    {
        config()->set('services.deepseek.key', 'sk-prueba');

        $user = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create([
            'nombre' => 'Historia',
            'markdown' => '# Unidad 1',
        ]);

        Http::fake([
            'api.deepseek.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'cantidad' => 1,
                            'unidades' => [[
                                'numero' => 1,
                                'nombre' => 'Orígenes',
                                'horas_sugeridas' => 12,
                                'aprendizaje_esperado' => 'Explica el origen',
                                'criterios_evaluacion' => ['Fundamenta con fuentes'],
                                'contenidos_obligatorios' => ['Línea de tiempo'],
                                'tipo_habilidad' => 'Análisis',
                                'competencias_personales_sociales_valoricas' => 'Trabajo colaborativo',
                            ]],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('asignaturas.documento.aprender', $asignatura))
            ->assertOk()
            ->assertJsonPath('url', route('asignaturas.unidades', $asignatura));

        $guardado = $asignatura->refresh()->unidades;

        $this->assertSame(1, $guardado['cantidad']);
        $this->assertSame('Orígenes', $guardado['unidades'][0]['nombre']);
        $this->assertSame(12, $guardado['unidades'][0]['horas_sugeridas']);
        $this->assertSame(['Línea de tiempo'], $guardado['unidades'][0]['contenidos_obligatorios']);
        $this->assertSame('Análisis', $guardado['unidades'][0]['tipo_habilidad']);

        $this->actingAs($user)
            ->get(route('asignaturas.unidades', $asignatura))
            ->assertOk()
            ->assertSee('Unidad 1')
            ->assertSee('Orígenes')
            ->assertSee('Explica el origen')
            ->assertSee('Fundamenta con fuentes')
            ->assertSee('Trabajo colaborativo')
            ->assertSee('id="acordeon-unidades"', false)
            ->assertSee('Continuar con actividades');
    }

    public function test_activity_summary_lists_fixed_days_and_one_evaluation_per_unit(): void
    {
        $user = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create([
            'fecha_inicio' => '2026-03-02',
            'fecha_termino' => '2026-03-30',
            'unidades' => [
                'cantidad' => 2,
                'unidades' => [[], []],
            ],
        ]);

        foreach (['2026-03-02', '2026-03-09', '2026-03-16', '2026-03-23', '2026-03-30'] as $numero => $fecha) {
            $asignatura->clases()->create([
                'numero' => $numero + 1,
                'fecha' => $fecha,
                'hora_inicio' => '08:00',
                'hora_termino' => '09:30',
                'sin_clase' => false,
            ]);
        }

        $asignatura->clases()->create([
            'numero' => 5,
            'fecha' => '2026-03-02',
            'hora_inicio' => '10:00',
            'hora_termino' => '11:00',
            'sin_clase' => false,
        ]);

        $asignatura->clases()->create([
            'numero' => null,
            'fecha' => '2026-03-12',
            'sin_clase' => true,
            'comentario' => 'Feriado',
        ]);

        $this->actingAs($user)
            ->get(route('asignaturas.actividades', $asignatura))
            ->assertOk()
            ->assertSee('02/03/2026 a 30/03/2026')
            ->assertSee('6')
            ->assertSee('5')
            ->assertSee('Presentación de inicio y evaluación diagnóstica')
            ->assertSee('02/03/2026')
            ->assertSee('Evaluación de 60 minutos')
            ->assertSee('Unidad 2')
            ->assertSee('El día completo queda bloqueado: no se enseñan clases y el resto del día queda libre.')
            ->assertSee('09/03/2026')
            ->assertSee('Prueba recuperativa')
            ->assertSee('16/03/2026')
            ->assertSee('Examen 1')
            ->assertSee('23/03/2026')
            ->assertSee('Examen de repetición')
            ->assertSee('30/03/2026')
            ->assertDontSee('No alcanzan los días de clase');
    }

    public function test_activity_summary_warns_when_there_are_fewer_than_four_class_days(): void
    {
        $user = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create([
            'fecha_inicio' => '2026-03-02',
            'fecha_termino' => '2026-03-09',
            'unidades' => ['cantidad' => 1, 'unidades' => [[]]],
        ]);

        foreach (['2026-03-02', '2026-03-09'] as $numero => $fecha) {
            $asignatura->clases()->create([
                'numero' => $numero + 1,
                'fecha' => $fecha,
                'hora_inicio' => '08:00',
                'hora_termino' => '09:00',
                'sin_clase' => false,
            ]);
        }

        $this->actingAs($user)
            ->get(route('asignaturas.actividades', $asignatura))
            ->assertOk()
            ->assertSee('No alcanzan los días de clase para separar la presentación, el día bloqueado de la última unidad y las tres evaluaciones finales.');
    }

    public function test_learning_without_processed_markdown_is_rejected(): void
    {
        $user = User::factory()->create();
        $asignatura = Asignatura::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson(route('asignaturas.documento.aprender', $asignatura))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Primero procesa el archivo PDF.');
    }

    public function test_only_the_owner_can_download_the_pdf(): void
    {
        Storage::fake('local');

        $dueno = User::factory()->create();
        $otro = User::factory()->create();
        $asignatura = Asignatura::factory()->for($dueno)->create([
            'pdf_path' => 'asignaturas/1/programa.pdf',
            'pdf_nombre' => 'programa.pdf',
        ]);
        Storage::disk('local')->put($asignatura->pdf_path, '%PDF-1.4');

        $this->actingAs($dueno)
            ->get(route('asignaturas.documento.descargar', $asignatura))
            ->assertOk();

        $this->actingAs($otro)
            ->get(route('asignaturas.documento.descargar', $asignatura))
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $reemplazos
     * @return array<string, mixed>
     */
    private function payload(array $reemplazos = []): array
    {
        return [
            'nombre' => 'Cálculo I',
            'descripcion' => 'Primera asignatura',
            'fecha_inicio' => '02/03/2026',
            'fecha_termino' => '03/07/2026',
            'horarios' => [[
                'dia' => 1,
                'hora_inicio' => '08:00',
                'hora_termino' => '09:30',
            ]],
            'fechas_sin_clase' => ['01/05/2026'],
            ...$reemplazos,
        ];
    }
}
