<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\StockReason;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockCountService
{
    public function __construct(
        protected StockService $stockService
    ) {}

    /**
     * ดึงรอบนับสต็อกวันนี้ หรือสร้าง draft อัตโนมัติจาก stock_levels
     * (§5.3: 1 สาขา 1 รอบนับ/วัน, เปิด draft เดียวกันได้, รายการมาจาก stock_levels)
     */
    public function getOrCreateTodayDraft(int $branchId, User $user): StockCount
    {
        $today = now()->toDateString();

        $stockCount = StockCount::withoutGlobalScopes()
            ->firstOrCreate(
                ['branch_id' => $branchId, 'count_date' => $today],
                [
                    'status' => 'draft',
                    'revision' => 0,
                    'started_by' => $user->id,
                ]
            );

        // ดึง variants ที่เปิดใช้งานทั้งหมด เพื่อรับประกันว่าทุกแบบ/ไซส์มีรายการให้นับ
        $variants = ProductVariant::where('is_active', true)->get();

        // ดึง stock levels ปัจจุบันของสาขานี้
        $currentLevels = StockLevel::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->pluck('qty', 'variant_id');

        foreach ($variants as $variant) {
            $prevQty = $currentLevels[$variant->id] ?? 0;

            // ตรวจสอบหรือสร้าง StockLevel เริ่มต้นถ้ายังไม่มี
            if (!isset($currentLevels[$variant->id])) {
                StockLevel::withoutGlobalScopes()->create([
                    'branch_id' => $branchId,
                    'variant_id' => $variant->id,
                    'qty' => 0,
                    'min_qty' => 7,
                ]);
            }

            // สร้างรายการนับ StockCountItem หากยังไม่มี
            StockCountItem::firstOrCreate(
                [
                    'stock_count_id' => $stockCount->id,
                    'variant_id' => $variant->id,
                ],
                [
                    'prev_qty' => $prevQty,
                    'counted_qty' => null,
                    'diff' => 0,
                    'is_confirmed' => false,
                ]
            );
        }

        return $stockCount->load([
            'branch',
            'startedBy',
            'submittedBy',
            'items.variant.product'
        ]);
    }

    /**
     * บันทึกผลการนับทีละรายการลงฐานข้อมูลทันที (Autosave)
     * (§5.3: บันทึกทีละรายการ/แถว, เก็บ counted_by)
     */
    public function updateCountItem(
        int $stockCountId,
        int $variantId,
        int $countedQty,
        User $user,
        ?string $reason = null
    ): StockCountItem {
        $stockCount = StockCount::findOrFail($stockCountId);

        if ($stockCount->status === 'submitted') {
            throw ValidationException::withMessages([
                'stock_count' => ['รอบนับสต็อกนี้ถูกส่งไปแล้ว ไม่สามารถแก้ไขได้ (หากต้องการแก้ไขกรุณากดเปิดรอบแก้ไขใหม่)'],
            ]);
        }

        $item = StockCountItem::where('stock_count_id', $stockCountId)
            ->where('variant_id', $variantId)
            ->firstOrFail();

        $diff = $countedQty - $item->prev_qty;

        $item->update([
            'counted_qty' => $countedQty,
            'diff' => $diff,
            'is_confirmed' => true,
            'counted_by' => $user->id,
            'reason' => $reason,
        ]);

        return $item->load('variant.product');
    }

    /**
     * กดยืนยันแถวสินค้าว่าตรงกับจำนวนเดิม (Confirm Rows)
     * (§5.3: พนักงานเดินนับแล้วตรงกับยอดเดิม กด "ยืนยันแถว ✓" ระบบจะตั้ง counted_qty = prev_qty และ is_confirmed = true)
     */
    public function confirmRows(int $stockCountId, array $variantIds, User $user): int
    {
        $stockCount = StockCount::findOrFail($stockCountId);

        if ($stockCount->status === 'submitted') {
            throw ValidationException::withMessages([
                'stock_count' => ['รอบนับสต็อกนี้ถูกส่งไปแล้ว ไม่สามารถแก้ไขได้'],
            ]);
        }

        $items = StockCountItem::where('stock_count_id', $stockCountId)
            ->whereIn('variant_id', $variantIds)
            ->get();

        $confirmedCount = 0;
        foreach ($items as $item) {
            $countedQty = $item->counted_qty !== null ? $item->counted_qty : $item->prev_qty;
            $diff = $countedQty - $item->prev_qty;

            $item->update([
                'counted_qty' => $countedQty,
                'diff' => $diff,
                'is_confirmed' => true,
                'counted_by' => $user->id,
            ]);
            $confirmedCount++;
        }

        return $confirmedCount;
    }

    /**
     * ยืนยันทั้งแถวของแบบสินค้า (Confirm by Product ID)
     */
    public function confirmProduct(int $stockCountId, int $productId, User $user): int
    {
        $variantIds = ProductVariant::where('product_id', $productId)->pluck('id')->toArray();
        return $this->confirmRows($stockCountId, $variantIds, $user);
    }

    /**
     * ส่งการนับสต็อกประจำวัน (Submit Stock Count)
     * (§5.3: ตรวจสอบว่ายืนยันครบทุกแถวที่มี prev_qty > 0, อัปเดต stock_levels, บันทึก stock_movements)
     */
    public function submitCount(int $stockCountId, User $user): StockCount
    {
        $stockCount = StockCount::with('items')->findOrFail($stockCountId);

        if ($stockCount->status === 'submitted') {
            throw ValidationException::withMessages([
                'stock_count' => ['รอบนับสต็อกนี้ได้ทำการส่งไปเรียบร้อยแล้ว'],
            ]);
        }

        // ตรวจสอบว่ายืนยันครบทุกรายการที่มี prev_qty > 0 หรือยัง (§5.3)
        // รายการที่มี prev_qty = 0 ไม่จำเป็นต้องกดยืนยัน แต่ต้องแก้ไขได้
        $unconfirmed = $stockCount->items()
            ->where('prev_qty', '>', 0)
            ->where('is_confirmed', false)
            ->count();

        if ($unconfirmed > 0) {
            throw ValidationException::withMessages([
                'stock_count' => ["ยังยืนยันรายการสต็อกไม่ครบถ้วน (เหลืออีก {$unconfirmed} รายการที่ต้องยืนยัน)"],
            ]);
        }

        return DB::transaction(function () use ($stockCount, $user) {
            // บันทึกการอัปเดตสต็อกผ่าน StockService::change เท่านั้น (ตามกฎเหล็ก §5.3)
            foreach ($stockCount->items as $item) {
                if ($item->is_confirmed && $item->counted_qty !== null) {
                    $this->stockService->change(
                        $stockCount->branch_id,
                        $item->variant_id,
                        $item->counted_qty,
                        StockReason::COUNT,
                        $stockCount,
                        $item->reason,
                        $user->id
                    );
                }
            }

            $stockCount->update([
                'status' => 'submitted',
                'submitted_by' => $user->id,
                'submitted_at' => now(),
            ]);

            return $stockCount->fresh([
                'branch',
                'startedBy',
                'submittedBy',
                'items.variant.product'
            ]);
        });
    }

    /**
     * เปิดรอบแก้ไขใหม่ในวันเดียวกัน (Revision +1)
     * (§5.3: ส่งแล้วแก้ซ้ำในวันเดียวกันได้ โดยสร้างรอบ revision +1 และบันทึก movement ต่อเนื่อง)
     */
    public function reopenDraft(int $stockCountId, User $user): StockCount
    {
        $stockCount = StockCount::findOrFail($stockCountId);

        if ($stockCount->status !== 'submitted') {
            throw ValidationException::withMessages([
                'stock_count' => ['รอบนับนี้ยังไม่ได้ส่ง ไม่จำเป็นต้องเปิดรอบแก้ไขใหม่'],
            ]);
        }

        $stockLevels = StockLevel::withoutGlobalScopes()
            ->where('branch_id', $stockCount->branch_id)
            ->pluck('qty', 'variant_id');

        DB::transaction(function () use ($stockCount, $stockLevels, $user) {
            $stockCount->update([
                'status' => 'draft',
                'revision' => $stockCount->revision + 1,
            ]);

            // อัปเดต prev_qty ให้ตรงกับสต็อกล่าสุดหลังส่งรอบแรก และรีเซ็ตสถานะการยืนยัน
            foreach ($stockCount->items as $item) {
                $currentQty = $stockLevels[$item->variant_id] ?? $item->counted_qty ?? $item->prev_qty;
                $item->update([
                    'prev_qty' => $currentQty,
                    'is_confirmed' => false,
                ]);
            }
        });

        return $stockCount->fresh([
            'branch',
            'startedBy',
            'submittedBy',
            'items.variant.product'
        ]);
    }
}
