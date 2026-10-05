<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\DailyClosing;
use App\Models\StockLevel;

class ReportService
{
    /**
     * สรุปรายงานรายวัน
     */
    public function getDailySummary(string $date): array
    {
        return [
            'date' => $date,
            'attendances' => Attendance::withoutGlobalScopes()->where('work_date', $date)->get(),
            'closings' => DailyClosing::withoutGlobalScopes()->where('closing_date', $date)->get(),
        ];
    }
}
