<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StockCount;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockCountReportController extends Controller
{
    public function index(Request $request): View
    {
        $branches = Branch::all();
        $query = StockCount::withoutGlobalScopes()->with(['branch', 'submittedBy'])->latest('count_date');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $stockCounts = $query->paginate(20);

        return view('admin.stock_counts.index', compact('stockCounts', 'branches'));
    }

    public function show(int $id): View
    {
        $stockCount = StockCount::withoutGlobalScopes()
            ->with(['branch', 'submittedBy', 'items.variant.product'])
            ->findOrFail($id);

        return view('admin.stock_counts.show', compact('stockCount'));
    }

    public function export(Request $request)
    {
        // Export logic will be implemented in Phase 7
        return back()->with('info', 'ฟังก์ชันส่งออก Excel จะพร้อมใช้งานในเร็ว ๆ นี้');
    }
}
