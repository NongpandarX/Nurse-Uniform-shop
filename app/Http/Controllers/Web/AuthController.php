<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * แสดงหน้าฟอร์ม Login เว็บ
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * ประมวลผลการเข้าสู่ระบบ
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'employee_code' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            if (!$user->is_active) {
                Auth::logout();
                return back()->withErrors(['employee_code' => 'บัญชีผู้ใช้นี้ถูกปิดการใช้งาน']);
            }

            $user->updateQuietly(['last_login_at' => now()]);

            if ($user->isAdmin()) {
                return redirect()->intended(route('admin.dashboard'));
            }

            return redirect()->intended('/');
        }

        return back()->withErrors([
            'employee_code' => 'รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง',
        ])->onlyInput('employee_code');
    }

    /**
     * ออกจากระบบ
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
