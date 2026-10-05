<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopOpen
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Admins can perform actions anytime
        if ($user && $user->role === Role::ADMIN) {
            return $next($request);
        }

        $branch = $user?->branch ?? Branch::find($request->input('branch_id'));

        if (!$branch) {
            return response()->json(['message' => 'ไม่พบข้อมูลสาขา'], 404);
        }

        if (!$branch->is_active) {
            return response()->json(['message' => 'สาขานี้ยังไม่เปิดให้บริการหรือถูกระงับชั่วคราว'], 403);
        }

        $todayWeekday = now()->dayOfWeek; // 0 (Sunday) to 6 (Saturday)
        $closedWeekdays = $branch->closed_weekdays ?? [];

        if (is_array($closedWeekdays) && in_array($todayWeekday, $closedWeekdays, true)) {
            return response()->json([
                'message' => 'วันนี้เป็นวันหยุดทำการของสาขา (' . $branch->name . ')'
            ], 403);
        }

        return $next($request);
    }
}
