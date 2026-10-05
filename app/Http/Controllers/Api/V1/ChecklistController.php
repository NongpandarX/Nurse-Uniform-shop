<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function __construct(
        protected ChecklistService $checklistService
    ) {}

    /**
     * ดึงรายการเช็คลิสต์ประจำวัน
     */
    public function todayChecklists(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id', $request->user()->branch_id);
        $submissions = $this->checklistService->getTodaySubmissions($branchId, $request->user());

        return response()->json([
            'checklists' => $submissions,
        ]);
    }

    /**
     * บันทึกคำตอบเช็คลิสต์ทีละข้อ
     */
    public function updateItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $request->validate([
            'is_ok' => ['required', 'boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $item = $this->checklistService->updateSubmissionItem($id, $itemId, $request->is_ok, $request->note);

        return response()->json([
            'message' => 'บันทึกรายการสำเร็จ',
            'item' => $item,
        ]);
    }

    /**
     * อัปโหลดรูปภาพเช็คลิสต์ (บีบอัดรูปภาพผ่าน ImageService)
     */
    public function uploadPhoto(Request $request, int $id, int $itemId): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic', 'max:5120'], // max 5MB
        ]);

        $media = $this->checklistService->attachPhoto($id, $itemId, $request->file('photo'), $request->user());

        return response()->json([
            'message' => 'อัปโหลดรูปภาพสำเร็จ',
            'media' => $media,
        ]);
    }

    /**
     * กดส่งเช็คลิสต์
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $submission = $this->checklistService->submit($id, $request->user());

        return response()->json([
            'message' => 'ส่งผลการตรวจเช็คลิสต์เรียบร้อยแล้ว',
            'submission' => $submission,
        ]);
    }
}
