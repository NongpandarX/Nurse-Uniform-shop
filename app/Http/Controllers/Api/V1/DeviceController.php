<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * ลงทะเบียนเครื่อง / อัปเดต FCM token
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'device_uid' => ['required', 'string'],
            'platform' => ['required', 'in:android,ios'],
            'fcm_token' => ['nullable', 'string'],
        ]);

        $device = $request->user()->devices()->updateOrCreate(
            ['device_uid' => $request->device_uid],
            [
                'platform' => $request->platform,
                'fcm_token' => $request->fcm_token,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'บันทึกข้อมูลอุปกรณ์เรียบร้อยแล้ว',
            'device' => $device,
        ]);
    }

    /**
     * ลบ FCM token / ยกเลิกอุปกรณ์
     */
    public function destroy(Request $request, string $uid): JsonResponse
    {
        $request->user()->devices()->where('device_uid', $uid)->delete();

        return response()->json([
            'message' => 'ยกเลิกอุปกรณ์เรียบร้อยแล้ว'
        ]);
    }
}
