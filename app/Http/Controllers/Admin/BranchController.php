<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $branches = Branch::withCount(['users'])->get();
        return view('admin.branches.index', compact('branches'));
    }

    public function create(): View
    {
        return view('admin.branches.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'geofence_radius_m' => ['required', 'integer', 'min:10'],
            'open_time' => ['required'],
            'close_time' => ['required'],
            'grace_minutes' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        Branch::create($validated);

        return redirect()->route('admin.branches.index')->with('success', 'เพิ่มสาขาสำเร็จ');
    }

    public function edit(Branch $branch): View
    {
        return view('admin.branches.edit', compact('branch'));
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'geofence_radius_m' => ['required', 'integer', 'min:10'],
            'open_time' => ['required'],
            'close_time' => ['required'],
            'grace_minutes' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $branch->update($validated);

        return redirect()->route('admin.branches.index')->with('success', 'แก้ไขสาขาสำเร็จ');
    }
}
