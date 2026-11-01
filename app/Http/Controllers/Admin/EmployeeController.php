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
    /**
     * รายการบัญชีพนักงานทั้งหมด (เฉพาะ role: employee)
     */
    public function index(): View
    {
        $employees = User::where('role', Role::EMPLOYEE)->with('branch')->latest('id')->paginate(20);
        return view('admin.employees.index', compact('employees'));
    }

    /**
     * ฟอร์มเพิ่มบัญชีพนักงานใหม่
     */
    public function create(): View
    {
        $branches = Branch::where('is_active', true)->get();
        return view('admin.employees.create', compact('branches'));
    }

    /**
     * บันทึกบัญชีพนักงานใหม่ (เพิ่มได้เฉพาะ role: employee เท่านั้น)
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_code' => ['required', 'string', 'max:50', 'unique:users,employee_code'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:6'],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['role'] = Role::EMPLOYEE; // แอดมินสามารถเพิ่มได้เฉพาะบัญชีพนักงาน (employee) เท่านั้น
        $validated['is_active'] = true;

        User::create($validated);

        return redirect()->route('admin.employees.index')->with('success', 'เพิ่มบัญชีพนักงานใหม่เรียบร้อยแล้ว');
    }

    /**
     * ฟอร์มแก้ไขข้อมูลพนักงาน
     */
    public function edit(User $employee): View
    {
        if ($employee->role !== Role::EMPLOYEE) {
            abort(404);
        }

        $branches = Branch::where('is_active', true)->get();
        return view('admin.employees.edit', compact('employee', 'branches'));
    }

    /**
     * อัปเดตข้อมูลพนักงาน
     */
    public function update(Request $request, User $employee): RedirectResponse
    {
        if ($employee->role !== Role::EMPLOYEE) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $validated['role'] = Role::EMPLOYEE;
        $employee->update($validated);

        return redirect()->route('admin.employees.index')->with('success', 'แก้ไขข้อมูลพนักงานเรียบร้อยแล้ว');
    }

    /**
     * รีเซ็ตรหัสผ่านพนักงาน
     */
    public function resetPassword(Request $request, int $id): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', 'min:6']]);
        $user = User::where('role', Role::EMPLOYEE)->findOrFail($id);
        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', "รีเซ็ตรหัสผ่านของ {$user->name} สำเร็จ");
    }

    /**
     * สลับสถานะเปิด/ระงับการใช้งานพนักงาน
     */
    public function toggleActive(int $id): RedirectResponse
    {
        $user = User::where('role', Role::EMPLOYEE)->findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);

        $status = $user->is_active ? 'เปิดใช้งาน' : 'ระงับการใช้งาน';
        return back()->with('success', "เปลี่ยนสถานะของ {$user->name} เป็น {$status} เรียบร้อยแล้ว");
    }
}
