<?php

namespace App\Enums;

enum ChecklistStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'ฉบับร่าง',
            self::SUBMITTED => 'ส่งแล้ว/รอตรวจสอบ',
            self::APPROVED => 'อนุมัติแล้ว',
            self::REJECTED => 'ส่งกลับแก้ไข',
        };
    }
}
