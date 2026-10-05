<?php

namespace App\Services;

use App\Enums\ChecklistStatus;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistSubmissionItem;
use App\Models\ChecklistTemplate;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ChecklistService
{
    /**
     * ดึงรายการส่งเช็คลิสต์วันนี้ (สร้าง Draft จาก Template หากยังไม่มี)
     */
    public function getTodaySubmissions(int $branchId, User $user): Collection
    {
        $today = now()->toDateString();
        $templates = ChecklistTemplate::where('is_active', true)->with('items')->get();

        $submissions = collect();

        foreach ($templates as $template) {
            $submission = ChecklistSubmission::withoutGlobalScopes()->firstOrCreate(
                [
                    'branch_id' => $branchId,
                    'template_id' => $template->id,
                    'check_date' => $today,
                ],
                [
                    'status' => ChecklistStatus::DRAFT,
                ]
            );

            foreach ($template->items as $tplItem) {
                ChecklistSubmissionItem::firstOrCreate(
                    [
                        'submission_id' => $submission->id,
                        'template_item_id' => $tplItem->id,
                    ],
                    [
                        'is_ok' => true,
                    ]
                );
            }

            $submissions->push($submission->load(['template', 'items.templateItem', 'items.photos']));
        }

        return $submissions;
    }

    /**
     * บันทึกคำตอบเช็คลิสต์รายข้อ
     */
    public function updateSubmissionItem(int $submissionId, int $itemId, bool $isOk, ?string $note): ChecklistSubmissionItem
    {
        $item = ChecklistSubmissionItem::where('submission_id', $submissionId)
            ->where('id', $itemId)
            ->firstOrFail();

        $item->update([
            'is_ok' => $isOk,
            'note' => $note,
        ]);

        return $item;
    }

    /**
     * แนบรูปภาพเช็คลิสต์
     */
    public function attachPhoto(int $submissionId, int $itemId, UploadedFile $file, User $user): Media
    {
        $item = ChecklistSubmissionItem::where('submission_id', $submissionId)
            ->where('id', $itemId)
            ->firstOrFail();

        $path = $file->store('checklists/' . now()->format('Y/m/d'), 'local');

        return $item->photos()->create([
            'disk' => 'local',
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'created_by' => $user->id,
            'expires_at' => now()->addDays(60),
            'is_pinned' => false,
            'created_at' => now(),
        ]);
    }

    /**
     * ส่งผลการตรวจเช็คลิสต์
     */
    public function submit(int $submissionId, User $user): ChecklistSubmission
    {
        $submission = ChecklistSubmission::withoutGlobalScopes()
            ->with(['items.templateItem', 'items.photos'])
            ->findOrFail($submissionId);

        // ตรวจสอบว่าข้อที่บังคับรูป มีรูปถ่ายครบหรือยัง (§5.6)
        foreach ($submission->items as $item) {
            if ($item->templateItem->require_photo && $item->photos->isEmpty()) {
                throw ValidationException::withMessages([
                    'photo' => ["ข้อ '{$item->templateItem->label}' ต้องทำการแนบรูปถ่ายประกอบ"],
                ]);
            }
        }

        $submission->update([
            'status' => ChecklistStatus::SUBMITTED,
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);

        return $submission;
    }
}
