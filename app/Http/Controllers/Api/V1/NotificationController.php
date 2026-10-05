<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * รายการแจ้งเตือนในแอป
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->appNotifications()
            ->latest('id')
            ->paginate(20);

        return response()->json($notifications);
    }

    /**
     * ทำเครื่องหมายอ่านแล้วรายการเดียว
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $request->user()->appNotifications()
            ->where('id', $id)
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'ทำเครื่องหมายว่าอ่านแล้ว'
        ]);
    }

    /**
     * ทำเครื่องหมายอ่านแล้วทั้งหมด
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->appNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'ทำเครื่องหมายอ่านแล้วทั้งหมด'
        ]);
    }
}
