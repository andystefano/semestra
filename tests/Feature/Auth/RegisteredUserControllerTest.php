<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisteredUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_renders(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Crear cuenta');
    }

    public function test_valid_registration_creates_the_user_and_starts_a_session(): void
    {
        $this->post(route('register'), [
            'name' => 'Ana López',
            'email' => 'ana@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'ana@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('Ana López', $user->name);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_ignores_email_verification_spoofing(): void
    {
        $this->post(route('register'), [
            'name' => 'Ana López',
            'email' => 'ana@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'email_verified_at' => now()->toDateTimeString(),
        ])->assertRedirect(route('dashboard'));

        $this->assertNull(User::query()->where('email', 'ana@example.com')->value('email_verified_at'));
    }

    public function test_empty_registration_rejects_required_fields(): void
    {
        $this->from(route('register'))->post(route('register'), [])
            ->assertSessionHasErrors([
                'name' => 'El nombre es obligatorio.',
                'email' => 'El correo electrónico es obligatorio.',
                'password' => 'La contraseña es obligatoria.',
            ]);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_duplicate_email_rejects_registration(): void
    {
        User::factory()->create([
            'email' => 'ana@example.com',
        ]);

        $this->from(route('register'))->post(route('register'), [
            'name' => 'Ana López',
            'email' => 'ana@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors([
            'email' => 'Este correo electrónico ya está registrado.',
        ]);

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->from(route('register'))->post(route('register'), [
            'name' => 'Ana López',
            'email' => 'ana@example.com',
            'password' => 'password',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors([
            'password' => 'La confirmación de la contraseña no coincide.',
        ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_short_password_rejects_registration(): void
    {
        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'Ana López',
            'email' => 'ana@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors([
            'password' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_authenticated_user_is_redirected_away_from_registration(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('register'))
            ->assertRedirect(route('dashboard'));
    }
}
