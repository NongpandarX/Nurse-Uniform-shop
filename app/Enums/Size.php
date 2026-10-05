<?php

namespace App\Enums;

enum Size: string
{
    case SSS = 'SSS';
    case SS = 'SS';
    case S = 'S';
    case M = 'M';
    case L = 'L';
    case XL = 'XL';
    case XXL = 'XXL';
    case THREE_XL = '3XL';
    case FOUR_XL = '4XL';
    case FIVE_XL = '5XL';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
