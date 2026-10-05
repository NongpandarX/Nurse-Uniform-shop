<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->is_active) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'บัญชีผู้ใช้นี้ถูกปิดการใช้งาน กรุณาติดต่อผู้ดูแลระบบ'
                ], 403);
            }

            auth()->logout();
            return redirect()->route('login')->withErrors(['employee_code' => 'บัญชีผู้ใช้นี้ถูกปิดการใช้งาน']);
        }

        return $next($request);
    }
}
