<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StockReason;
use App\Events\AbnormalStockDrop;
use App\Events\LowStockDetected;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\StockLevelSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailyStockCountTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employeeKkc;
    protected User $employeeUbp;
    protected Branch $branchKkc;
    protected Branch $branchUbp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SettingSeeder::class,
            BranchSeeder::class,
            UserSeeder::class,
            ProductSeeder::class,
            StockLevelSeeder::class,
        ]);

        $this->admin = User::where('role', Role::ADMIN)->first();
        $this->employeeKkc = User::where('employee_code', 'KKC-001')->first();
        $this->employeeUbp = User::where('employee_code', 'UBP-001')->first();

        $this->branchKkc = Branch::where('code', 'KKC')->first();
        $this->branchUbp = Branch::where('code', 'UBP')->first();
    }

    /**
     * 1. ทดสอบพนักงานเปิดรอบนับสต็อกวันนี้ (Daily Draft)
     * ระบบต้องสร้าง stock_counts และดึง stock_levels มาสร้าง items ตั้งต้นในฐานข้อมูลจริง
     */
    public function test_employee_can_open_today_draft_and_populates_items_from_stock_levels(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        $response = $this->getJson('/api/v1/stock-counts/today');

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'stock_count' => [
                'branch_id' => $this->branchKkc->id,
                'count_date' => now()->toDateString(),
                'status' => 'draft',
                'revision' => 0,
            ],
        ]);

        // ตรวจสอบว่าบันทึกลงตาราง stock_counts จริงในฐานข้อมูล
        $this->assertDatabaseHas('stock_counts', [
            'branch_id' => $this->branchKkc->id,
            'count_date' => now()->toDateString(),
            'status' => 'draft',
            'started_by' => $this->employeeKkc->id,
        ]);

        $stockCount = StockCount::where('branch_id', $this->branchKkc->id)->first();
        $this->assertNotNull($stockCount);

        // ตรวจสอบว่าตาราง stock_count_items ถูกสร้างและบันทึกลงฐานข้อมูลจริง
        $this->assertGreaterThan(0, $stockCount->items()->count());

        // ตรวจสอบ item แรกว่า prev_qty ตรงกับ stock_level
        $firstItem = $stockCount->items()->first();
        $stockLevel = StockLevel::where('branch_id', $this->branchKkc->id)
            ->where('variant_id', $firstItem->variant_id)
            ->first();

        $this->assertEquals($stockLevel->qty, $firstItem->prev_qty);
        $this->assertNull($firstItem->counted_qty);
        $this->assertFalse($firstItem->is_confirmed);
    }

    /**
     * 2. ทดสอบระบบ Autosave บันทึกจำนวนนับทีละรายการ
     * เมื่อแก้จำนวน ระบบต้องบันทึกลงฐานข้อมูลจริงทันที พร้อมคำนวณ diff และเซ็ต is_confirmed = true
     */
    public function test_autosave_updates_count_item_immediately_in_database(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        // เปิดรอบนับ
        $draftRes = $this->getJson('/api/v1/stock-counts/today');
        $draftRes->assertOk();
        $stockCountId = $draftRes->json('stock_count.id');

        $stockCount = StockCount::find($stockCountId);
        $item = $stockCount->items()->first();
        $variantId = $item->variant_id;
        $prevQty = $item->prev_qty;

        $newCountedQty = $prevQty + 3; // นับได้เกินมา 3 ตัว

        // ยิงคำขอบันทึก Autosave
        $response = $this->putJson("/api/v1/stock-counts/{$stockCountId}/items/{$variantId}", [
            'counted_qty' => $newCountedQty,
            'reason' => 'พบสินค้าเกิน 3 ชิ้นที่ห้องเก็บสต็อก',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'บันทึกจำนวนนับสำเร็จ (Autosaved)',
            'item' => [
                'variant_id' => $variantId,
                'counted_qty' => $newCountedQty,
                'diff' => 3,
                'is_confirmed' => true,
                'reason' => 'พบสินค้าเกิน 3 ชิ้นที่ห้องเก็บสต็อก',
            ],
        ]);

        // ตรวจสอบว่าข้อมูลลง Database จริงทันที (Autosave จริง ไม่ใช่แค่เก็บใน memory)
        $this->assertDatabaseHas('stock_count_items', [
            'stock_count_id' => $stockCountId,
            'variant_id' => $variantId,
            'counted_qty' => $newCountedQty,
            'diff' => 3,
            'is_confirmed' => true,
            'counted_by' => $this->employeeKkc->id,
            'reason' => 'พบสินค้าเกิน 3 ชิ้นที่ห้องเก็บสต็อก',
        ]);
    }

    /**
     * 3. ทดสอบการกดยืนยันแถว (Confirm Row) ว่าจำนวนตรงกับของเดิม
     * ระบบต้องตั้ง counted_qty = prev_qty และ is_confirmed = true บันทึกลงฐานข้อมูลจริง
     */
    public function test_confirm_row_updates_all_variants_in_row_to_prev_qty(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        $draftRes = $this->getJson('/api/v1/stock-counts/today');
        $stockCountId = $draftRes->json('stock_count.id');

        $stockCount = StockCount::find($stockCountId);
        $targetItems = $stockCount->items()->take(3)->get();
        $variantIds = $targetItems->pluck('variant_id')->toArray();

        // กดยืนยันแถว
        $response = $this->postJson("/api/v1/stock-counts/{$stockCountId}/confirm-row", [
            'variant_ids' => $variantIds,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'confirmed_count' => 3,
        ]);

        // ตรวจสอบในฐานข้อมูลว่าทุก variant ที่กดยืนยันได้รับการอัปเดตจริง
        foreach ($targetItems as $item) {
            $this->assertDatabaseHas('stock_count_items', [
                'stock_count_id' => $stockCountId,
                'variant_id' => $item->variant_id,
                'counted_qty' => $item->prev_qty,
                'diff' => 0,
                'is_confirmed' => true,
                'counted_by' => $this->employeeKkc->id,
            ]);
        }
    }

    /**
     * 4. ทดสอบกดยืนยันทั้งแถวด้วย product_id
     */
    public function test_confirm_by_product_id_confirms_all_variants_of_that_product(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        $draftRes = $this->getJson('/api/v1/stock-counts/today');
        $stockCountId = $draftRes->json('stock_count.id');

        $product = Product::first();
        $productVariantIds = ProductVariant::where('product_id', $product->id)->pluck('id')->toArray();

        $response = $this->postJson("/api/v1/stock-counts/{$stockCountId}/confirm-row", [
            'product_id' => $product->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'confirmed_count' => count($productVariantIds),
        ]);

        // ตรวจสอบในฐานข้อมูล
        foreach ($productVariantIds as $vId) {
            $this->assertDatabaseHas('stock_count_items', [
                'stock_count_id' => $stockCountId,
                'variant_id' => $vId,
                'is_confirmed' => true,
            ]);
        }
    }

    /**
     * 5. ทดสอบว่าถ้ายังยืนยันรายการที่มี prev_qty > 0 ไม่ครบ ระบบต้องปฏิเสธการส่ง (Validation Error)
     */
    public function test_submit_count_is_rejected_if_any_item_with_positive_prev_qty_is_unconfirmed(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        $draftRes = $this->getJson('/api/v1/stock-counts/today');
        $stockCountId = $draftRes->json('stock_count.id');

        // พยายามกดส่งทั้งที่ยังไม่ได้ยืนยันครบ
        $response = $this->postJson("/api/v1/stock-counts/{$stockCountId}/submit", [
            'lat' => $this->branchKkc->lat,
            'lng' => $this->branchKkc->lng,
            'accuracy' => 15.0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['stock_count']);

        // ตรวจสอบว่าใน Database สถานะยังคงเป็น draft
        $this->assertDatabaseHas('stock_counts', [
            'id' => $stockCountId,
            'status' => 'draft',
            'submitted_at' => null,
        ]);
    }

    /**
     * 6. ทดสอบการส่งนับสต็อกสำเร็จ (Submit Count)
     * - อัปเดต stock_counts เป็น submitted
     * - อัปเดต stock_levels.qty ตามจำนวนที่นับจริง
     * - บันทึก stock_movements (reason: count) เป็น Ledger
     */
    public function test_submit_count_succeeds_and_updates_stock_levels_and_creates_movements(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        $draftRes = $this->getJson('/api/v1/stock-counts/today');
        $stockCountId = $draftRes->json('stock_count.id');

        $stockCount = StockCount::find($stockCountId);

        // จำลองการยืนยันครบทุกรายการ
        foreach ($stockCount->items as $item) {
            $countedQty = $item->prev_qty > 0 ? $item->prev_qty + 1 : 0;
            $item->update([
                'counted_qty' => $countedQty,
                'diff' => $countedQty - $item->prev_qty,
                'is_confirmed' => true,
                'counted_by' => $this->employeeKkc->id,
            ]);
        }

        // ยิงส่งนับสต็อก (ส่งพิกัดหน้าร้านผ่านเกณฑ์ Geofence)
        $response = $this->postJson("/api/v1/stock-counts/{$stockCountId}/submit", [
            'lat' => $this->branchKkc->lat,
            'lng' => $this->branchKkc->lng,
            'accuracy' => 15.0,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'stock_count' => [
                'id' => $stockCountId,
                'status' => 'submitted',
            ],
        ]);

        // 1. ตรวจสอบ stock_counts ใน Database
        $this->assertDatabaseHas('stock_counts', [
            'id' => $stockCountId,
            'status' => 'submitted',
            'submitted_by' => $this->employeeKkc->id,
        ]);

        // 2. ตรวจสอบว่า stock_levels.qty ได้รับการอัปเดตจริง
        $sampleItem = $stockCount->items()->where('prev_qty', '>', 0)->first();
        $this->assertDatabaseHas('stock_levels', [
            'branch_id' => $this->branchKkc->id,
            'variant_id' => $sampleItem->variant_id,
            'qty' => $sampleItem->counted_qty,
        ]);

        // 3. ตรวจสอบว่ามีบันทึก stock_movements (Ledger) สำหรับการนับสต็อก
        $this->assertDatabaseHas('stock_movements', [
            'branch_id' => $this->branchKkc->id,
            'variant_id' => $sampleItem->variant_id,
            'reason' => StockReason::COUNT->value,
            'ref_type' => StockCount::class,
            'ref_id' => $stockCountId,
            'qty_after' => $sampleItem->counted_qty,
        ]);
    }

    /**
     * 7. ทดสอบว่าเมื่อส่งนับสต็อกแล้วมีสินค้าลดลงจนต่ำกว่า min_qty หรือลดผิดปกติ จะยิง Event แจ้งเตือน
     */
    public function test_submit_count_triggers_low_stock_and_abnormal_drop_events(): void
    {
        Event::fake([LowStockDetected::class, AbnormalStockDrop::class]);

        Sanctum::actingAs($this->employeeKkc);

        $draftRes = $this->getJson('/api/v1/stock-counts/today');
        $stockCountId = $draftRes->json('stock_count.id');
        $stockCount = StockCount::find($stockCountId);

        // ให้มี 1 variant ที่ลดลงผิดปกติ เช่น จาก 20 เหลือ 4 (ลดลง 16 ชิ้น ซึ่ง >= 10 ชิ้น และ <= min_qty 7)
        $abnormalItem = $stockCount->items()->where('prev_qty', '>=', 20)->first();
        $this->assertNotNull($abnormalItem, 'ต้องมีสินค้าที่มีสต็อก >= 20 เพื่อทดสอบการลดผิดปกติ');

        foreach ($stockCount->items as $item) {
            if ($item->id === $abnormalItem->id) {
                $item->update([
                    'counted_qty' => 4,
                    'diff' => 4 - $item->prev_qty,
                    'is_confirmed' => true,
                    'counted_by' => $this->employeeKkc->id,
                    'reason' => 'ตรวจพบสินค้าสูญหาย',
                ]);
            } else {
                $item->update([
                    'counted_qty' => $item->prev_qty,
                    'diff' => 0,
                    'is_confirmed' => true,
                    'counted_by' => $this->employeeKkc->id,
                ]);
            }
        }

        $response = $this->postJson("/api/v1/stock-counts/{$stockCountId}/submit", [
            'lat' => $this->branchKkc->lat,
            'lng' => $this->branchKkc->lng,
            'accuracy' => 15.0,
        ]);

        $response->assertOk();

        // ตรวจสอบว่า Event ถูกยิงจริง
        Event::assertDispatched(AbnormalStockDrop::class);
        Event::assertDispatched(LowStockDetected::class);
    }

    /**
     * 8. ทดสอบว่ารอบนับที่ถูกส่งไปแล้ว ไม่สามารถแก้ไขได้อีก เว้นแต่จะกด reopen
     */
    public function test_cannot_edit_already_submitted_count_without_reopening(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        $draftRes = $this->getJson('/api/v1/stock-counts/today');
        $stockCountId = $draftRes->json('stock_count.id');
        $stockCount = StockCount::find($stockCountId);

        foreach ($stockCount->items as $item) {
            $item->update([
                'counted_qty' => $item->prev_qty,
                'diff' => 0,
                'is_confirmed' => true,
            ]);
        }

        // ส่งนับสต็อก
        $this->postJson("/api/v1/stock-counts/{$stockCountId}/submit", [
            'lat' => $this->branchKkc->lat,
            'lng' => $this->branchKkc->lng,
            'accuracy' => 15.0,
        ])->assertOk();

        // พยายาม Autosave ในรอบที่ส่งไปแล้ว
        $sampleItem = $stockCount->items()->first();
        $response = $this->putJson("/api/v1/stock-counts/{$stockCountId}/items/{$sampleItem->variant_id}", [
            'counted_qty' => 99,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['stock_count']);
    }

    /**
     * 9. ทดสอบการเปิดรอบแก้ไขใหม่ (Revision +1) ในวันเดียวกัน
     */
    public function test_can_reopen_submitted_count_for_revision(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        $draftRes = $this->getJson('/api/v1/stock-counts/today');
        $stockCountId = $draftRes->json('stock_count.id');
        $stockCount = StockCount::find($stockCountId);

        foreach ($stockCount->items as $item) {
            $item->update([
                'counted_qty' => $item->prev_qty,
                'diff' => 0,
                'is_confirmed' => true,
            ]);
        }

        // ส่งรอบที่ 0
        $this->postJson("/api/v1/stock-counts/{$stockCountId}/submit", [
            'lat' => $this->branchKkc->lat,
            'lng' => $this->branchKkc->lng,
            'accuracy' => 15.0,
        ])->assertOk();

        // กดยื่นขอเปิดรอบแก้ไขใหม่ (Reopen / Revision)
        $reopenRes = $this->postJson("/api/v1/stock-counts/{$stockCountId}/reopen");

        $reopenRes->assertOk();
        $reopenRes->assertJson([
            'success' => true,
            'stock_count' => [
                'status' => 'draft',
                'revision' => 1,
            ],
        ]);

        // ตรวจสอบใน Database
        $this->assertDatabaseHas('stock_counts', [
            'id' => $stockCountId,
            'status' => 'draft',
            'revision' => 1,
        ]);
    }

    /**
     * 10. ทดสอบว่าพนักงานไม่สามารถเข้าถึงหรือแก้ไขรอบนับสต็อกของสาขาอื่นได้ (BranchScope)
     */
    public function test_employee_cannot_access_or_edit_stock_count_of_another_branch(): void
    {
        // แอดมินสร้างรอบนับของอุบลฯ
        Sanctum::actingAs($this->admin);
        $ubpDraftRes = $this->getJson('/api/v1/stock-counts/today?branch_id=' . $this->branchUbp->id);
        $ubpDraftRes->assertOk();
        $ubpStockCountId = $ubpDraftRes->json('stock_count.id');

        // สลับเป็นพนักงานขอนแก่น พยายามเข้าถึงรอบนับของอุบลฯ
        Sanctum::actingAs($this->employeeKkc);

        $response = $this->putJson("/api/v1/stock-counts/{$ubpStockCountId}/items/1", [
            'counted_qty' => 5,
        ]);

        // ต้องถูกบล็อกด้วย 404 (BranchScoped หาไม่เจอ)
        $response->assertNotFound();
    }

    /**
     * 11. ทดสอบ Geofence ในการส่งนับสต็อก
     * พนักงานที่อยู่นอกรัศมี 50 เมตร ต้องถูกปฏิเสธ
     */
    public function test_submit_enforces_geofence_for_employees(): void
    {
        Sanctum::actingAs($this->employeeKkc);

        $draftRes = $this->getJson('/api/v1/stock-counts/today');
        $stockCountId = $draftRes->json('stock_count.id');
        $stockCount = StockCount::find($stockCountId);

        foreach ($stockCount->items as $item) {
            $item->update([
                'counted_qty' => $item->prev_qty,
                'diff' => 0,
                'is_confirmed' => true,
            ]);
        }

        // ส่งพิกัดที่อยู่นอกรัศมี 50 เมตร (เช่น ห่างออกไป 10 กิโลเมตร)
        $response = $this->postJson("/api/v1/stock-counts/{$stockCountId}/submit", [
            'lat' => $this->branchKkc->lat + 0.1,
            'lng' => $this->branchKkc->lng + 0.1,
            'accuracy' => 10.0,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('อยู่นอกพื้นที่ร้าน', $response->json('message'));
    }
}

