<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\UserDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceBound
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Admins bypass device binding check
        if (!$user || $user->role === Role::ADMIN) {
            return $next($request);
        }

        $deviceUid = $request->header('X-Device-Uid') ?? $request->input('device_uid');

        if (!$deviceUid) {
            return response()->json([
                'message' => 'กรุณาระบุรหัสอุปกรณ์ (X-Device-Uid) ในการร้องขอ'
            ], 422);
        }

        $device = UserDevice::where('user_id', $user->id)
            ->where('device_uid', $deviceUid)
            ->first();

        if (!$device) {
            return response()->json([
                'message' => 'อุปกรณ์นี้ยังไม่ได้ลงทะเบียนในระบบ กรุณาเข้าสู่ระบบใหม่เพื่อผูกอุปกรณ์'
            ], 403);
        }

        if (!$device->is_approved) {
            return response()->json([
                'message' => 'อุปกรณ์นี้กำลังรอการอนุมัติจากผู้ดูแลระบบ'
            ], 403);
        }

        // Update last seen
        $device->updateQuietly(['last_seen_at' => now()]);

        return $next($request);
    }
}
