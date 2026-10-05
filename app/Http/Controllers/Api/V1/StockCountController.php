<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
     */
    public function todayDraft(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id', $request->user()->branch_id);
        $draft = $this->stockCountService->getOrCreateTodayDraft($branchId, $request->user());

        return response()->json([
            'stock_count' => $draft,
        ]);
    }

    /**
     * บันทึกจำนวนนับทีละรายการ (Autosave)
     */
    public function updateItem(Request $request, int $id, int $variantId): JsonResponse
    {
        $request->validate([
            'counted_qty' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string'],
        ]);

        $item = $this->stockCountService->updateCountItem($id, $variantId, $request->counted_qty, $request->user(), $request->reason);

        return response()->json([
            'message' => 'บันทึกจำนวนนับสำเร็จ',
            'item' => $item,
        ]);
    }

    /**
     * กดยืนยันแถวสินค้าว่าตรงกับจำนวนเดิม
     */
    public function confirmRow(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'variant_ids' => ['required', 'array'],
            'variant_ids.*' => ['integer'],
        ]);

        $this->stockCountService->confirmRows($id, $request->variant_ids, $request->user());

        return response()->json([
            'message' => 'ยืนยันรายการแถวเรียบร้อยแล้ว'
        ]);
    }

    /**
     * ส่งการนับสต็อกประจำวัน
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $stockCount = $this->stockCountService->submitCount($id, $request->user());

        return response()->json([
            'message' => 'ส่งผลการนับสต็อกประจำวันเรียบร้อยแล้ว',
            'stock_count' => $stockCount,
        ]);
    }
}
