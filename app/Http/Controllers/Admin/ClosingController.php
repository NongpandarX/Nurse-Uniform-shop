<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyClosing;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClosingController extends Controller
{
    public function index(Request $request): View
    {
        $closings = DailyClosing::withoutGlobalScopes()
            ->with(['branch', 'closedBy', 'stockCount'])
            ->latest('closing_date')
            ->paginate(20);

        return view('admin.closings.index', compact('closings'));
    }

    public function show(int $id): View
    {
        $closing = DailyClosing::withoutGlobalScopes()
            ->with(['branch', 'closedBy', 'stockCount.items.variant.product', 'closingSubmission.items.photos'])
            ->findOrFail($id);

        return view('admin.closings.show', compact('closing'));
    }
}
