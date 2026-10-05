<?php

namespace App\Services;

use App\Enums\ChecklistType;
use App\Events\ShopClosed;
use App\Models\Attendance;
use App\Models\ChecklistSubmission;
use App\Models\DailyClosing;
use App\Models\StockCount;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ClosingService
{
    /**
     * ตรวจความพร้อมในการปิดร้าน
     */
    public function checkReadiness(int $branchId): array
    {
        $today = now()->toDateString();

        $stockCount = StockCount::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('count_date', $today)
            ->where('status', 'submitted')
            ->first();

        $closingChecklist = ChecklistSubmission::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('check_date', $today)
            ->where('status', '!=', 'draft')
            ->whereHas('template', fn($q) => $q->where('type', ChecklistType::CLOSING))
            ->first();

        return [
            'has_submitted_stock_count' => !empty($stockCount),
            'has_submitted_closing_checklist' => !empty($closingChecklist),
            'can_close' => !empty($stockCount) && !empty($closingChecklist),
        ];
    }

    /**
     * ดำเนินการแจ้งปิดร้านประจำวัน
     */
    public function closeShop(int $branchId, User $user, ?string $note = null): DailyClosing
    {
        $today = now()->toDateString();
        $readiness = $this->checkReadiness($branchId);

        if (!$readiness['has_submitted_stock_count']) {
            throw ValidationException::withMessages([
                'closing' => ['ต้องทำการส่งผลการนับสต็อกประจำวันก่อนแจ้งปิดร้าน'],
            ]);
        }

        if (!$readiness['has_submitted_closing_checklist']) {
            throw ValidationException::withMessages([
                'closing' => ['ต้องทำการส่งเช็คลิสต์ปิดร้านก่อนแจ้งปิดร้าน'],
            ]);
        }

        $stockCount = StockCount::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('count_date', $today)
            ->first();

        $closingSubmission = ChecklistSubmission::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('check_date', $today)
            ->whereHas('template', fn($q) => $q->where('type', ChecklistType::CLOSING))
            ->first();

        $lowStockCount = StockLevel::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->whereColumn('qty', '<=', 'min_qty')
            ->count();

        $staffPresent = Attendance::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('work_date', $today)
            ->whereNotNull('check_in_at')
            ->count();

        $closing = DailyClosing::withoutGlobalScopes()->updateOrCreate(
            ['branch_id' => $branchId, 'closing_date' => $today],
            [
                'closed_by' => $user->id,
                'closed_at' => now(),
                'stock_count_id' => $stockCount?->id,
                'closing_submission_id' => $closingSubmission?->id,
                'low_stock_count' => $lowStockCount,
                'staff_present' => $staffPresent,
                'note' => $note,
            ]
        );

        event(new ShopClosed($closing));

        return $closing;
    }
}
