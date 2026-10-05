<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $products = Product::with('variants')->orderBy('sort')->paginate(25);
        return view('admin.products.index', compact('products'));
    }

    public function create(): View
    {
        return view('admin.products.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'unique:products,code'],
            'name' => ['required', 'string'],
            'category' => ['required'],
            'has_sizes' => ['boolean'],
            'sort' => ['integer'],
        ]);

        Product::create($validated);

        return redirect()->route('admin.products.index')->with('success', 'เพิ่มสินค้าสำเร็จ');
    }

    public function edit(Product $product): View
    {
        $product->load('variants');
        return view('admin.products.edit', compact('product'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'category' => ['required'],
            'has_sizes' => ['boolean'],
            'sort' => ['integer'],
            'is_active' => ['boolean'],
        ]);

        $product->update($validated);

        return redirect()->route('admin.products.index')->with('success', 'แก้ไขสินค้าสำเร็จ');
    }

    public function updateMinQty(Request $request): RedirectResponse
    {
        $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'variant_id' => ['required', 'exists:product_variants,id'],
            'min_qty' => ['required', 'integer', 'min:0'],
        ]);

        StockLevel::withoutGlobalScopes()->where('branch_id', $request->branch_id)
            ->where('variant_id', $request->variant_id)
            ->update(['min_qty' => $request->min_qty]);

        return back()->with('success', 'อัปเดตเกณฑ์สต็อกขั้นต่ำสำเร็จ');
    }
}
