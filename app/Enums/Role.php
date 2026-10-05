<?php

namespace App\Enums;

enum Role: string
{
    case ADMIN = 'admin';
    case EMPLOYEE = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'ผู้ดูแลระบบ (Admin)',
            self::EMPLOYEE => 'พนักงานประจำสาขา (Employee)',
        };
    }
}
