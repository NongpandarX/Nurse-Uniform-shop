<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function daily(Request $request): View
    {
        $branches = Branch::all();
        $date = $request->input('date', now()->toDateString());
        return view('admin.reports.daily', compact('branches', 'date'));
    }

    public function monthly(Request $request): View
    {
        $branches = Branch::all();
        $month = $request->input('month', now()->format('Y-m'));
        return view('admin.reports.monthly', compact('branches', 'month'));
    }

    public function export(Request $request)
    {
        return back()->with('info', 'ฟังก์ชันส่งออกรายงานจะพร้อมใช้งานในเร็ว ๆ นี้');
    }
}
