<?php

namespace Tests\Feature;

use App\Enums\ChecklistStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistSubmissionItem;
use App\Models\ChecklistTemplate;
use App\Models\Media;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\ChecklistTemplateSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailyChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employeeKkc;
    protected Branch $branchKkc;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed([
            SettingSeeder::class,
            BranchSeeder::class,
            UserSeeder::class,
            ChecklistTemplateSeeder::class,
        ]);

        $this->admin = User::where('role', Role::ADMIN)->first();
        $this->employeeKkc = User::where('employee_code', 'KKC-001')->first();
        $this->branchKkc = Branch::where('code', 'KKC')->first();
    }

    /**
     * 1. ทดสอบพนักงานดึงรายการเช็คลิสต์วันนี้ (สร้าง Draft จาก 3 แม่แบบอัตโนมัติ)
     */
    public function test_employee_can_get_today_checklists_and_creates_drafts_from_templates(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        $response = $this->getJson('/api/v1/checklists/today');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        // แม่แบบเช็คลิสต์มี 3 ชุด (เปิดร้าน, ปิดร้าน, ความสะอาด)
        $this->assertCount(3, $response->json('checklists'));

        // ตรวจสอบในฐานข้อมูลว่ามีการสร้าง submissions 3 รายการ
        $this->assertEquals(3, ChecklistSubmission::where('branch_id', $this->branchKkc->id)->count());

        $firstSubmission = ChecklistSubmission::where('branch_id', $this->branchKkc->id)->first();
        $this->assertEquals(ChecklistStatus::DRAFT, $firstSubmission->status);
        $this->assertGreaterThan(0, $firstSubmission->items()->count());
    }

    /**
     * 2. ทดสอบการบันทึกคำตอบเช็คลิสต์รายข้อ
     */
    public function test_employee_can_update_checklist_item_answer(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        // ดึงเช็คลิสต์วันนี้
        $this->getJson('/api/v1/checklists/today');

        $submission = ChecklistSubmission::where('branch_id', $this->branchKkc->id)->first();
        $item = $submission->items()->first();

        $response = $this->putJson("/api/v1/checklists/{$submission->id}/items/{$item->id}", [
            'is_ok' => true,
            'note' => 'เปิดแอร์ 25 องศา และไฟป้ายหน้าร้านติดครบทุกดวง',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'บันทึกคำตอบเช็คลิสต์สำเร็จ',
            'item' => [
                'id' => $item->id,
                'is_ok' => true,
                'note' => 'เปิดแอร์ 25 องศา และไฟป้ายหน้าร้านติดครบทุกดวง',
            ],
        ]);

        // ตรวจสอบใน Database
        $this->assertDatabaseHas('checklist_submission_items', [
            'id' => $item->id,
            'is_ok' => true,
            'note' => 'เปิดแอร์ 25 องศา และไฟป้ายหน้าร้านติดครบทุกดวง',
        ]);
    }

    /**
     * 3. ทดสอบอัปโหลดรูปภาพและบีบอัดเป็น WebP q=70 (พร้อมสร้าง Thumbnail)
     */
    public function test_employee_can_upload_photo_and_compresses_to_webp(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        $this->getJson('/api/v1/checklists/today');

        $submission = ChecklistSubmission::where('branch_id', $this->branchKkc->id)->first();
        $item = $submission->items()->first();

        // จำลองการอัปโหลดรูปภาพขนาดใหญ่ 1920x1080 JPEG
        $uploadedFile = UploadedFile::fake()->image('front_store.jpg', 1920, 1080);

        $response = $this->postJson("/api/v1/checklists/{$submission->id}/items/{$item->id}/photos", [
            'photo' => $uploadedFile,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'อัปโหลดและบีบอัดรูปภาพเป็น WebP สำเร็จ',
            'media' => [
                'mime' => 'image/webp',
            ],
        ]);

        $mediaId = $response->json('media.id');
        $media = Media::find($mediaId);
        $this->assertNotNull($media);

        // ตรวจสอบคุณสมบัติการบีบอัดภาพ (§5.6)
        $this->assertEquals('image/webp', $media->mime);
        $this->assertLessThanOrEqual(1280, $media->width);
        $this->assertLessThanOrEqual(1280, $media->height);
        $this->assertStringEndsWith('.webp', $media->path);
        $this->assertStringEndsWith('.webp', $media->thumb_path);

        // ตรวจสอบว่าไฟล์ถูกจัดเก็บบน Storage disk จริง
        Storage::disk('local')->assertExists($media->path);
        Storage::disk('local')->assertExists($media->thumb_path);

        // ตรวจสอบวันหมดอายุ (expires_at = 60 วันข้างหน้า)
        $this->assertNotNull($media->expires_at);
        $this->assertEquals(now()->addDays(60)->toDateString(), $media->expires_at->toDateString());
    }

    /**
     * 4. ทดสอบปฏิเสธไฟล์ที่มีขนาดเกิน 5MB หรือไม่ใช่ไฟล์รูปภาพ
     */
    public function test_upload_rejects_files_larger_than_5mb_or_invalid_mime(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        $this->getJson('/api/v1/checklists/today');
        $submission = ChecklistSubmission::where('branch_id', $this->branchKkc->id)->first();
        $item = $submission->items()->first();

        // ไฟล์ขนาด 6MB (> 5MB)
        $largeFile = UploadedFile::fake()->create('large.jpg', 6000, 'image/jpeg');

        $response = $this->postJson("/api/v1/checklists/{$submission->id}/items/{$item->id}/photos", [
            'photo' => $largeFile,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['photo']);

        // ไฟล์ไม่ใช่รูปภาพ (เช่น text)
        $textFile = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        $textResponse = $this->postJson("/api/v1/checklists/{$submission->id}/items/{$item->id}/photos", [
            'photo' => $textFile,
        ]);

        $textResponse->assertStatus(422);
        $textResponse->assertJsonValidationErrors(['photo']);
    }

    /**
     * 5. ทดสอบการลบรูปภาพที่แนบไว้ในข้อตรวจเช็ค
     */
    public function test_employee_can_delete_attached_photo(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        $this->getJson('/api/v1/checklists/today');
        $submission = ChecklistSubmission::where('branch_id', $this->branchKkc->id)->first();
        $item = $submission->items()->first();

        // อัปโหลดรูปก่อน
        $uploadedFile = UploadedFile::fake()->image('test.jpg', 800, 600);
        $uploadRes = $this->postJson("/api/v1/checklists/{$submission->id}/items/{$item->id}/photos", [
            'photo' => $uploadedFile,
        ]);

        $mediaId = $uploadRes->json('media.id');
        $media = Media::find($mediaId);
        $path = $media->path;
        $thumbPath = $media->thumb_path;

        // ลบรูป
        $delResponse = $this->deleteJson("/api/v1/checklists/{$submission->id}/items/{$item->id}/photos/{$mediaId}");

        $delResponse->assertOk();
        $delResponse->assertJson([
            'success' => true,
            'message' => 'ลบรูปภาพเรียบร้อยแล้ว',
        ]);

        // ตรวจสอบว่าใน Database และ Storage ถูกลบออกจริง
        $this->assertDatabaseMissing('media', ['id' => $mediaId]);
        Storage::disk('local')->assertMissing($path);
        Storage::disk('local')->assertMissing($thumbPath);
    }

    /**
     * 6. ทดสอบว่าถ้าข้อที่ require_photo = true ยังไม่มีรูปแนบ ระบบต้องไม่อนุญาตให้ส่ง (422)
     */
    public function test_submit_checklist_fails_if_required_photo_is_missing(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        $this->getJson('/api/v1/checklists/today');

        // หา submission ที่มีข้อ require_photo = true (เช่น เช็คลิสต์เปิดร้าน ข้อที่ 2)
        $submission = ChecklistSubmission::where('branch_id', $this->branchKkc->id)
            ->whereHas('items.templateItem', function ($q) {
                $q->where('require_photo', true);
            })
            ->first();

        // พยายามกดส่งทั้งที่ยังไม่มีรูป
        $response = $this->postJson("/api/v1/checklists/{$submission->id}/submit", [
            'lat' => $this->branchKkc->lat,
            'lng' => $this->branchKkc->lng,
            'accuracy' => 15.0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['photo']);

        $this->assertEquals(ChecklistStatus::DRAFT, $submission->fresh()->status);
    }

    /**
     * 7. ทดสอบการส่งเช็คลิสต์สำเร็จ เมื่อแนบรูปครบตามที่กำหนด
     */
    public function test_submit_checklist_succeeds_when_all_required_photos_are_attached(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        $this->getJson('/api/v1/checklists/today');

        $submission = ChecklistSubmission::where('branch_id', $this->branchKkc->id)
            ->whereHas('items.templateItem', function ($q) {
                $q->where('require_photo', true);
            })
            ->first();

        // แนบรูปให้ทุกข้อที่ต้องการรูป
        foreach ($submission->items as $item) {
            if ($item->templateItem->require_photo) {
                $photo = UploadedFile::fake()->image('evidence.jpg', 1000, 800);
                $this->postJson("/api/v1/checklists/{$submission->id}/items/{$item->id}/photos", [
                    'photo' => $photo,
                ])->assertOk();
            }
        }

        // กดส่งเช็คลิสต์
        $response = $this->postJson("/api/v1/checklists/{$submission->id}/submit", [
            'lat' => $this->branchKkc->lat,
            'lng' => $this->branchKkc->lng,
            'accuracy' => 15.0,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'submission' => [
                'id' => $submission->id,
                'status' => 'submitted',
                'submitted_by' => $this->employeeKkc->id,
            ],
        ]);

        // ตรวจสอบสถานะใน Database
        $this->assertDatabaseHas('checklist_submissions', [
            'id' => $submission->id,
            'status' => ChecklistStatus::SUBMITTED->value,
            'submitted_by' => $this->employeeKkc->id,
        ]);
        $this->assertNotNull($submission->fresh()->submitted_at);
    }

    /**
     * 8. ทดสอบว่าเช็คลิสต์ที่ส่งไปแล้ว ไม่สามารถแก้ไขคำตอบหรือแนบรูปเพิ่มได้
     */
    public function test_cannot_edit_checklist_after_submission(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        $this->getJson('/api/v1/checklists/today');
        $submission = ChecklistSubmission::where('branch_id', $this->branchKkc->id)->first();

        // แนบรูปถ้าจำเป็น
        foreach ($submission->items as $item) {
            if ($item->templateItem->require_photo) {
                $photo = UploadedFile::fake()->image('evidence.jpg', 800, 600);
                $this->postJson("/api/v1/checklists/{$submission->id}/items/{$item->id}/photos", ['photo' => $photo]);
            }
        }

        // ส่งเช็คลิสต์
        $this->postJson("/api/v1/checklists/{$submission->id}/submit", [
            'lat' => $this->branchKkc->lat,
            'lng' => $this->branchKkc->lng,
            'accuracy' => 15.0,
        ])->assertOk();

        // พยายามแก้ไขคำตอบหลังส่ง
        $sampleItem = $submission->items()->first();
        $updateRes = $this->putJson("/api/v1/checklists/{$submission->id}/items/{$sampleItem->id}", [
            'is_ok' => false,
        ]);

        $updateRes->assertStatus(422);
        $updateRes->assertJsonValidationErrors(['checklist']);
    }

    /**
     * 9. ทดสอบแอดมินอนุมัติ (Approve) หรือตีกลับ (Reject) เช็คลิสต์ (§5.7, §7.2)
     */
    public function test_admin_can_approve_or_reject_checklist_submission(): void
    {
        // พนักงานส่งเช็คลิสต์
        Sanctum::actingAs($this->employeeKkc);
        $this->getJson('/api/v1/checklists/today');
        $submission = ChecklistSubmission::where('branch_id', $this->branchKkc->id)->first();

        foreach ($submission->items as $item) {
            if ($item->templateItem->require_photo) {
                $photo = UploadedFile::fake()->image('photo.jpg', 800, 600);
                $this->postJson("/api/v1/checklists/{$submission->id}/items/{$item->id}/photos", ['photo' => $photo]);
            }
        }

        $this->postJson("/api/v1/checklists/{$submission->id}/submit", [
            'lat' => $this->branchKkc->lat,
            'lng' => $this->branchKkc->lng,
            'accuracy' => 15.0,
        ])->assertOk();

        // สลับเป็น Admin มาตรวจรีวิว
        $checklistService = app(\App\Services\ChecklistService::class);

        // ทดสอบอนุมัติ
        $approved = $checklistService->approve($submission->id, $this->admin, 100, 'ตรวจแล้ว ดีเยี่ยม');
        $this->assertEquals(ChecklistStatus::APPROVED, $approved->status);
        $this->assertEquals(100, $approved->score);
        $this->assertEquals($this->admin->id, $approved->reviewed_by);

        // ทดสอบตีกลับ
        $rejected = $checklistService->reject($submission->id, $this->admin, 'ห้องลองชุดยังไม่เรียบร้อย กรุณาจัดใหม่');
        $this->assertEquals(ChecklistStatus::REJECTED, $rejected->status);
        $this->assertEquals('ห้องลองชุดยังไม่เรียบร้อย กรุณาจัดใหม่', $rejected->review_note);
    }

    /**
     * 10. ทดสอบคำสั่ง Artisan media:purge-old ล้างรูปภาพที่หมดอายุ (เกิน 60 วัน) แต่คงรูปที่ปักหมุดไว้ (§5.6)
     */
    public function test_purge_old_media_deletes_expired_media_but_keeps_pinned_media(): void
    {
        // 1. สร้างรูปที่หมดอายุและไม่ได้ปักหมุด (ต้องถูกลบ)
        Storage::disk('local')->put('checklists/old/expired.webp', 'dummy');
        Storage::disk('local')->put('checklists/old/thumbs/expired.webp', 'dummy_thumb');

        $expiredMedia = Media::create([
            'mediable_type' => ChecklistSubmissionItem::class,
            'mediable_id' => 1,
            'disk' => 'local',
            'path' => 'checklists/old/expired.webp',
            'thumb_path' => 'checklists/old/thumbs/expired.webp',
            'mime' => 'image/webp',
            'size_bytes' => 100,
            'created_by' => $this->employeeKkc->id,
            'expires_at' => now()->subDay(), // หมดอายุเมื่อวาน
            'is_pinned' => false,
            'created_at' => now()->subDays(61),
        ]);

        // 2. สร้างรูปที่หมดอายุแต่ถูกปักหมุดไว้ (ต้องไม่ถูกลบ)
        Storage::disk('local')->put('checklists/old/pinned.webp', 'dummy_pinned');
        $pinnedMedia = Media::create([
            'mediable_type' => ChecklistSubmissionItem::class,
            'mediable_id' => 1,
            'disk' => 'local',
            'path' => 'checklists/old/pinned.webp',
            'thumb_path' => null,
            'mime' => 'image/webp',
            'size_bytes' => 150,
            'created_by' => $this->employeeKkc->id,
            'expires_at' => now()->subDay(),
            'is_pinned' => true, // ปักหมุด
            'created_at' => now()->subDays(61),
        ]);

        // รันคำสั่งล้างรูปหมดอายุ
        $this->artisan('media:purge-old')
            ->expectsOutputToContain('ล้างรูปภาพที่หมดอายุสำเร็จแล้วทั้งหมด 1 รายการ')
            ->assertSuccessful();

        // ตรวจสอบว่ารูปหมดอายุถูกลบออกจาก DB และ Storage
        $this->assertDatabaseMissing('media', ['id' => $expiredMedia->id]);
        Storage::disk('local')->assertMissing('checklists/old/expired.webp');
        Storage::disk('local')->assertMissing('checklists/old/thumbs/expired.webp');

        // ตรวจสอบว่ารูปที่ปักหมุดยังคงอยู่ใน DB และ Storage
        $this->assertDatabaseHas('media', ['id' => $pinnedMedia->id]);
        Storage::disk('local')->assertExists('checklists/old/pinned.webp');
    }
}
