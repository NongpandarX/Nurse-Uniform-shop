<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\ChecklistSubmission;
use App\Models\DailyClosing;
use App\Models\StockCount;
use App\Models\StockLevel;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * หน้าแรกแดชบอร์ดแอดมิน
     */
    public function index(Request $request): View
    {
        $today = now()->toDateString();
        $branches = Branch::where('is_active', true)->get();

        $stats = [
            'total_branches' => $branches->count(),
            'present_count' => Attendance::where('work_date', $today)->whereNotNull('check_in_at')->count(),
            'late_count' => Attendance::where('work_date', $today)->where('status', 'late')->count(),
            'stock_counted_branches' => StockCount::where('count_date', $today)->where('status', 'submitted')->count(),
            'closed_branches' => DailyClosing::where('closing_date', $today)->count(),
            'low_stock_count' => StockLevel::withoutGlobalScopes()->whereColumn('qty', '<=', 'min_qty')->count(),
            'pending_checklists' => ChecklistSubmission::withoutGlobalScopes()->where('status', 'submitted')->count(),
        ];

        return view('admin.dashboard.index', compact('stats', 'branches', 'today'));
    }
}
