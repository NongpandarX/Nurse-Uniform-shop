<?php

namespace App\Enums;

enum ProductCategory: string
{
    case TOP = 'top';
    case PANTS = 'pants';
    case SKIRT = 'skirt';
    case ACCESSORY = 'accessory';
    case HAT = 'hat';

    public function label(): string
    {
        return match ($this) {
            self::TOP => 'เสื้อพยาบาล',
            self::PANTS => 'กางเกง',
            self::SKIRT => 'กระโปรง',
            self::ACCESSORY => 'อุปกรณ์เสริม/เข็ม/กระดุม',
            self::HAT => 'หมวก',
        };
    }
}
