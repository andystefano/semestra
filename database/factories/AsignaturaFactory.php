<?php

namespace Database\Factories;

use App\Models\Asignatura;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asignatura>
 */
class AsignaturaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nombre' => fake()->words(3, true),
            'descripcion' => fake()->optional()->sentence(),
            'fecha_inicio' => '2026-03-02',
            'fecha_termino' => '2026-07-03',
        ];
    }
}
