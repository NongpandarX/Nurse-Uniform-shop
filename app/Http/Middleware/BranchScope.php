<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BranchScope
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === Role::EMPLOYEE) {
            $requestedBranchId = $request->input('branch_id')
                ?? $request->route('branch_id')
                ?? $request->route('branch')?->id
                ?? $request->header('X-Branch-Id');

            // If an employee attempts to access or manipulate a different branch, deny with 403
            if ($requestedBranchId && (int)$requestedBranchId !== (int)$user->branch_id) {
                return response()->json([
                    'message' => 'คุณไม่มีสิทธิ์เข้าถึงข้อมูลของสาขาอื่น'
                ], 403);
            }

            // Always enforce user's branch_id in request input
            $request->merge(['branch_id' => $user->branch_id]);
        }

        return $next($request);
    }
}
