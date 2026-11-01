<?php

namespace Tests\Feature;

use App\Enums\ChecklistStatus;
use App\Enums\ChecklistType;
use App\Enums\Role;
use App\Events\ShopClosed;
use App\Models\AppNotification;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistTemplate;
use App\Models\DailyClosing;
use App\Models\StockCount;
use App\Models\User;
use App\Services\ClosingService;
use Carbon\Carbon;
use Database\Seeders\BranchSeeder;
use Database\Seeders\ChecklistTemplateSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\StockLevelSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyClosingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;
    protected Branch $branch;
    protected ClosingService $closingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            BranchSeeder::class,
            UserSeeder::class,
            ProductSeeder::class,
            StockLevelSeeder::class,
            ChecklistTemplateSeeder::class,
        ]);

        $this->admin = User::where('role', Role::ADMIN)->first();
        $this->employee = User::where('employee_code', 'KKC-001')->first();
        $this->branch = Branch::where('code', 'KKC')->first();
        $this->closingService = app(ClosingService::class);
    }

    /**
     * 1. พนักงานเรียกดูสถานะความพร้อมปิดร้าน (GET /api/v1/closing/today) เมื่อยังไม่พร้อม
     */
    public function test_employee_can_check_closing_readiness_when_not_ready(): void
    {
        $response = $this->actingAs($this->employee, 'sanctum')
            ->getJson('/api/v1/closing/today');

        $response->assertOk()
            ->assertJson([
                'is_closed' => false,
                'has_submitted_stock_count' => false,
                'has_submitted_closing_checklist' => false,
                'can_close' => false,
            ]);
    }

    /**
     * 2. แจ้งปิดร้านล้มเหลว หากยังไม่ได้ส่งนับสต็อกประจำวัน
     */
    public function test_closing_fails_if_stock_count_not_submitted(): void
    {
        // สร้างเฉพาะ Closing Checklist ที่ส่งแล้ว แต่ยังไม่มี Stock Count
        $template = ChecklistTemplate::where('type', ChecklistType::CLOSING)->first();
        ChecklistSubmission::create([
            'branch_id' => $this->branch->id,
            'template_id' => $template->id,
            'check_date' => now()->toDateString(),
            'submitted_by' => $this->employee->id,
            'submitted_at' => now(),
            'status' => ChecklistStatus::SUBMITTED,
        ]);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/v1/closing', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 15.0,
                'note' => 'ทดสอบปิดร้าน',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['stock_count']);
    }

    /**
     * 3. แจ้งปิดร้านล้มเหลว หากยังไม่ได้ส่งเช็คลิสต์ปิดร้าน
     */
    public function test_closing_fails_if_closing_checklist_not_submitted(): void
    {
        // มีเฉพาะ Stock Count ที่ส่งแล้ว แต่ยังไม่มี Closing Checklist
        StockCount::create([
            'branch_id' => $this->branch->id,
            'count_date' => now()->toDateString(),
            'status' => 'submitted',
            'started_by' => $this->employee->id,
            'submitted_by' => $this->employee->id,
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/v1/closing', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 15.0,
                'note' => 'ทดสอบปิดร้าน',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['closing_checklist']);
    }

    /**
     * 4. พนักงานแจ้งปิดร้านล้มเหลวหากอยู่นอกรัศมี Geofence
     */
    public function test_closing_fails_if_outside_geofence(): void
    {
        StockCount::create([
            'branch_id' => $this->branch->id,
            'count_date' => now()->toDateString(),
            'status' => 'submitted',
            'started_by' => $this->employee->id,
            'submitted_by' => $this->employee->id,
            'submitted_at' => now(),
        ]);

        $template = ChecklistTemplate::where('type', ChecklistType::CLOSING)->first();
        ChecklistSubmission::create([
            'branch_id' => $this->branch->id,
            'template_id' => $template->id,
            'check_date' => now()->toDateString(),
            'submitted_by' => $this->employee->id,
            'submitted_at' => now(),
            'status' => ChecklistStatus::SUBMITTED,
        ]);

        // พิกัดห่างออกไปมาก
        $response = $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/v1/closing', [
                'lat' => $this->branch->lat + 0.5,
                'lng' => $this->branch->lng + 0.5,
                'accuracy' => 10.0,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('คุณอยู่นอกพื้นที่ร้าน', $response->json('message'));
    }

    /**
     * 5. แจ้งปิดร้านสำเร็จเมื่อนับสต็อกและเช็คลิสต์ปิดร้านส่งแล้ว + อยู่ใน Geofence
     */
    public function test_closing_succeeds_when_all_conditions_met(): void
    {
        // 1. ส่ง Stock count
        $stockCount = StockCount::create([
            'branch_id' => $this->branch->id,
            'count_date' => now()->toDateString(),
            'status' => 'submitted',
            'started_by' => $this->employee->id,
            'submitted_by' => $this->employee->id,
            'submitted_at' => now(),
        ]);

        // 2. ส่ง Checklist closing
        $template = ChecklistTemplate::where('type', ChecklistType::CLOSING)->first();
        $checklist = ChecklistSubmission::create([
            'branch_id' => $this->branch->id,
            'template_id' => $template->id,
            'check_date' => now()->toDateString(),
            'submitted_by' => $this->employee->id,
            'submitted_at' => now(),
            'status' => ChecklistStatus::SUBMITTED,
        ]);

        // 3. จำลองพนักงานเข้างานวันนี้
        Attendance::create([
            'user_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'work_date' => now()->toDateString(),
            'check_in_at' => now()->subHours(8),
            'status' => 'on_time',
        ]);

        $response = $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/v1/closing', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 15.0,
                'note' => 'ปิดร้านเรียบร้อย ประตูล็อกแน่นหนา',
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'แจ้งปิดร้านประจำวันสำเร็จ',
                'closing' => [
                    'branch_id' => $this->branch->id,
                    'closing_date' => now()->toDateString(),
                    'stock_count_id' => $stockCount->id,
                    'closing_submission_id' => $checklist->id,
                    'staff_present' => 1,
                    'note' => 'ปิดร้านเรียบร้อย ประตูล็อกแน่นหนา',
                ],
            ]);

        $this->assertDatabaseHas('daily_closings', [
            'branch_id' => $this->branch->id,
            'closing_date' => now()->toDateString(),
            'closed_by' => $this->employee->id,
            'note' => 'ปิดร้านเรียบร้อย ประตูล็อกแน่นหนา',
        ]);
    }

    /**
     * 6. เมื่อปิดร้าน จะยิง Event ShopClosed และสร้าง AppNotification ถึง Admin
     */
    public function test_closing_fires_event_and_notifies_admins(): void
    {
        StockCount::create([
            'branch_id' => $this->branch->id,
            'count_date' => now()->toDateString(),
            'status' => 'submitted',
            'started_by' => $this->employee->id,
            'submitted_by' => $this->employee->id,
            'submitted_at' => now(),
        ]);

        $template = ChecklistTemplate::where('type', ChecklistType::CLOSING)->first();
        ChecklistSubmission::create([
            'branch_id' => $this->branch->id,
            'template_id' => $template->id,
            'check_date' => now()->toDateString(),
            'submitted_by' => $this->employee->id,
            'submitted_at' => now(),
            'status' => ChecklistStatus::SUBMITTED,
        ]);

        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/v1/closing', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 15.0,
                'note' => 'เช็คไฟรอบร้านแล้ว',
            ])->assertOk();

        // ตรวจสอบว่ามี Notification ส่งถึง Admin
        $notification = AppNotification::where('user_id', $this->admin->id)
            ->where('type', 'shop_closed')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString($this->branch->name, $notification->title);
        $this->assertStringContainsString('ปิดร้านแล้ว', $notification->title);
        $this->assertStringContainsString($this->employee->name, $notification->body);
    }

    /**
     * 7. การแจ้งปิดร้านซ้ำในวันเดียวกัน จะอัปเดตหมายเหตุและไม่ออกแถวซ้ำ (Unique branch_id + closing_date)
     */
    public function test_duplicate_closing_updates_note_without_duplicate_row(): void
    {
        StockCount::create([
            'branch_id' => $this->branch->id,
            'count_date' => now()->toDateString(),
            'status' => 'submitted',
            'started_by' => $this->employee->id,
            'submitted_by' => $this->employee->id,
            'submitted_at' => now(),
        ]);

        $template = ChecklistTemplate::where('type', ChecklistType::CLOSING)->first();
        ChecklistSubmission::create([
            'branch_id' => $this->branch->id,
            'template_id' => $template->id,
            'check_date' => now()->toDateString(),
            'submitted_by' => $this->employee->id,
            'submitted_at' => now(),
            'status' => ChecklistStatus::SUBMITTED,
        ]);

        // ครั้งแรก
        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/v1/closing', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 15.0,
                'note' => 'รอบที่ 1',
            ])->assertOk();

        // ครั้งที่สอง (อัปเดตหมายเหตุ)
        $this->actingAs($this->employee, 'sanctum')
            ->postJson('/api/v1/closing', [
                'lat' => $this->branch->lat,
                'lng' => $this->branch->lng,
                'accuracy' => 15.0,
                'note' => 'รอบที่ 2 แก้ไขหมายเหตุ',
            ])->assertOk();

        $this->assertEquals(1, DailyClosing::withoutGlobalScopes()->where('branch_id', $this->branch->id)->whereDate('closing_date', now()->toDateString())->count());
        $this->assertDatabaseHas('daily_closings', [
            'branch_id' => $this->branch->id,
            'note' => 'รอบที่ 2 แก้ไขหมายเหตุ',
        ]);
    }

    /**
     * 8. Admin สามารถเปิดดูหน้ารายการแจ้งปิดร้าน และหน้าแสดงรายละเอียด
     */
    public function test_admin_can_view_closings_index_and_show(): void
    {
        $closing = DailyClosing::create([
            'branch_id' => $this->branch->id,
            'closing_date' => now()->toDateString(),
            'closed_by' => $this->employee->id,
            'closed_at' => now(),
            'low_stock_count' => 2,
            'staff_present' => 1,
            'note' => 'เรียบร้อยดี',
        ]);

        $responseIndex = $this->actingAs($this->admin)
            ->get(route('admin.closings.index'));
        $responseIndex->assertOk()
            ->assertSee($this->branch->name)
            ->assertSee('เรียบร้อยดี');

        $responseShow = $this->actingAs($this->admin)
            ->get(route('admin.closings.show', $closing->id));
        $responseShow->assertOk()
            ->assertSee($this->branch->name)
            ->assertSee($this->employee->name);
    }

    /**
     * 9. คำสั่ง closing:check-overdue แจ้งเตือนเมื่อเลยเวลาปิดร้าน 30 นาทีแล้วยังไม่ปิด
     */
    public function test_check_overdue_closing_command_alerts_when_overdue(): void
    {
        // กำหนดเวลาปิดร้านให้ผ่านมาแล้วเกิน 30 นาที
        $this->branch->update([
            'close_time' => '17:00:00',
            'closed_weekdays' => [],
        ]);

        // รันด้วย --force เพื่อจำลองการตรวจ
        $this->artisan('closing:check-overdue', ['--force' => true])
            ->assertSuccessful();

        // ตรวจสอบว่าแอดมินได้รับการแจ้งเตือน overdue_closing
        $adminNotif = AppNotification::where('user_id', $this->admin->id)
            ->where('type', 'overdue_closing')
            ->first();
        $this->assertNotNull($adminNotif);
        $this->assertStringContainsString('ยังไม่แจ้งปิดร้าน', $adminNotif->title);

        // ตรวจสอบว่าพนักงานสาขาก็ได้รับการแจ้งเตือน
        $empNotif = AppNotification::where('user_id', $this->employee->id)
            ->where('type', 'overdue_closing')
            ->first();
        $this->assertNotNull($empNotif);
    }

    /**
     * 10. คำสั่ง closing:check-overdue ไม่แจ้งเตือนถ้าสาขาปิดร้านแล้ว
     */
    public function test_check_overdue_closing_command_skips_already_closed_branch(): void
    {
        // ปิดร้านแล้วสำหรับสาขา KKC
        DailyClosing::create([
            'branch_id' => $this->branch->id,
            'closing_date' => now()->toDateString(),
            'closed_by' => $this->employee->id,
            'closed_at' => now(),
        ]);

        AppNotification::truncate();

        $this->artisan('closing:check-overdue', ['--force' => true])
            ->assertSuccessful();

        $count = AppNotification::where('type', 'overdue_closing')
            ->where(function ($q) {
                $q->where('data->branch_id', $this->branch->id)
                    ->orWhere('data->branch_id', (string) $this->branch->id);
            })
            ->count();

        $this->assertEquals(0, $count);
    }
}
