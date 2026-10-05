<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceAttempt;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $branches = Branch::all();
        $date = $request->input('date', now()->toDateString());

        $attendances = Attendance::withoutGlobalScopes()
            ->with(['user', 'branch'])
            ->where('work_date', $date)
            ->latest('id')
            ->paginate(25);

        return view('admin.attendances.index', compact('attendances', 'branches', 'date'));
    }

    public function attempts(Request $request): View
    {
        $attempts = AttendanceAttempt::with(['user', 'branch'])->latest('id')->paginate(30);
        return view('admin.attendances.attempts', compact('attempts'));
    }

    public function monthly(Request $request): View
    {
        $month = $request->input('month', now()->format('Y-m'));
        return view('admin.attendances.monthly', compact('month'));
    }

    public function edit(int $id): View
    {
        $attendance = Attendance::withoutGlobalScopes()->with(['user', 'branch'])->findOrFail($id);
        return view('admin.attendances.edit', compact('attendance'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'check_in_at' => ['nullable', 'date'],
            'check_out_at' => ['nullable', 'date'],
            'status' => ['required'],
            'manual_reason' => ['required', 'string'],
        ]);

        $attendance = Attendance::withoutGlobalScopes()->findOrFail($id);
        $validated['edited_by'] = auth()->id();
        $attendance->update($validated);

        return redirect()->route('admin.attendances.index')->with('success', 'แก้ไขเวลาเข้างานเรียบร้อยแล้ว');
    }
}
