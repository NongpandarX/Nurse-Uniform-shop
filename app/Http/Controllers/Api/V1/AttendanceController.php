<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    /**
     * เช็คชื่อเข้างาน (GPS ≤ 50 ม.)
     */
    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
            'accuracy' => ['required', 'numeric'],
            'is_mock' => ['nullable', 'boolean'],
        ]);

        $attendance = $this->attendanceService->checkIn($request->user(), $request->all());

        return response()->json([
            'message' => 'บันทึกเวลาเข้างานสำเร็จ',
            'attendance' => $attendance,
        ]);
    }

    /**
     * เช็คชื่อออกงาน
     */
    public function checkOut(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
            'accuracy' => ['required', 'numeric'],
            'is_mock' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $attendance = $this->attendanceService->checkOut($request->user(), $request->all());

        return response()->json([
            'message' => 'บันทึกเวลาออกงานสำเร็จ',
            'attendance' => $attendance,
        ]);
    }

    /**
     * ประวัติการเข้างานของตนเอง
     */
    public function history(Request $request): JsonResponse
    {
        $month = $request->input('month', now()->format('Y-m'));

        $attendances = $request->user()->attendances()
            ->where('work_date', 'like', "{$month}%")
            ->orderByDesc('work_date')
            ->get();

        return response()->json([
            'attendances' => $attendances
        ]);
    }
}
