<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BranchSeeder::class);
        $this->seed(UserSeeder::class);
    }

    public function test_admin_can_login_via_api(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'employee_code' => 'ADMIN-001',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user' => ['id', 'employee_code', 'role']])
            ->assertJsonPath('user.role', 'admin');
    }

    public function test_employee_can_login_via_api(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'employee_code' => 'KKC-001',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user' => ['id', 'employee_code', 'role', 'branch_id']])
            ->assertJsonPath('user.role', 'employee');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'employee_code' => 'ADMIN-001',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }
}
