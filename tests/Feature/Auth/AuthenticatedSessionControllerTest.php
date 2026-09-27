<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Iniciar sesión');
    }

    public function test_valid_credentials_start_a_session_and_redirect_to_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_remember_me_sets_the_recall_cookie(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertCookie(Auth::guard()->getRecallerName());
    }

    public function test_invalid_credentials_reject_login(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'Estas credenciales no coinciden con nuestros registros.',
            ]);

        $this->assertGuest();
    }

    public function test_empty_login_rejects_missing_email_and_password(): void
    {
        $this->from(route('login'))->post(route('login'), [])
            ->assertSessionHasErrors([
                'email' => 'El correo electrónico es obligatorio.',
                'password' => 'La contraseña es obligatoria.',
            ]);

        $this->assertGuest();
    }

    public function test_invalid_email_rejects_login(): void
    {
        $this->from(route('login'))->post(route('login'), [
            'email' => 'not-an-email',
            'password' => 'password',
        ])->assertSessionHasErrors([
            'email' => 'El correo electrónico no es válido.',
        ]);
    }

    public function test_sixth_failed_login_is_rate_limited(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_guest_is_redirected_to_login_from_the_dashboard(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_dashboard_escapes_the_user_name(): void
    {
        $user = User::factory()->create([
            'name' => "<script>alert('xss')</script>",
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee("<script>alert('xss')</script>", false);
    }
}
