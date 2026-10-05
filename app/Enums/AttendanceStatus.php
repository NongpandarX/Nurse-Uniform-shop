<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case ON_TIME = 'on_time';
    case LATE = 'late';
    case EARLY_LEAVE = 'early_leave';
    case ABSENT = 'absent';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::ON_TIME => 'ตรงเวลา',
            self::LATE => 'สาย',
            self::EARLY_LEAVE => 'ออกก่อนเวลา',
            self::ABSENT => 'ขาดงาน',
            self::MANUAL => 'บันทึกด้วยตนเอง/แก้ไขโดยแอดมิน',
        };
    }
}
