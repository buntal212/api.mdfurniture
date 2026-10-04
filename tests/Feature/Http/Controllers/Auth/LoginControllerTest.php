<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_authenticated_user_from_sanctum_api(): void
    {
        $user = User::factory()->create(['username' => 'md-admin']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('username', 'md-admin');
    }

    public function test_returns_422_when_username_and_password_are_missing(): void
    {
        $response = $this->postJson('/api/login');

        $response
            ->assertUnprocessable()
            ->assertInvalid([
                'username' => 'Username wajib diisi.',
                'password' => 'Kata sandi wajib diisi.',
            ]);
    }

    public function test_authenticates_user_with_valid_username_and_password(): void
    {
        $user = User::factory()->create([
            'username' => 'md-admin',
            'password' => Hash::make('kata-sandi-rahasia'),
        ]);

        $response = $this->postJson('/api/login', [
            'username' => 'md-admin',
            'password' => 'kata-sandi-rahasia',
            'rememberMe' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Login berhasil.')
            ->assertJsonPath('user.username', 'md-admin')
            ->assertJsonStructure(['token']);

        $this->assertGuest();
    }

    public function test_returns_422_when_credentials_do_not_match(): void
    {
        User::factory()->create([
            'username' => 'md-admin',
            'password' => Hash::make('kata-sandi-rahasia'),
        ]);

        $response = $this->postJson('/api/login', [
            'username' => 'md-admin',
            'password' => 'kata-sandi-salah',
        ]);

        $response
            ->assertUnprocessable()
            ->assertInvalid(['username' => 'Username atau kata sandi tidak valid.']);

        $this->assertGuest();
    }
}
