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
     * ดูสถานะความพร้อมในการปิดร้านวันนี้ (§5.5)
     */
    public function todayStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $user->isAdmin() ? ($request->input('branch_id') ?? $user->branch_id) : $user->branch_id;

        if (!$branchId) {
            return response()->json(['message' => 'ไม่พบข้อมูลสาขา'], 422);
        }

        $status = $this->closingService->checkReadiness($branchId);

        return response()->json($status);
    }

    /**
     * แจ้งปิดร้านประจำวัน (§5.5)
     */
    public function close(Request $request): JsonResponse
    {
        $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $branchId = $user->isAdmin() ? ($request->input('branch_id') ?? $user->branch_id) : $user->branch_id;

        if (!$branchId) {
            return response()->json(['message' => 'ไม่พบข้อมูลสาขา'], 422);
        }

        $closing = $this->closingService->closeShop($branchId, $user, $request->input('note'));

        return response()->json([
            'message' => 'แจ้งปิดร้านประจำวันสำเร็จ',
            'closing' => $closing->load(['branch', 'closedBy', 'stockCount', 'closingSubmission']),
        ]);
    }
}
