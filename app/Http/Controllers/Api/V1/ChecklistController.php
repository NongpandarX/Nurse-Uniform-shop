<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\ChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function __construct(
        protected ChecklistService $checklistService
    ) {}

    /**
     * ดึงรายการเช็คลิสต์ประจำวัน (เปิดร้าน, ปิดร้าน, ความสะอาด)
     * (§5.5, §5.6: GET /api/v1/checklists/today)
     */
    public function todayChecklists(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role === Role::EMPLOYEE) {
            $branchId = (int) $user->branch_id;
        } else {
            $branchId = (int) $request->input('branch_id', $user->branch_id ?? Branch::first()?->id);
        }

        $submissions = $this->checklistService->getTodaySubmissions($branchId, $user);

        return response()->json([
            'success' => true,
            'checklists' => $submissions,
        ]);
    }

    /**
     * บันทึกคำตอบเช็คลิสต์ทีละข้อ
     * (§5.6: PUT /api/v1/checklists/{id}/items/{itemId})
     */
    public function updateItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $request->validate([
            'is_ok' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $item = $this->checklistService->updateSubmissionItem(
            $id,
            $itemId,
            (bool) $request->is_ok,
            $request->note,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'บันทึกคำตอบเช็คลิสต์สำเร็จ',
            'item' => $item,
        ]);
    }

    /**
     * อัปโหลดรูปภาพเช็คลิสต์ (บีบอัดและแปลงเป็น WebP q=70 อัตโนมัติ)
     * (§5.6: POST /api/v1/checklists/{id}/items/{itemId}/photos)
     */
    public function uploadPhoto(Request $request, int $id, int $itemId): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic', 'max:5120'], // ไฟล์ดิบสูงสุด 5MB
        ]);

        $media = $this->checklistService->attachPhoto(
            $id,
            $itemId,
            $request->file('photo'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'อัปโหลดและบีบอัดรูปภาพเป็น WebP สำเร็จ',
            'media' => $media,
        ]);
    }

    /**
     * ลบรูปภาพที่แนบในข้อตรวจเช็ค
     */
    public function deletePhoto(Request $request, int $id, int $itemId, int $mediaId): JsonResponse
    {
        $this->checklistService->deletePhoto($id, $itemId, $mediaId, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'ลบรูปภาพเรียบร้อยแล้ว',
        ]);
    }

    /**
     * กดส่งผลการตรวจเช็คลิสต์
     * (§5.6: POST /api/v1/checklists/{id}/submit)
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $submission = $this->checklistService->submit($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'ส่งผลการตรวจเช็คลิสต์เรียบร้อยแล้ว',
            'submission' => $submission,
        ]);
    }
}
