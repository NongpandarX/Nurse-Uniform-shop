<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ClosingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClosingController extends Controller
{
    public function __construct(
        protected ClosingService $closingService
    ) {}

    /**
     * ดูสถานะความพร้อมในการปิดร้านวันนี้
     */
    public function todayStatus(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id', $request->user()->branch_id);
        $status = $this->closingService->checkReadiness($branchId);

        return response()->json($status);
    }

    /**
     * แจ้งปิดร้านประจำวัน
     */
    public function close(Request $request): JsonResponse
    {
        $request->validate([
            'note' => ['nullable', 'string'],
        ]);

        $branchId = $request->input('branch_id', $request->user()->branch_id);
        $closing = $this->closingService->closeShop($branchId, $request->user(), $request->note);

        return response()->json([
            'message' => 'แจ้งปิดร้านประจำวันสำเร็จ',
            'closing' => $closing,
        ]);
    }
}
