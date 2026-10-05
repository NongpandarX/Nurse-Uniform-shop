<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\StockLevel;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\StockLevelSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            BranchSeeder::class,
            UserSeeder::class,
            ProductSeeder::class,
            StockLevelSeeder::class,
        ]);
    }

    public function test_employee_query_is_automatically_scoped_to_own_branch(): void
    {
        $employee = User::where('employee_code', 'KKC-001')->first();
        $this->actingAs($employee);

        // Eloquent query on StockLevel with BranchScoped
        $levels = StockLevel::all();

        $this->assertNotEmpty($levels);
        foreach ($levels as $level) {
            $this->assertEquals($employee->branch_id, $level->branch_id);
        }
    }

    public function test_employee_cannot_access_other_branch_via_api(): void
    {
        $employee = User::where('employee_code', 'KKC-001')->first(); // Branch KKC
        $otherBranch = Branch::where('code', 'NMA')->first(); // Branch NMA

        $response = $this->actingAs($employee, 'sanctum')
            ->getJson('/api/v1/stock?branch_id=' . $otherBranch->id);

        $response->assertStatus(403);
    }
}
