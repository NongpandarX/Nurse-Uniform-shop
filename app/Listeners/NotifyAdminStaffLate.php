<?php

namespace App\Listeners;

use App\Events\StaffLate;
use App\Services\PushService;

class NotifyAdminStaffLate
{
    public function __construct(
        protected PushService $pushService
    ) {}

    /**
     * ดักจับ Event เมื่อพนักงานเข้างานสาย
     * ตัวอย่างข้อความ (§5.7): 🕒 อุบล: มาสาย 15 นาที (ไม่หักเงิน)
     */
    public function handle(StaffLate $event): void
    {
        $attendance = $event->attendance;
        $attendance->loadMissing(['branch', 'user']);

        $branchName = $attendance->branch?->name ?? 'ไม่ระบุสาขา';
        $staffName = $attendance->user?->name ?? 'พนักงาน';
        $lateMinutes = $attendance->late_minutes;
        $timeStr = $attendance->check_in_at ? $attendance->check_in_at->format('H:i') : '-';

        $title = "🕒 {$branchName}: มาสาย {$lateMinutes} นาที (ไม่หักเงิน)";
        $body = "พนักงาน {$staffName} สาขา {$branchName} เช็คชื่อเข้างานสาย {$lateMinutes} นาที (เวลา {$timeStr} น.)";

        $this->pushService->notifyAdmins(
            type: 'staff_late',
            title: $title,
            body: $body,
            data: [
                'type' => 'staff_late',
                'branch_id' => $attendance->branch_id,
                'user_id' => $attendance->user_id,
                'attendance_id' => $attendance->id,
                'late_minutes' => $lateMinutes,
            ]
        );
    }
}

