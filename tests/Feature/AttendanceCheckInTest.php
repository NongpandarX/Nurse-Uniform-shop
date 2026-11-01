<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\User;
use App\Models\UserDevice;
use Carbon\Carbon;
use Database\Seeders\BranchSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;
    protected Branch $branch;
    protected UserDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            BranchSeeder::class,
            UserSeeder::class,
        ]);

        $this->admin = User::where('role', Role::ADMIN)->first();
        $this->employee = User::where('employee_code', 'KKC-001')->first();
        $this->branch = Branch::where('code', 'KKC')->first();
        $this->device = UserDevice::where('user_id', $this->employee->id)->first();
    }

    /**
     * 1. พนักงานเช็คชื่อเข้างานตรงเวลา (อยู่ในรัศมี 50 ม. และเวลาอยู่ในเกณฑ์)
     */
    public function test_employee_can_check_in_on_time(): void
    {
        // กำหนดเวลา 09:05 น. (ร้านเปิด 09:00 + grace 10 นาที ถือว่าตรงเวลา)
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:05:00'));

        $response = $this->actingAs($this->employee, 'sanctum')
            ->withHeader('X-Device-Uid', $this->device->device_uid)
            ->postJson('/api/v1/attendance/check-in', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 15.0,
                'is_mock' => false,
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'บันทึกเวลาเข้างานสำเร็จ',
            ]);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'status' => AttendanceStatus::ON_TIME->value,
            'late_minutes' => 0,
        ]);
    }

    /**
     * 2. เช็คชื่อก่อนเวลาเปิดเกิน 60 นาที (เช่น 07:30 เมื่อเปิด 09:00) ต้องถูกปฏิเสธ (422)
     */
    public function test_check_in_too_early_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 07:30:00'));

        $response = $this->actingAs($this->employee, 'sanctum')
            ->withHeader('X-Device-Uid', $this->device->device_uid)
            ->postJson('/api/v1/attendance/check-in', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 10.0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['attendance']);
    }

    /**
     * 3. เช็คชื่อเข้างานสาย (เกิน open_time + grace_minutes)
     * บันทึกสถานะ late, คำนวณ late_minutes และยิงแจ้งเตือนแอดมินทันที
     */
    public function test_employee_check_in_late_records_minutes_and_notifies_admin(): void
    {
        // กำหนดเวลา 09:20 น. (เปิด 09:00, grace 10 นาที -> สาย 20 นาที)
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:20:00'));

        $response = $this->actingAs($this->employee, 'sanctum')
            ->withHeader('X-Device-Uid', $this->device->device_uid)
            ->postJson('/api/v1/attendance/check-in', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 12.0,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->employee->id,
            'status' => AttendanceStatus::LATE->value,
            'late_minutes' => 20,
        ]);

        // ตรวจสอบว่ามี Notification ส่งหา Admin (§5.7: 🕒 อุบล: มาสาย 15 นาที)
        $notif = AppNotification::where('user_id', $this->admin->id)
            ->where('type', 'staff_late')
            ->latest('id')
            ->first();

        $this->assertNotNull($notif);
        $this->assertStringContainsString('🕒', $notif->title);
        $this->assertStringContainsString('มาสาย 20 นาที (ไม่หักเงิน)', $notif->title);
    }

    /**
     * 4. เช็คชื่อเข้างานซ้ำในวันเดียวกัน ต้องตอบกลับ 409 Conflict (§5.2)
     */
    public function test_duplicate_check_in_returns_409(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00'));

        // เข้างานครั้งแรก
        $this->actingAs($this->employee, 'sanctum')
            ->withHeader('X-Device-Uid', $this->device->device_uid)
            ->postJson('/api/v1/attendance/check-in', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 10.0,
            ])->assertOk();

        // เข้างานซ้ำในวันเดิม
        $response = $this->actingAs($this->employee, 'sanctum')
            ->withHeader('X-Device-Uid', $this->device->device_uid)
            ->postJson('/api/v1/attendance/check-in', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 10.0,
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'คุณได้ทำการเช็คชื่อเข้างานของวันนี้ไปแล้ว',
            ]);
    }

    /**
     * 5. ออกงานโดยที่ยังไม่ได้เข้างาน ต้องถูกปฏิเสธ (422)
     */
    public function test_check_out_without_check_in_fails(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 18:00:00'));

        $response = $this->actingAs($this->employee, 'sanctum')
            ->withHeader('X-Device-Uid', $this->device->device_uid)
            ->postJson('/api/v1/attendance/check-out', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 10.0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['attendance']);
    }

    /**
     * 6. ออกงานก่อนเวลา (close_time) โดยไม่ระบุเหตุผล ต้องถูกปฏิเสธ (422)
     */
    public function test_early_check_out_without_note_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00'));

        // เข้างานก่อน
        $this->actingAs($this->employee, 'sanctum')
            ->withHeader('X-Device-Uid', $this->device->device_uid)
            ->postJson('/api/v1/attendance/check-in', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 10.0,
            ])->assertOk();

        // พยายามออกงานตอน 15:00 น. (ร้านปิด 18:00 น.) โดยไม่ระบุเหตุผล
        Carbon::setTestNow(Carbon::parse('2026-11-02 15:00:00'));

        $response = $this->actingAs($this->employee, 'sanctum')
            ->withHeader('X-Device-Uid', $this->device->device_uid)
            ->postJson('/api/v1/attendance/check-out', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 10.0,
                'note' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['note']);
    }

    /**
     * 7. ออกงานก่อนเวลาพร้อมระบุเหตุผล สำเร็จและบันทึกสถานะ early_leave
     */
    public function test_early_check_out_with_note_succeeds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00'));

        $this->actingAs($this->employee, 'sanctum')
            ->withHeader('X-Device-Uid', $this->device->device_uid)
            ->postJson('/api/v1/attendance/check-in', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 10.0,
            ])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-11-02 16:00:00'));

        $response = $this->actingAs($this->employee, 'sanctum')
            ->withHeader('X-Device-Uid', $this->device->device_uid)
            ->postJson('/api/v1/attendance/check-out', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 10.0,
                'note' => 'ขออนุญาตไปพบแพทย์',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->employee->id,
            'status' => AttendanceStatus::EARLY_LEAVE->value,
            'note' => 'ขออนุญาตไปพบแพทย์',
        ]);
    }

    /**
     * 8. ออกงานตามเวลาปกติ (หลัง close_time 18:00 น.)
     */
    public function test_on_time_check_out_succeeds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00'));

        $this->actingAs($this->employee, 'sanctum')
            ->withHeader('X-Device-Uid', $this->device->device_uid)
            ->postJson('/api/v1/attendance/check-in', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 10.0,
            ])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-11-02 18:05:00'));

        $response = $this->actingAs($this->employee, 'sanctum')
            ->withHeader('X-Device-Uid', $this->device->device_uid)
            ->postJson('/api/v1/attendance/check-out', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 10.0,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->employee->id,
            'status' => AttendanceStatus::ON_TIME->value,
        ]);
    }

    /**
     * 9. สาขาเชียงใหม่ (หยุดวันอาทิตย์) เมื่อพนักงานพยายามเช็คชื่อวันอาทิตย์ จะถูกปฏิเสธ (403)
     */
    public function test_chiang_mai_blocked_on_sunday(): void
    {
        $cnxEmployee = User::where('employee_code', 'CNX-001')->first();
        $cnxDevice = UserDevice::where('user_id', $cnxEmployee->id)->first();
        $cnxBranch = Branch::where('code', 'CNX1')->first();

        // 2026-11-01 คือวันอาทิตย์
        Carbon::setTestNow(Carbon::parse('2026-11-01 09:00:00'));

        $response = $this->actingAs($cnxEmployee, 'sanctum')
            ->withHeader('X-Device-Uid', $cnxDevice->device_uid)
            ->postJson('/api/v1/attendance/check-in', [
                'lat' => $cnxBranch->lat,
                'lng' => $cnxBranch->lng,
                'accuracy' => 10.0,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'วันนี้เป็นวันหยุดทำการของสาขา (' . $cnxBranch->name . ')',
            ]);
    }

    /**
     * 10. ดึงประวัติเข้างานของตนเอง GET /api/v1/attendance/me
     */
    public function test_employee_can_view_own_attendance_history(): void
    {
        Attendance::create([
            'user_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'work_date' => '2026-11-01',
            'check_in_at' => '2026-11-01 09:00:00',
            'status' => AttendanceStatus::ON_TIME,
        ]);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/v1/attendance/me?month=2026-11');

        $response->assertOk()
            ->assertJsonStructure([
                'attendances',
            ]);

        $this->assertCount(1, $response->json('attendances'));
    }

    /**
     * 11. ตรวจสอบคำสั่ง Artisan: attendance:mark-absent (00:10 น.)
     * บันทึกพนักงานที่ไม่ได้เช็คชื่อในวันที่สาขาเปิดให้เป็นขาดงาน (absent)
     */
    public function test_attendance_mark_absent_command(): void
    {
        // วันจันทร์ที่ 2026-11-02 สาขา KKC เปิดทำการ แต่พนักงานไม่ได้เช็คชื่อ
        $this->artisan('attendance:mark-absent', ['date' => '2026-11-02'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'status' => AttendanceStatus::ABSENT->value,
        ]);
    }

    /**
     * 12. แอดมินสามารถแก้มือบันทึกเวลาเข้างานพร้อม manual_reason ได้
     */
    public function test_admin_can_update_attendance_with_manual_reason(): void
    {
        $attendance = Attendance::create([
            'user_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'work_date' => '2026-11-02',
            'status' => AttendanceStatus::ABSENT,
        ]);

        $response = $this->actingAs($this->admin)
            ->put("/admin/attendances/{$attendance->id}", [
                'check_in_at' => '2026-11-02 09:00:00',
                'check_out_at' => '2026-11-02 18:00:00',
                'status' => AttendanceStatus::MANUAL->value,
                'manual_reason' => 'พนักงานลืมนำโทรศัพท์มา แต่เข้างานตรงเวลาจริง',
            ]);

        $response->assertRedirect('/admin/attendances');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'status' => AttendanceStatus::MANUAL->value,
            'manual_reason' => 'พนักงานลืมนำโทรศัพท์มา แต่เข้างานตรงเวลาจริง',
            'edited_by' => $this->admin->id,
        ]);
    }
}
