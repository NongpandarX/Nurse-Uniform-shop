<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChecklistTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChecklistTemplateController extends Controller
{
    public function index(): View
    {
        $templates = ChecklistTemplate::with('items')->get();
        return view('admin.checklist_templates.index', compact('templates'));
    }

    public function create(): View
    {
        return view('admin.checklist_templates.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required'],
            'name' => ['required', 'string'],
        ]);

        ChecklistTemplate::create($validated);

        return redirect()->route('admin.checklist-templates.index')->with('success', 'สร้างแม่แบบสำเร็จ');
    }

    public function edit(ChecklistTemplate $checklistTemplate): View
    {
        $checklistTemplate->load('items');
        return view('admin.checklist_templates.edit', compact('checklistTemplate'));
    }

    public function update(Request $request, ChecklistTemplate $checklistTemplate): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        $checklistTemplate->update($validated);

        return redirect()->route('admin.checklist-templates.index')->with('success', 'แก้ไขแม่แบบสำเร็จ');
    }
}
