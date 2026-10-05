<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * (§1 สาขา พิกัด และเวลาทำการ)
     */
    public function run(): void
    {
        $branches = [
            [
                'code' => 'KKC',
                'name' => 'ขอนแก่น (ร้านหลัก)',
                'address' => 'ขอนแก่น',
                'open_time' => '09:00:00',
                'close_time' => '18:00:00',
                'closed_weekdays' => null, // เปิดทุกวัน
                'lat' => 16.4390693,
                'lng' => 102.8277712,
                'geofence_radius_m' => 50,
                'geofence_enabled' => true,
                'grace_minutes' => 10,
                'is_active' => true,
            ],
            [
                'code' => 'NMA',
                'name' => 'โคราช',
                'address' => 'นครราชสีมา',
                'open_time' => '09:00:00',
                'close_time' => '18:30:00',
                'closed_weekdays' => null, // เปิดทุกวัน
                'lat' => 14.9887613,
                'lng' => 102.1046383,
                'geofence_radius_m' => 50,
                'geofence_enabled' => true,
                'grace_minutes' => 10,
                'is_active' => true,
            ],
            [
                'code' => 'UBP',
                'name' => 'อุบลราชธานี',
                'address' => 'อุบลราชธานี',
                'open_time' => '09:00:00',
                'close_time' => '18:30:00',
                'closed_weekdays' => null, // เปิดทุกวัน
                'lat' => 15.2357186,
                'lng' => 104.8677354,
                'geofence_radius_m' => 50,
                'geofence_enabled' => true,
                'grace_minutes' => 10,
                'is_active' => true,
            ],
            [
                'code' => 'CNX1',
                'name' => 'เชียงใหม่ (1) นครพิงค์',
                'address' => 'เชียงใหม่ นครพิงค์',
                'open_time' => '08:30:00',
                'close_time' => '18:30:00',
                'closed_weekdays' => [0], // หยุดวันอาทิตย์ (0=Sunday)
                'lat' => 18.8536451,
                'lng' => 98.9686832,
                'geofence_radius_m' => 50,
                'geofence_enabled' => true,
                'grace_minutes' => 10,
                'is_active' => true,
            ],
            [
                'code' => 'CNX2',
                'name' => 'เชียงใหม่ (2)',
                'address' => 'เชียงใหม่ สาขา 2 (กำลังก่อสร้าง)',
                'open_time' => '08:30:00',
                'close_time' => '18:30:00',
                'closed_weekdays' => [0], // หยุดวันอาทิตย์
                'lat' => null, // ยังไม่มีพิกัด
                'lng' => null,
                'geofence_radius_m' => 50,
                'geofence_enabled' => true,
                'grace_minutes' => 10,
                'is_active' => false, // สาขายังไม่เสร็จ
            ],
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(
                ['code' => $branch['code']],
                $branch
            );
        }
    }
}
