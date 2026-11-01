<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    /**
     * ดูสต็อกคงเหลือ (ตารางแบบ × ไซส์)
     */
    public function index(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id', $request->user()->branch_id);

        $stockLevels = StockLevel::where('branch_id', $branchId)
            ->with(['variant.product'])
            ->get();

        return response()->json([
            'branch_id' => $branchId,
            'stock_levels' => $stockLevels,
        ]);
    }

    /**
     * กรองรายการสินค้าใกล้หมด / หมดสต็อก
     */
    public function lowStock(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->input('branch_id', $user->branch_id);

        $query = StockLevel::whereColumn('qty', '<=', 'min_qty')
            ->with(['branch', 'variant.product']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        } elseif ($user->isEmployee() && $user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }

        $items = $query->get();

        return response()->json([
            'branch_id' => $branchId,
            'count' => $items->count(),
            'low_stock_items' => $items,
        ]);
    }

    /**
     * ดูประวัติความเคลื่อนไหวสต็อก (Ledger)
     */
    public function movements(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id', $request->user()->branch_id);

        $movements = StockMovement::where('branch_id', $branchId)
            ->with(['variant.product', 'user'])
            ->latest('id')
            ->paginate($request->input('per_page', 30));

        return response()->json($movements);
    }
}
