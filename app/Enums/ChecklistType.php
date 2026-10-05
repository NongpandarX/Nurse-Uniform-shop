<?php

namespace App\Enums;

enum ChecklistType: string
{
    case OPENING = 'opening';
    case CLOSING = 'closing';
    case CLEANLINESS = 'cleanliness';

    public function label(): string
    {
        return match ($this) {
            self::OPENING => 'เปิดร้าน',
            self::CLOSING => 'ปิดร้าน',
            self::CLEANLINESS => 'ความสะอาด',
        };
    }
}
