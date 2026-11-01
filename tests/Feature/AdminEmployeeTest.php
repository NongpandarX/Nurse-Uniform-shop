<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminEmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            BranchSeeder::class,
            UserSeeder::class,
        ]);

        $this->admin = User::where('role', Role::ADMIN)->first();
        $this->employee = User::where('role', Role::EMPLOYEE)->first();
        $this->branch = Branch::first();
    }

    public function test_non_admin_cannot_access_employee_management(): void
    {
        // ผู้ใช้ทั่วไปที่ยังไม่ล็อกอิน
        $response = $this->get('/admin/employees');
        $response->assertRedirect('/login');

        // พนักงานประจำสาขา (role: employee)
        $response = $this->actingAs($this->employee)->get('/admin/employees');
        $response->assertForbidden();
    }

    public function test_admin_can_view_employee_list(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/employees');

        $response->assertOk();
        $response->assertSee('จัดการบัญชีพนักงาน');
        $response->assertSee($this->employee->employee_code);
        $response->assertSee($this->employee->name);
    }

    public function test_admin_can_view_create_employee_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/employees/create');

        $response->assertOk();
        $response->assertSee('เพิ่มพนักงานใหม่');
        $response->assertSee($this->branch->name);
    }

    public function test_admin_can_create_employee_account_only(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/employees', [
            'employee_code' => 'KKC-999',
            'name' => 'พนักงาน ทดสอบใหม่',
            'phone' => '0899999999',
            'branch_id' => $this->branch->id,
            'password' => 'secret123',
            'role' => 'admin', // ทดสอบพยายามส่ง role: admin
        ]);

        $response->assertRedirect('/admin/employees');
        $response->assertSessionHas('success');

        // ตรวจสอบในฐานข้อมูลว่าต้องถูกบันทึกเป็น role: employee เท่านั้น (ไม่ใช่ admin)
        $newEmployee = User::where('employee_code', 'KKC-999')->first();
        $this->assertNotNull($newEmployee);
        $this->assertEquals(Role::EMPLOYEE, $newEmployee->role);
        $this->assertEquals($this->branch->id, $newEmployee->branch_id);
        $this->assertTrue($newEmployee->is_active);
        $this->assertTrue(Hash::check('secret123', $newEmployee->password));
    }

    public function test_create_employee_validation_fails_without_branch(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/employees', [
            'employee_code' => 'KKC-888',
            'name' => 'ไม่มีสาขา',
            'password' => 'secret123',
            // ขาด branch_id
        ]);

        $response->assertSessionHasErrors(['branch_id']);
        $this->assertDatabaseMissing('users', ['employee_code' => 'KKC-888']);
    }

    public function test_admin_can_update_employee(): void
    {
        $response = $this->actingAs($this->admin)->put("/admin/employees/{$this->employee->id}", [
            'name' => 'ชื่อที่แก้ไขแล้ว',
            'phone' => '0811112222',
            'branch_id' => $this->branch->id,
        ]);

        $response->assertRedirect('/admin/employees');
        $this->assertEquals('ชื่อที่แก้ไขแล้ว', $this->employee->fresh()->name);
        $this->assertEquals('0811112222', $this->employee->fresh()->phone);
        $this->assertEquals(Role::EMPLOYEE, $this->employee->fresh()->role);
    }

    public function test_admin_can_toggle_employee_active_status(): void
    {
        $initialStatus = $this->employee->is_active;

        $response = $this->actingAs($this->admin)->post("/admin/employees/{$this->employee->id}/toggle-active");

        $response->assertRedirect();
        $this->assertEquals(!$initialStatus, $this->employee->fresh()->is_active);
    }

    public function test_admin_can_reset_employee_password(): void
    {
        $response = $this->actingAs($this->admin)->post("/admin/employees/{$this->employee->id}/reset-password", [
            'password' => 'newpassword123',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Hash::check('newpassword123', $this->employee->fresh()->password));
    }
}

