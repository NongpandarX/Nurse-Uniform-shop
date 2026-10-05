<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Events\StaffLate;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    /**
     * เช็คชื่อเข้างาน
     */
    public function checkIn(User $user, array $geoData): Attendance
    {
        $today = now()->toDateString();
        $branch = $user->branch;

        $existing = Attendance::where('user_id', $user->id)
            ->where('work_date', $today)
            ->first();

        if ($existing && $existing->check_in_at) {
            throw ValidationException::withMessages([
                'attendance' => ['คุณได้ทำการเช็คชื่อเข้างานของวันนี้ไปแล้ว'],
            ]);
        }

        $now = now();
        $openTime = Carbon::createFromTimeString($branch->open_time);
        $graceMinutes = (int) $branch->grace_minutes;
        $lateThreshold = $openTime->copy()->addMinutes($graceMinutes);

        $status = AttendanceStatus::ON_TIME;
        $lateMinutes = 0;

        if ($now->greaterThan($lateThreshold)) {
            $status = AttendanceStatus::LATE;
            $lateMinutes = (int) $openTime->diffInMinutes($now);
        }

        $attendance = Attendance::updateOrCreate(
            ['user_id' => $user->id, 'work_date' => $today],
            [
                'branch_id' => $user->branch_id,
                'check_in_at' => $now,
                'check_in_lat' => $geoData['lat'] ?? null,
                'check_in_lng' => $geoData['lng'] ?? null,
                'check_in_accuracy' => $geoData['accuracy'] ?? null,
                'check_in_distance_m' => $geoData['calculated_distance_m'] ?? null,
                'status' => $status,
                'late_minutes' => $lateMinutes,
            ]
        );

        if ($status === AttendanceStatus::LATE) {
            event(new StaffLate($attendance));
        }

        return $attendance;
    }

    /**
     * เช็คชื่อออกงาน
     */
    public function checkOut(User $user, array $geoData): Attendance
    {
        $today = now()->toDateString();
        $branch = $user->branch;

        $attendance = Attendance::where('user_id', $user->id)
            ->where('work_date', $today)
            ->first();

        if (!$attendance || !$attendance->check_in_at) {
            throw ValidationException::withMessages([
                'attendance' => ['ต้องทำการเช็คชื่อเข้างานก่อนจึงจะสามารถออกงานได้'],
            ]);
        }

        $now = now();
        $closeTime = Carbon::createFromTimeString($branch->close_time);

        $status = $attendance->status;
        if ($now->lessThan($closeTime) && empty($geoData['note'])) {
            $status = AttendanceStatus::EARLY_LEAVE;
        }

        $attendance->update([
            'check_out_at' => $now,
            'check_out_lat' => $geoData['lat'] ?? null,
            'check_out_lng' => $geoData['lng'] ?? null,
            'check_out_distance_m' => $geoData['calculated_distance_m'] ?? null,
            'status' => $status,
            'note' => $geoData['note'] ?? $attendance->note,
        ]);

        return $attendance;
    }
}
