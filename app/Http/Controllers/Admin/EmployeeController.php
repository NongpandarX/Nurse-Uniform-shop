<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(): View
    {
        $employees = User::with('branch')->latest('id')->paginate(20);
        return view('admin.employees.index', compact('employees'));
    }

    public function create(): View
    {
        $branches = Branch::where('is_active', true)->get();
        return view('admin.employees.create', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_code' => ['required', 'string', 'unique:users,employee_code'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:admin,employee'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        User::create($validated);

        return redirect()->route('admin.employees.index')->with('success', 'เพิ่มผู้ใช้เรียบร้อยแล้ว');
    }

    public function edit(User $employee): View
    {
        $branches = Branch::where('is_active', true)->get();
        return view('admin.employees.edit', compact('employee', 'branches'));
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'role' => ['required', 'in:admin,employee'],
        ]);

        $employee->update($validated);

        return redirect()->route('admin.employees.index')->with('success', 'แก้ไขข้อมูลเรียบร้อยแล้ว');
    }

    public function resetPassword(Request $request, int $id): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', 'min:6']]);
        $user = User::findOrFail($id);
        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'รีเซ็ตรหัสผ่านสำเร็จ');
    }

    public function toggleActive(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);

        return back()->with('success', 'เปลี่ยนสถานะการใช้งานสำเร็จ');
    }
}
