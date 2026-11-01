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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ChecklistService
{
    public function __construct(
        protected ImageService $imageService
    ) {}

    /**
     * ดึงรายการเช็คลิสต์วันนี้ (สร้าง Draft จาก Template หากยังไม่มี)
     * (§5.5, §5.6: 1 สาขา 1 รายการต่อแม่แบบ/วัน)
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
                        'is_ok' => false,
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
    public function updateSubmissionItem(int $submissionId, int $itemId, bool $isOk, ?string $note, User $user): ChecklistSubmissionItem
    {
        $submission = ChecklistSubmission::findOrFail($submissionId);

        if ($submission->status === ChecklistStatus::SUBMITTED || $submission->status === ChecklistStatus::APPROVED) {
            throw ValidationException::withMessages([
                'checklist' => ['เช็คลิสต์นี้ถูกส่งไปแล้ว ไม่สามารถแก้ไขคำตอบได้'],
            ]);
        }

        $item = ChecklistSubmissionItem::where('submission_id', $submissionId)
            ->where('id', $itemId)
            ->firstOrFail();

        $item->update([
            'is_ok' => $isOk,
            'note' => $note,
        ]);

        return $item->load(['templateItem', 'photos']);
    }

    /**
     * แนบและบีบอัดรูปภาพเช็คลิสต์ (แปลงเป็น WebP q=70 และสร้าง Thumbnail) (§5.6)
     */
    public function attachPhoto(int $submissionId, int $itemId, UploadedFile $file, User $user): Media
    {
        $submission = ChecklistSubmission::findOrFail($submissionId);

        if ($submission->status === ChecklistStatus::SUBMITTED || $submission->status === ChecklistStatus::APPROVED) {
            throw ValidationException::withMessages([
                'checklist' => ['เช็คลิสต์นี้ถูกส่งไปแล้ว ไม่สามารถแนบรูปเพิ่มได้'],
            ]);
        }

        $item = ChecklistSubmissionItem::where('submission_id', $submissionId)
            ->where('id', $itemId)
            ->firstOrFail();

        return $this->imageService->storeAndCompress($file, $item, $user);
    }

    /**
     * ลบรูปภาพที่แนบไว้ในข้อตรวจเช็ค
     */
    public function deletePhoto(int $submissionId, int $itemId, int $mediaId, User $user): bool
    {
        $submission = ChecklistSubmission::findOrFail($submissionId);

        if ($submission->status === ChecklistStatus::SUBMITTED || $submission->status === ChecklistStatus::APPROVED) {
            throw ValidationException::withMessages([
                'checklist' => ['เช็คลิสต์นี้ถูกส่งไปแล้ว ไม่สามารถลบรูปได้'],
            ]);
        }

        $item = ChecklistSubmissionItem::where('submission_id', $submissionId)
            ->where('id', $itemId)
            ->firstOrFail();

        $photo = $item->photos()->where('id', $mediaId)->firstOrFail();

        if (Storage::disk($photo->disk)->exists($photo->path)) {
            Storage::disk($photo->disk)->delete($photo->path);
        }
        if ($photo->thumb_path && Storage::disk($photo->disk)->exists($photo->thumb_path)) {
            Storage::disk($photo->disk)->delete($photo->thumb_path);
        }

        return (bool) $photo->delete();
    }

    /**
     * ส่งผลการตรวจเช็คลิสต์
     * (§5.6: ตรวจสอบรูปภาพครบทุกข้อที่ require_photo = true)
     */
    public function submit(int $submissionId, User $user): ChecklistSubmission
    {
        $submission = ChecklistSubmission::with(['items.templateItem', 'items.photos'])
            ->findOrFail($submissionId);

        if ($submission->status === ChecklistStatus::SUBMITTED || $submission->status === ChecklistStatus::APPROVED) {
            throw ValidationException::withMessages([
                'checklist' => ['เช็คลิสต์นี้ได้ทำการส่งไปเรียบร้อยแล้ว'],
            ]);
        }

        // ตรวจสอบว่าข้อที่กำหนด require_photo = true มีการแนบรูปภาพอย่างน้อย 1 รูป (§5.6)
        foreach ($submission->items as $item) {
            if ($item->templateItem->require_photo && $item->photos->isEmpty()) {
                throw ValidationException::withMessages([
                    'photo' => ["ข้อ '{$item->templateItem->label}' ต้องทำการแนบรูปถ่ายประกอบก่อนส่ง"],
                ]);
            }
        }

        $submission->update([
            'status' => ChecklistStatus::SUBMITTED,
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ]);

        return $submission->fresh(['template', 'items.templateItem', 'items.photos', 'submitter']);
    }

    /**
     * แอดมินอนุมัติผลการตรวจเช็คลิสต์ (§7.2)
     */
    public function approve(int $submissionId, User $reviewer, ?int $score = null, ?string $reviewNote = null): ChecklistSubmission
    {
        $submission = ChecklistSubmission::withoutGlobalScopes()->findOrFail($submissionId);

        $submission->update([
            'status' => ChecklistStatus::APPROVED,
            'score' => $score,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $reviewNote,
        ]);

        return $submission->fresh(['template', 'items.templateItem', 'items.photos', 'reviewer']);
    }

    /**
     * แอดมินตีกลับเช็คลิสต์ให้แก้ไข (§5.7, §7.2)
     */
    public function reject(int $submissionId, User $reviewer, string $reviewNote): ChecklistSubmission
    {
        $submission = ChecklistSubmission::withoutGlobalScopes()->findOrFail($submissionId);

        $submission->update([
            'status' => ChecklistStatus::REJECTED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $reviewNote,
        ]);

        return $submission->fresh(['template', 'items.templateItem', 'items.photos', 'reviewer']);
    }
}
