<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ChecklistSubmission;
use App\Models\DailyClosing;
use App\Models\StockCount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * โปรไฟล์ตนเอง
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load('branch');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'employee_code' => $user->employee_code,
                'name' => $user->name,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'branch' => $user->branch,
            ]
        ]);
    }

    /**
     * สถานะการทำงานวันนี้ (เข้างานแล้ว? นับสต็อกแล้ว? ปิดร้านแล้ว?)
     */
    public function todayStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = now()->toDateString();
        $branchId = $user->branch_id;

        $attendance = Attendance::where('user_id', $user->id)
            ->where('work_date', $today)
            ->first();

        $stockCount = $branchId ? StockCount::where('branch_id', $branchId)
            ->where('count_date', $today)
            ->first() : null;

        $checklistCount = $branchId ? ChecklistSubmission::where('branch_id', $branchId)
            ->where('check_date', $today)
            ->where('status', '!=', 'draft')
            ->count() : 0;

        $closing = $branchId ? DailyClosing::where('branch_id', $branchId)
            ->where('closing_date', $today)
            ->first() : null;

        return response()->json([
            'date' => $today,
            'attendance' => [
                'checked_in' => !empty($attendance?->check_in_at),
                'check_in_at' => $attendance?->check_in_at,
                'checked_out' => !empty($attendance?->check_out_at),
                'check_out_at' => $attendance?->check_out_at,
                'status' => $attendance?->status?->value,
            ],
            'stock_count' => [
                'submitted' => $stockCount?->status === 'submitted',
                'status' => $stockCount?->status,
            ],
            'checklist' => [
                'submitted_count' => $checklistCount,
            ],
            'closing' => [
                'closed' => !empty($closing),
                'closed_at' => $closing?->closed_at,
            ],
        ]);
    }
}
