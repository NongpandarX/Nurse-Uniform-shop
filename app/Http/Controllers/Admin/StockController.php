<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockReason;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    public function index(): View
    {
        $branches = Branch::where('is_active', true)->get();
        return view('admin.stock.index', compact('branches'));
    }

    public function show(int $branchId): View
    {
        $branch = Branch::findOrFail($branchId);
        $products = Product::with(['variants.stockLevels' => function ($q) use ($branchId) {
            $q->withoutGlobalScopes()->where('branch_id', $branchId);
        }])->get();

        return view('admin.stock.show', compact('branch', 'products'));
    }

    public function movements(Request $request): View
    {
        $branches = Branch::all();
        $query = StockMovement::withoutGlobalScopes()->with(['branch', 'variant.product', 'user'])->latest('id');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $movements = $query->paginate(30);

        return view('admin.stock.movements', compact('movements', 'branches'));
    }

    public function adjust(Request $request): RedirectResponse
    {
        $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'variant_id' => ['required', 'exists:product_variants,id'],
            'new_qty' => ['required', 'integer', 'min:0'],
            'reason' => ['required'],
            'note' => ['nullable', 'string'],
        ]);

        $this->stockService->change(
            $request->branch_id,
            $request->variant_id,
            $request->new_qty,
            StockReason::from($request->reason),
            null,
            $request->note
        );

        return back()->with('success', 'ปรับปรุงสต็อกสำเร็จ');
    }
}
