<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\StockCountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockCountController extends Controller
{
    public function __construct(
        protected StockCountService $stockCountService
    ) {}

    /**
     * ดึงรอบนับสต็อกวันนี้ (สร้าง Draft อัตโนมัติจาก StockLevel หากยังไม่มี)
     * (§5.3: GET /api/v1/stock-counts/today)
     */
    public function todayDraft(Request $request): JsonResponse
    {
        $user = $request->user();

        // พนักงานประจำสาขาจะถูกจำกัดเฉพาะสาขาตัวเอง ส่วน Admin สามารถระบุ branch_id ได้
        if ($user->role === Role::EMPLOYEE) {
            $branchId = (int) $user->branch_id;
        } else {
            $branchId = (int) $request->input('branch_id', $user->branch_id ?? Branch::first()?->id);
        }

        $draft = $this->stockCountService->getOrCreateTodayDraft($branchId, $user);

        // คำนวณสรุปสถิติความคืบหน้าการนับ (Summary Stats)
        $items = $draft->items;
        $totalItems = $items->count();
        $confirmedItems = $items->where('is_confirmed', true)->count();
        $pendingItems = $items->where('prev_qty', '>', 0)->where('is_confirmed', false)->count();
        $totalPrevQty = (int) $items->sum('prev_qty');
        $totalCountedQty = (int) $items->whereNotNull('counted_qty')->sum('counted_qty');
        $totalDiff = (int) $items->where('is_confirmed', true)->sum('diff');

        return response()->json([
            'success' => true,
            'stock_count' => $draft,
            'summary' => [
                'total_items' => $totalItems,
                'confirmed_items' => $confirmedItems,
                'pending_items' => $pendingItems,
                'is_ready_to_submit' => ($pendingItems === 0),
                'total_prev_qty' => $totalPrevQty,
                'total_counted_qty' => $totalCountedQty,
                'total_diff' => $totalDiff,
            ],
        ]);
    }

    /**
     * บันทึกจำนวนนับทีละรายการลงฐานข้อมูลทันที (Autosave)
     * (§5.3: PUT /api/v1/stock-counts/{id}/items/{variantId})
     */
    public function updateItem(Request $request, int $id, int $variantId): JsonResponse
    {
        $request->validate([
            'counted_qty' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $item = $this->stockCountService->updateCountItem(
            $id,
            $variantId,
            (int) $request->counted_qty,
            $request->user(),
            $request->reason
        );

        return response()->json([
            'success' => true,
            'message' => 'บันทึกจำนวนนับสำเร็จ (Autosaved)',
            'item' => $item,
        ]);
    }

    /**
     * กดยืนยันแถวสินค้าว่าตรงกับจำนวนเดิม (Confirm Row)
     * (§5.3: POST /api/v1/stock-counts/{id}/confirm-row)
     */
    public function confirmRow(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'variant_ids' => ['nullable', 'array'],
            'variant_ids.*' => ['integer'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        if (!$request->filled('variant_ids') && !$request->filled('product_id')) {
            return response()->json([
                'success' => false,
                'message' => 'กรุณาระบุ variant_ids หรือ product_id ที่ต้องการยืนยัน',
            ], 422);
        }

        if ($request->filled('product_id')) {
            $count = $this->stockCountService->confirmProduct($id, (int) $request->product_id, $request->user());
        } else {
            $count = $this->stockCountService->confirmRows($id, $request->variant_ids, $request->user());
        }

        return response()->json([
            'success' => true,
            'message' => "ยืนยันรายการแถวเรียบร้อยแล้ว ({$count} รายการ)",
            'confirmed_count' => $count,
        ]);
    }

    /**
     * ส่งการนับสต็อกประจำวัน
     * (§5.3: POST /api/v1/stock-counts/{id}/submit)
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $stockCount = $this->stockCountService->submitCount($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'ส่งผลการนับสต็อกประจำวันเรียบร้อยแล้ว ยอดสต็อกได้รับการปรับปรุงแล้ว',
            'stock_count' => $stockCount,
        ]);
    }

    /**
     * เปิดรอบแก้ไขการนับสต็อกใหม่ในวันเดียวกัน (Revision +1)
     * (§5.3: POST /api/v1/stock-counts/{id}/reopen)
     */
    public function reopen(Request $request, int $id): JsonResponse
    {
        $stockCount = $this->stockCountService->reopenDraft($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => "เปิดรอบแก้ไขการนับสต็อกใหม่เรียบร้อยแล้ว (รอบที่ {$stockCount->revision})",
            'stock_count' => $stockCount,
        ]);
    }
}
