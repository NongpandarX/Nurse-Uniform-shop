<?php

namespace App\Console\Commands;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AttendanceMarkAbsent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:mark-absent {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '00:10 ตีขาดงานเมื่อสาขาเปิดแต่พนักงานไม่มีเช็คชื่อ';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $targetDate = $this->argument('date') ?: now()->subDay()->toDateString();
        $dayOfWeek = Carbon::parse($targetDate)->dayOfWeek; // 0=Sunday, 6=Saturday

        $activeBranches = Branch::where('is_active', true)->get();
        $markedCount = 0;

        foreach ($activeBranches as $branch) {
            $closedDays = $branch->closed_weekdays ?? [];
            if (is_array($closedDays) && in_array($dayOfWeek, $closedDays, true)) {
                // สาขาหยุดทำการในวันนี้ ข้ามไป
                continue;
            }

            // พนักงานประจำสาขานี้
            $employees = User::where('branch_id', $branch->id)
                ->where('role', Role::EMPLOYEE)
                ->where('is_active', true)
                ->get();

            foreach ($employees as $employee) {
                $hasAttendance = Attendance::withoutGlobalScopes()
                    ->where('user_id', $employee->id)
                    ->where('work_date', $targetDate)
                    ->whereNotNull('check_in_at')
                    ->exists();

                if (!$hasAttendance) {
                    Attendance::withoutGlobalScopes()->updateOrCreate(
                        [
                            'user_id' => $employee->id,
                            'work_date' => $targetDate,
                        ],
                        [
                            'branch_id' => $branch->id,
                            'status' => AttendanceStatus::ABSENT,
                        ]
                    );
                    $markedCount++;
                }
            }
        }

        $this->info("บันทึกสถานะขาดงานสำหรับวันที่ {$targetDate} เรียบร้อยแล้ว ทั้งหมด {$markedCount} รายการ");
        return Command::SUCCESS;
    }
}

