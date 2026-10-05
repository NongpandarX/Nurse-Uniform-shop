<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChecklistStatus;
use App\Http\Controllers\Controller;
use App\Models\ChecklistSubmission;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChecklistReviewController extends Controller
{
    public function index(Request $request): View
    {
        $submissions = ChecklistSubmission::withoutGlobalScopes()
            ->with(['branch', 'template', 'submitter'])
            ->latest('id')
            ->paginate(20);

        return view('admin.checklist_reviews.index', compact('submissions'));
    }

    public function show(int $id): View
    {
        $submission = ChecklistSubmission::withoutGlobalScopes()
            ->with(['branch', 'template', 'submitter', 'items.templateItem', 'items.photos'])
            ->findOrFail($id);

        return view('admin.checklist_reviews.show', compact('submission'));
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $submission = ChecklistSubmission::withoutGlobalScopes()->findOrFail($id);
        $submission->update([
            'status' => ChecklistStatus::APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $request->input('review_note'),
        ]);

        return back()->with('success', 'อนุมัติเช็คลิสต์เรียบร้อยแล้ว');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $request->validate(['review_note' => ['required', 'string']]);

        $submission = ChecklistSubmission::withoutGlobalScopes()->findOrFail($id);
        $submission->update([
            'status' => ChecklistStatus::REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $request->input('review_note'),
        ]);

        return back()->with('success', 'ส่งกลับเช็คลิสต์ให้แก้ไขแล้ว');
    }

    public function pinMedia(int $mediaId): RedirectResponse
    {
        $media = Media::findOrFail($mediaId);
        $media->update(['is_pinned' => !$media->is_pinned]);

        return back()->with('success', 'เปลี่ยนสถานะการปักหมุดรูปภาพสำเร็จ');
    }
}
