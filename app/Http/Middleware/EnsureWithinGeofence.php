<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Setting;
use App\Services\GeofenceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWithinGeofence
{
    public function __construct(
        protected GeofenceService $geofenceService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // ตรวจสอบการตั้งค่า settings ของระบบสำหรับแต่ละกิจกรรม (§4)
        if (str_contains($request->path(), 'stock-counts') && !Setting::get('geofence_required_for_count', true)) {
            return $next($request);
        }
        if (str_contains($request->path(), 'checklists') && !Setting::get('geofence_required_for_checklist', true)) {
            return $next($request);
        }
        if (str_contains($request->path(), 'closing') && !Setting::get('geofence_required_for_closing', true)) {
            return $next($request);
        }

        $branch = $user?->branch ?? Branch::find($request->input('branch_id'));

        $lat = (float) $request->input('lat', 0);
        $lng = (float) $request->input('lng', 0);
        $accuracy = (float) $request->input('accuracy', 0);
        $isMock = (bool) $request->input('is_mock', false);
        $deviceUid = $request->header('X-Device-Uid') ?? $request->input('device_uid');

        $result = $this->geofenceService->validate(
            $user,
            $branch,
            $lat,
            $lng,
            $accuracy,
            $isMock,
            $deviceUid,
            $request->ip(),
            $request->path()
        );

        if (!$result['allowed']) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        if ($result['distance_m'] !== null) {
            $request->merge(['calculated_distance_m' => $result['distance_m']]);
        }

        return $next($request);
    }
}
