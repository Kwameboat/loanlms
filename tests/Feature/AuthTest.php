<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email'    => 'test@bigcash.com',
            'password' => Hash::make('Password@123'),
        ]);

        $response = $this->post('/login', [
            'email'    => 'test@bigcash.com',
            'password' => 'Password@123',
        ]);

        $this->assertAuthenticated();
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'email'    => 'test2@bigcash.com',
            'password' => Hash::make('Password@123'),
        ]);

        $this->post('/login', [
            'email'    => 'test2@bigcash.com',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_health_check_returns_ok(): void
    {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200)
                 ->assertJsonFragment(['app' => 'Big Cash']);
    }
}
