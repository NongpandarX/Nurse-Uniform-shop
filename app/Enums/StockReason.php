<?php

namespace App\Enums;

enum StockReason: string
{
    case INITIAL = 'initial';
    case COUNT = 'count';
    case RECEIVE = 'receive';
    case EXCHANGE = 'exchange';
    case DAMAGED = 'damaged';
    case SOLD = 'sold';
    case TRANSFER_IN = 'transfer_in';
    case TRANSFER_OUT = 'transfer_out';
    case CORRECTION = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::INITIAL => 'ยอดยกมาเริ่มต้น',
            self::COUNT => 'นับสต็อกรายวัน',
            self::RECEIVE => 'รับของเข้า',
            self::EXCHANGE => 'เปลี่ยนสินค้า',
            self::DAMAGED => 'สินค้าชำรุด/เสีย',
            self::SOLD => 'ขายออก',
            self::TRANSFER_IN => 'โอนเข้าจากสาขาอื่น',
            self::TRANSFER_OUT => 'โอนออกไปสาขาอื่น',
            self::CORRECTION => 'ปรับปรุงยอดแก้ไข',
        };
    }
}
