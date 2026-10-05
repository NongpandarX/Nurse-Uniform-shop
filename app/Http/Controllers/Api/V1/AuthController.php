<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login ด้วย employee_code + password
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'employee_code' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_uid' => ['nullable', 'string'],
            'platform' => ['nullable', 'in:android,ios'],
        ]);

        $user = User::where('employee_code', $request->employee_code)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'employee_code' => ['รหัสพนักงานหรือรหัสผ่านไม่ถูกต้อง'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'บัญชีผู้ใช้นี้ถูกปิดการใช้งาน กรุณาติดต่อผู้ดูแลระบบ'
            ], 403);
        }

        // Auto-register or link device if device_uid is supplied
        if ($request->filled('device_uid') && $request->filled('platform')) {
            $user->devices()->updateOrCreate(
                ['device_uid' => $request->device_uid],
                [
                    'platform' => $request->platform,
                    'last_seen_at' => now(),
                    // Admin auto-approved, employee defaults to false or true depending on system policy
                    'is_approved' => $user->isAdmin() ? true : false,
                ]
            );
        }

        $user->updateQuietly(['last_login_at' => now()]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'employee_code' => $user->employee_code,
                'name' => $user->name,
                'role' => $user->role->value,
                'branch_id' => $user->branch_id,
                'branch' => $user->branch ? [
                    'id' => $user->branch->id,
                    'code' => $user->branch->code,
                    'name' => $user->branch->name,
                    'lat' => $user->branch->lat,
                    'lng' => $user->branch->lng,
                    'geofence_radius_m' => $user->branch->geofence_radius_m,
                ] : null,
            ],
        ]);
    }

    /**
     * ออกจากระบบ
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'ออกจากระบบเรียบร้อยแล้ว'
        ]);
    }

    /**
     * เปลี่ยนรหัสผ่าน
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['รหัสผ่านปัจจุบันไม่ถูกต้อง'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'เปลี่ยนรหัสผ่านสำเร็จ'
        ]);
    }
}
