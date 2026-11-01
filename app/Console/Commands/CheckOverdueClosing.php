<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\DailyClosing;
use App\Models\User;
use App\Services\PushService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckOverdueClosing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'closing:check-overdue {--force : ข้ามการตรวจเวลาเพื่อทดสอบ}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ตรวจหาสาขาที่เลยเวลาปิดร้าน + 30 นาทีแล้วยังไม่แจ้งปิดร้าน และแจ้งเตือนพนักงานกับ admin (§5.5, §5.7)';

    /**
     * Execute the console command.
     */
    public function handle(PushService $pushService): int
    {
        $today = now()->toDateString();
        $dayOfWeek = now()->dayOfWeek;
        $isForced = (bool) $this->option('force');

        $activeBranches = Branch::where('is_active', true)->get();
        $overdueCount = 0;

        foreach ($activeBranches as $branch) {
            // 1. ตรวจสอบวันหยุดประจำสัปดาห์ของสาขา
            $closedDays = $branch->closed_weekdays ?? [];
            if (is_array($closedDays) && in_array($dayOfWeek, $closedDays, true)) {
                continue;
            }

            if (!$branch->close_time) {
                continue;
            }

            // 2. ตรวจสอบเวลาเลยปิดร้าน 30 นาที
            $closeTimeToday = Carbon::parse($today . ' ' . $branch->close_time);
            $overdueThreshold = $closeTimeToday->copy()->addMinutes(30);

            if (!$isForced && now()->lt($overdueThreshold)) {
                continue;
            }

            // 3. ตรวจสอบว่าสาขาได้แจ้งปิดร้านไปแล้วหรือยัง
            $isClosed = DailyClosing::withoutGlobalScopes()
                ->where('branch_id', $branch->id)
                ->whereDate('closing_date', $today)
                ->exists();

            if ($isClosed) {
                continue;
            }

            // 4. ป้องกันแจ้งเตือนซ้ำในวันเดียวกัน
            $alreadyAlerted = AppNotification::where('type', 'overdue_closing')
                ->whereDate('created_at', $today)
                ->where(function ($q) use ($branch) {
                    $q->where('data->branch_id', $branch->id)
                        ->orWhere('data->branch_id', (string) $branch->id);
                })
                ->exists();

            if ($alreadyAlerted && !$isForced) {
                continue;
            }

            // 5. ส่งแจ้งเตือนพนักงานประจำสาขา
            $employees = User::where('branch_id', $branch->id)
                ->where('is_active', true)
                ->get();

            $employeeTitle = "❗ สาขา {$branch->name} ยังไม่แจ้งปิดร้าน";
            $employeeBody = "เลยเวลาปิดร้านมาแล้ว 30 นาที ({$branch->close_time}) กรุณาตรวจสอบการนับสต็อก เช็คลิสต์ และแจ้งปิดร้านประจำวัน";

            foreach ($employees as $employee) {
                $pushService->sendToUser(
                    $employee,
                    'overdue_closing',
                    $employeeTitle,
                    $employeeBody,
                    [
                        'type' => 'overdue_closing',
                        'branch_id' => $branch->id,
                        'close_time' => $branch->close_time,
                    ]
                );
            }

            // 6. ส่งแจ้งเตือน Admin ทุกคน
            $adminTitle = "❗ สาขา {$branch->name} ยังไม่แจ้งปิดร้าน";
            $adminBody = "สาขา {$branch->name} เลยเวลาปิดร้านมาแล้ว 30 นาที ({$branch->close_time}) แต่ยังไม่มีการส่งข้อมูลปิดร้านประจำวัน";

            $pushService->notifyAdmins(
                'overdue_closing',
                $adminTitle,
                $adminBody,
                [
                    'type' => 'overdue_closing',
                    'branch_id' => $branch->id,
                    'close_time' => $branch->close_time,
                ]
            );

            $this->warn("สาขา {$branch->name} เลยเวลาปิดร้าน ยังไม่ได้แจ้งปิดร้าน — ส่งแจ้งเตือนเรียบร้อย");
            $overdueCount++;
        }

        if ($overdueCount === 0) {
            $this->info('ทุกสาขาแจ้งปิดร้านเรียบร้อยแล้ว หรือยังไม่ถึงเวลาเกณฑ์เตือน');
        } else {
            $this->info("แจ้งเตือนสาขาที่ยังไม่ปิดร้านทั้งหมด {$overdueCount} สาขา");
        }

        return Command::SUCCESS;
    }
}
