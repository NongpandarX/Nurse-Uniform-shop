<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StockReason;
use App\Http\Controllers\Controller;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class StockUpdateController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    /**
     * อัปเดตสต็อกนอกรอบ (ของเข้า/เปลี่ยน/เสีย/โอน/แก้ไข)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'variant_id' => ['required', 'exists:product_variants,id'],
            'new_qty' => ['required', 'integer', 'min:0'],
            'reason' => ['required', new Enum(StockReason::class)],
            'note' => ['nullable', 'string'],
        ]);

        $branchId = $request->input('branch_id', $request->user()->branch_id);
        $reason = StockReason::from($request->reason);

        $stockLevel = $this->stockService->change(
            $branchId,
            $request->variant_id,
            $request->new_qty,
            $reason,
            null,
            $request->note
        );

        return response()->json([
            'message' => 'อัปเดตสต็อกสำเร็จ',
            'stock_level' => $stockLevel,
        ]);
    }
}
