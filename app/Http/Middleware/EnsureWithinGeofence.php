<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\AttendanceAttempt;
use App\Models\Branch;
use App\Models\Setting;
use App\Support\Geo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWithinGeofence
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Admins can bypass geofence check from anywhere
        if ($user && $user->role === Role::ADMIN) {
            return $next($request);
        }

        $branch = $user?->branch ?? Branch::find($request->input('branch_id'));

        if (!$branch || !$branch->lat || !$branch->lng) {
            return response()->json([
                'message' => 'สาขานี้ยังไม่ได้ตั้งพิกัด หรือไม่พบข้อมูลสาขา'
            ], 422);
        }

        // If branch geofence is disabled globally for this branch, allow
        if (!$branch->geofence_enabled) {
            return $next($request);
        }

        $lat = (float) $request->input('lat');
        $lng = (float) $request->input('lng');
        $accuracy = (float) $request->input('accuracy', 0);
        $isMock = (bool) $request->input('is_mock', false);
        $deviceUid = $request->header('X-Device-Uid') ?? $request->input('device_uid');

        // Check mock location
        if ($isMock) {
            $this->logAttempt($user?->id, $branch->id, $request->path(), $lat, $lng, $accuracy, null, 'ตรวจพบ Mock Location', $deviceUid, $request->ip());
            return response()->json([
                'message' => 'ตรวจพบการจำลองตำแหน่ง (Mock Location) ไม่อนุญาตให้ดำเนินการ'
            ], 422);
        }

        $maxAccuracy = (float) Setting::get('max_accuracy_m', 40);
        if ($accuracy > $maxAccuracy) {
            $this->logAttempt($user?->id, $branch->id, $request->path(), $lat, $lng, $accuracy, null, 'สัญญาณ GPS ไม่แม่นพอ (Accuracy เกินค่าที่กำหนด)', $deviceUid, $request->ip());
            return response()->json([
                'message' => "สัญญาณ GPS ไม่แม่นยำเพียงพอ ({$accuracy} ม. > สูงสุด {$maxAccuracy} ม.) กรุณาลองใหม่อีกครั้ง"
            ], 422);
        }

        $distance = Geo::distanceMeters($lat, $lng, (float)$branch->lat, (float)$branch->lng);
        $radius = (int) ($branch->geofence_radius_m ?: 50);

        if ($distance > $radius) {
            $this->logAttempt($user?->id, $branch->id, $request->path(), $lat, $lng, $accuracy, $distance, "อยู่นอกพื้นที่ร้าน ({$distance} ม.)", $deviceUid, $request->ip());
            return response()->json([
                'message' => "คุณอยู่นอกพื้นที่ร้าน (ระยะห่าง {$distance} ม. เกินกำหนด {$radius} ม.)"
            ], 422);
        }

        // Pass calculated distance to request for controller usage
        $request->merge(['calculated_distance_m' => $distance]);

        return $next($request);
    }

    protected function logAttempt(?int $userId, ?int $branchId, string $action, ?float $lat, ?float $lng, ?float $accuracy, ?float $distance, string $reason, ?string $deviceUid, ?string $ip): void
    {
        try {
            AttendanceAttempt::create([
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
        } catch (\Throwable $e) {
            // Silently ignore log failure during attempt
        }
    }
}
