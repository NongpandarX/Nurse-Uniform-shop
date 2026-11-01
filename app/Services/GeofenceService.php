<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\AttendanceAttempt;
use App\Models\Branch;
use App\Models\Setting;
use App\Models\User;
use App\Support\Geo;

class GeofenceService
{
    /**
     * ตรวจสอบพิกัด GPS ระยะทาง ความแม่นยำ และการจำลองตำแหน่ง (Mock Location)
     * ตามข้อกำหนด §1, §4, §5.2 และ §7.3
     */
    public function validate(
        User $user,
        ?Branch $branch,
        float $lat,
        float $lng,
        float $accuracy,
        bool $isMock = false,
        ?string $deviceUid = null,
        ?string $ip = null,
        string $action = 'attendance'
    ): array {
        // Admin สามารถข้ามการตรวจสอบ Geofence ได้ทุกกรณี
        if ($user->role === Role::ADMIN) {
            return [
                'allowed' => true,
                'distance_m' => 0.0,
                'error_code' => null,
                'message' => null,
            ];
        }

        // หากไม่พบข้อมูลสาขา หรือสาขายังไม่ได้ระบุพิกัด (เช่น CNX2)
        if (!$branch || $branch->lat === null || $branch->lng === null) {
            $message = 'สาขานี้ยังไม่ได้ตั้งพิกัด หรือไม่พบข้อมูลสาขา';
            $this->logAttempt($user->id, $branch?->id, $action, $lat, $lng, $accuracy, null, $message, $deviceUid, $ip);

            return [
                'allowed' => false,
                'distance_m' => null,
                'error_code' => 'no_coordinates',
                'message' => $message,
            ];
        }

        // หากสาขาปิดใช้งาน Geofence ชั่วคราว
        if (!$branch->geofence_enabled) {
            return [
                'allowed' => true,
                'distance_m' => 0.0,
                'error_code' => null,
                'message' => null,
            ];
        }

        // 1. ตรวจสอบการจำลองตำแหน่ง (Mock Location)
        if ($isMock) {
            $reason = 'ตรวจพบการจำลองตำแหน่ง (Mock Location)';
            $this->logAttempt($user->id, $branch->id, $action, $lat, $lng, $accuracy, null, $reason, $deviceUid, $ip);

            return [
                'allowed' => false,
                'distance_m' => null,
                'error_code' => 'mock_location',
                'message' => 'ตรวจพบการจำลองตำแหน่ง (Mock Location) ไม่อนุญาตให้ดำเนินการ',
            ];
        }

        // 2. ตรวจสอบความแม่นยำของสัญญาณ GPS (Accuracy)
        $maxAccuracy = (float) Setting::get('max_accuracy_m', 40);
        if ($accuracy > $maxAccuracy) {
            $reason = "สัญญาณ GPS ไม่แม่นพอ (Accuracy {$accuracy} ม. > สูงสุด {$maxAccuracy} ม.)";
            $this->logAttempt($user->id, $branch->id, $action, $lat, $lng, $accuracy, null, $reason, $deviceUid, $ip);

            return [
                'allowed' => false,
                'distance_m' => null,
                'error_code' => 'inaccurate_gps',
                'message' => "สัญญาณ GPS ไม่แม่นยำเพียงพอ ({$accuracy} ม. > สูงสุด {$maxAccuracy} ม.) กรุณาลองใหม่อีกครั้ง",
            ];
        }

        // 3. คำนวณระยะห่างด้วย Haversine Formula (§1 รัศมี 50 ม.)
        $distance = Geo::distanceMeters($lat, $lng, (float) $branch->lat, (float) $branch->lng);
        $radius = (int) ($branch->geofence_radius_m ?: 50);

        if ($distance > $radius) {
            $reason = "อยู่นอกพื้นที่ร้าน (ระยะห่าง {$distance} ม. เกินกำหนด {$radius} ม.)";
            $this->logAttempt($user->id, $branch->id, $action, $lat, $lng, $accuracy, $distance, $reason, $deviceUid, $ip);

            return [
                'allowed' => false,
                'distance_m' => $distance,
                'error_code' => 'outside_geofence',
                'message' => "คุณอยู่นอกพื้นที่ร้าน (ระยะห่าง {$distance} ม. เกินกำหนด {$radius} ม.)",
            ];
        }

        // ผ่านเงื่อนไขทั้งหมด
        return [
            'allowed' => true,
            'distance_m' => $distance,
            'error_code' => null,
            'message' => null,
        ];
    }

    /**
     * บันทึก Log ความพยายามที่ไม่ผ่านเกณฑ์ลงตาราง attendance_attempts
     */
    public function logAttempt(
        ?int $userId,
        ?int $branchId,
        string $action,
        ?float $lat,
        ?float $lng,
        ?float $accuracy,
        ?float $distance,
        string $reason,
        ?string $deviceUid,
        ?string $ip
    ): ?AttendanceAttempt {
        try {
            return AttendanceAttempt::create([
                'user_id' => $userId,
                'branch_id' => $branchId,
                'action' => $action,
                'lat' => $lat,
                'lng' => $lng,
                'accuracy' => $accuracy,
                'distance_m' => $distance,
                'reason' => $reason,
                'device_uid' => $deviceUid,
                'ip' => $ip,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }
}

