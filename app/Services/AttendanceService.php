<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Events\StaffLate;
use App\Exceptions\AlreadyCheckedInException;
use App\Models\Attendance;
use App\Models\User;
use App\Support\Geo;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    /**
     * เช็คชื่อเข้างาน (GPS ≤ 50 ม.)
     * กฎ (§5.2):
     * - เข้าได้ตั้งแต่ open_time - 60 นาที
     * - สายเมื่อเกิน open_time + grace_minutes → บันทึก late_minutes และยิง StaffLate
     * - 1 คน 1 แถว/วัน (unique(user_id, work_date)), เข้าซ้ำ → 409
     */
    public function checkIn(User $user, array $geoData): Attendance
    {
        $today = now()->toDateString();
        $branch = $user->branch;

        // 1. ตรวจสอบการเข้าซ้ำในวันเดียวกัน (§5.2: เข้าซ้ำ → 409)
        $existing = Attendance::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        if ($existing && $existing->check_in_at) {
            throw new AlreadyCheckedInException('คุณได้ทำการเช็คชื่อเข้างานของวันนี้ไปแล้ว');
        }

        $now = now();
        $status = AttendanceStatus::ON_TIME;
        $lateMinutes = 0;

        // 2. ตรวจสอบเวลาเริ่มให้เช็คชื่อ (เข้าได้ตั้งแต่ open_time - 60 นาที)
        if ($branch && $branch->open_time) {
            $openTime = Carbon::parse("{$today} {$branch->open_time}");
            $earliestCheckIn = $openTime->copy()->subMinutes(60);

            if ($now->lessThan($earliestCheckIn)) {
                throw ValidationException::withMessages([
                    'attendance' => ["ยังไม่ถึงเวลาเปิดให้เช็คชื่อเข้างาน (สามารถเช็คชื่อได้ตั้งแต่ {$earliestCheckIn->format('H:i')} น.)"],
                ]);
            }

            // 3. คำนวณการมาสาย (เกิน open_time + grace_minutes)
            $graceMinutes = (int) ($branch->grace_minutes ?? 10);
            $lateThreshold = $openTime->copy()->addMinutes($graceMinutes);

            if ($now->greaterThan($lateThreshold)) {
                $status = AttendanceStatus::LATE;
                $lateMinutes = (int) $openTime->diffInMinutes($now);
            }
        }

        // คำนวณระยะห่าง
        $distance = $geoData['calculated_distance_m'] ?? null;
        if ($distance === null && $branch && $branch->lat && isset($geoData['lat'], $geoData['lng'])) {
            $distance = Geo::distanceMeters((float)$geoData['lat'], (float)$geoData['lng'], (float)$branch->lat, (float)$branch->lng);
        }

        $data = [
            'user_id' => $user->id,
            'branch_id' => $user->branch_id,
            'work_date' => $today,
            'check_in_at' => $now,
            'check_in_lat' => $geoData['lat'] ?? null,
            'check_in_lng' => $geoData['lng'] ?? null,
            'check_in_accuracy' => $geoData['accuracy'] ?? null,
            'check_in_distance_m' => $distance,
            'status' => $status,
            'late_minutes' => $lateMinutes,
        ];

        // 4. บันทึกข้อมูลเข้างาน
        if ($existing) {
            $existing->update($data);
            $attendance = $existing;
        } else {
            $attendance = Attendance::create($data);
        }

        // 5. หากมาสาย ให้ยิง Event แจ้งเตือนแอดมินทันที
        if ($status === AttendanceStatus::LATE) {
            event(new StaffLate($attendance));
        }

        return $attendance;
    }

    /**
     * เช็คชื่อออกงาน
     * กฎ (§5.2):
     * - ต้องเช็คชื่อเข้างานก่อน
     * - ออกก่อน close_time ต้องใส่เหตุผล (early_leave)
     */
    public function checkOut(User $user, array $geoData): Attendance
    {
        $today = now()->toDateString();
        $branch = $user->branch;

        $attendance = Attendance::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        if (!$attendance || !$attendance->check_in_at) {
            throw ValidationException::withMessages([
                'attendance' => ['ต้องทำการเช็คชื่อเข้างานก่อนจึงจะสามารถออกงานได้'],
            ]);
        }

        if ($attendance->check_out_at) {
            throw ValidationException::withMessages([
                'attendance' => ['คุณได้ทำการเช็คชื่อออกงานของวันนี้ไปแล้ว'],
            ]);
        }

        $now = now();
        $status = $attendance->status;
        $note = isset($geoData['note']) ? trim((string) $geoData['note']) : null;

        // ตรวจสอบการออกก่อนเวลาปิดร้าน
        if ($branch && $branch->close_time) {
            $closeTime = Carbon::parse("{$today} {$branch->close_time}");

            if ($now->lessThan($closeTime)) {
                if (empty($note)) {
                    throw ValidationException::withMessages([
                        'note' => ["การออกงานก่อนเวลาปิดร้าน ({$closeTime->format('H:i')} น.) จำเป็นต้องระบุเหตุผล"],
                    ]);
                }
                $status = AttendanceStatus::EARLY_LEAVE;
            }
        }

        // คำนวณระยะห่าง
        $distance = $geoData['calculated_distance_m'] ?? null;
        if ($distance === null && $branch && $branch->lat && isset($geoData['lat'], $geoData['lng'])) {
            $distance = Geo::distanceMeters((float)$geoData['lat'], (float)$geoData['lng'], (float)$branch->lat, (float)$branch->lng);
        }

        $attendance->update([
            'check_out_at' => $now,
            'check_out_lat' => $geoData['lat'] ?? null,
            'check_out_lng' => $geoData['lng'] ?? null,
            'check_out_distance_m' => $distance,
            'status' => $status,
            'note' => $note ?: $attendance->note,
        ]);

        return $attendance;
    }
}
