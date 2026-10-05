<?php

namespace App\Services;

use App\Enums\StockReason;
use App\Events\AbnormalStockDrop;
use App\Events\LowStockDetected;
use App\Events\OutOfStockDetected;
use App\Models\Setting;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockService
{
    /**
     * จุดเดียวของระบบที่ทำการแก้ไข stock_levels.qty
     * พร้อมบันทึก ledger stock_movements และยิง Event ตรวจจับสต็อก
     */
    public function change(
        int $branchId,
        int $variantId,
        int $newQty,
        StockReason $reason,
        ?Model $ref = null,
        ?string $note = null
    ): StockLevel {
        if ($newQty < 0) {
            throw new InvalidArgumentException('จำนวนสินค้าคงเหลือไม่สามารถติดลบได้');
        }

        return DB::transaction(function () use ($branchId, $variantId, $newQty, $reason, $ref, $note) {
            $lv = StockLevel::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('variant_id', $variantId)
                ->lockForUpdate()
                ->firstOrCreate(
                    ['branch_id' => $branchId, 'variant_id' => $variantId],
                    ['qty' => 0, 'min_qty' => 7]
                );

            $before = $lv->qty;
            $diff = $newQty - $before;

            // บันทึก ledger การเคลื่อนไหว
            StockMovement::withoutGlobalScopes()->create([
                'branch_id' => $branchId,
                'variant_id' => $variantId,
                'reason' => $reason,
                'qty_before' => $before,
                'qty_change' => $diff,
                'qty_after' => $newQty,
                'ref_type' => $ref ? get_class($ref) : null,
                'ref_id' => $ref?->getKey(),
                'user_id' => auth()->id(),
                'note' => $note,
                'created_at' => now(),
            ]);

            // อัปเดต stock level
            $lv->update(['qty' => $newQty]);

            // 1. ตรวจสอบการลดลงผิดปกติ (≥ abnormal_drop_qty หรือ ≥ abnormal_drop_percent%)
            $abnormalDropQty = (int) Setting::get('abnormal_drop_qty', 10);
            $abnormalDropPercent = (float) Setting::get('abnormal_drop_percent', 50);

            if ($before - $newQty >= $abnormalDropQty ||
                ($before > 0 && (($before - $newQty) / $before * 100) >= $abnormalDropPercent)) {
                event(new AbnormalStockDrop($lv, $before));
            }

            // 2. ตรวจสอบหมดสต็อก (qty = 0)
            if ($newQty === 0 && !$lv->out_of_stock_alerted_at) {
                $lv->update(['out_of_stock_alerted_at' => now()]);
                event(new OutOfStockDetected($lv));
            } elseif ($newQty > 0 && $lv->out_of_stock_alerted_at) {
                $lv->update(['out_of_stock_alerted_at' => null]);
            }

            // 3. ตรวจสอบสินค้าใกล้หมด (0 < qty <= min_qty)
            if ($newQty > 0 && $newQty <= $lv->min_qty && !$lv->low_stock_alerted_at) {
                $lv->update(['low_stock_alerted_at' => now()]);
                event(new LowStockDetected($lv));
            } elseif ($newQty > $lv->min_qty && $lv->low_stock_alerted_at) {
                $lv->update(['low_stock_alerted_at' => null]);
            }

            return $lv;
        });
    }
}
