<?php

namespace App\Services;

use App\Enums\StockReason;
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
     */
    public function getOrCreateTodayDraft(int $branchId, User $user): StockCount
    {
        $today = now()->toDateString();

        $stockCount = StockCount::withoutGlobalScopes()
            ->firstOrCreate(
                ['branch_id' => $branchId, 'count_date' => $today],
                [
                    'status' => 'draft',
                    'started_by' => $user->id,
                ]
            );

        // ดึง stock_levels ทั้งหมดของสาขานี้มาเตรียมเป็น items
        $stockLevels = StockLevel::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->get();

        foreach ($stockLevels as $level) {
            StockCountItem::firstOrCreate(
                [
                    'stock_count_id' => $stockCount->id,
                    'variant_id' => $level->variant_id,
                ],
                [
                    'prev_qty' => $level->qty,
                    'counted_qty' => null,
                    'is_confirmed' => false,
                ]
            );
        }

        return $stockCount->load(['items.variant.product']);
    }

    /**
     * บันทึกผลการนับทีละรายการ (Autosave)
     */
    public function updateCountItem(int $stockCountId, int $variantId, int $countedQty, User $user, ?string $reason = null): StockCountItem
    {
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

        return $item;
    }

    /**
     * กดยืนยันแถวสินค้าว่าตรงกับจำนวนเดิม
     */
    public function confirmRows(int $stockCountId, array $variantIds, User $user): void
    {
        $items = StockCountItem::where('stock_count_id', $stockCountId)
            ->whereIn('variant_id', $variantIds)
            ->get();

        foreach ($items as $item) {
            $countedQty = $item->counted_qty ?? $item->prev_qty;
            $item->update([
                'counted_qty' => $countedQty,
                'diff' => $countedQty - $item->prev_qty,
                'is_confirmed' => true,
                'counted_by' => $user->id,
            ]);
        }
    }

    /**
     * ส่งการนับสต็อกประจำวัน
     */
    public function submitCount(int $stockCountId, User $user): StockCount
    {
        $stockCount = StockCount::withoutGlobalScopes()->with('items')->findOrFail($stockCountId);

        // ตรวจสอบว่ายืนยันครบทุกรายการที่มี prev_qty > 0 หรือยัง (§5.3)
        $unconfirmed = $stockCount->items()
            ->where('prev_qty', '>', 0)
            ->where('is_confirmed', false)
            ->count();

        if ($unconfirmed > 0) {
            throw ValidationException::withMessages([
                'stock_count' => ["ยังยืนยันรายการสต็อกไม่ครบถ้วน (เหลืออีก {$unconfirmed} รายการ)"],
            ]);
        }

        return DB::transaction(function () use ($stockCount, $user) {
            foreach ($stockCount->items as $item) {
                if ($item->is_confirmed && $item->counted_qty !== null) {
                    $this->stockService->change(
                        $stockCount->branch_id,
                        $item->variant_id,
                        $item->counted_qty,
                        StockReason::COUNT,
                        $stockCount,
                        $item->reason
                    );
                }
            }

            $stockCount->update([
                'status' => 'submitted',
                'submitted_by' => $user->id,
                'submitted_at' => now(),
            ]);

            return $stockCount;
        });
    }
}
