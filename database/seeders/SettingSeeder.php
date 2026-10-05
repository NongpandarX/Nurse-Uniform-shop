<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * (§6.2 ค่าตั้งค่าระบบ settings)
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'max_accuracy_m',
                'value' => '40',
                'type' => 'int',
                'description' => 'ค่าความคลาดเคลื่อน GPS สูงสุดที่ยอมรับได้ (เมตร)',
            ],
            [
                'key' => 'geofence_required_for_count',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'ต้องอยู่ในรัศมีร้านเมื่อส่งนับสต็อกหรือไม่',
            ],
            [
                'key' => 'geofence_required_for_checklist',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'ต้องอยู่ในรัศมีร้านเมื่อส่งเช็คลิสต์หรือไม่',
            ],
            [
                'key' => 'geofence_required_for_closing',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'ต้องอยู่ในรัศมีร้านเมื่อแจ้งปิดร้านหรือไม่',
            ],
            [
                'key' => 'abnormal_drop_qty',
                'value' => '10',
                'type' => 'int',
                'description' => 'เกณฑ์จำนวนชิ้นที่ลดลงผิดปกติ (ชิ้น)',
            ],
            [
                'key' => 'abnormal_drop_percent',
                'value' => '50',
                'type' => 'int',
                'description' => 'เกณฑ์เปอร์เซ็นต์ที่ลดลงผิดปกติ (%)',
            ],
            [
                'key' => 'media_retention_days',
                'value' => '60',
                'type' => 'int',
                'description' => 'จำนวนวันที่เก็บรูปเช็คลิสต์ก่อนถูกลบอัตโนมัติ (วัน)',
            ],
            [
                'key' => 'default_min_qty',
                'value' => '7',
                'type' => 'int',
                'description' => 'ค่าสต็อกขั้นต่ำเริ่มต้นสำหรับชุด/กางเกง/กระโปรง',
            ],
            [
                'key' => 'default_min_qty_accessory',
                'value' => '5',
                'type' => 'int',
                'description' => 'ค่าสต็อกขั้นต่ำเริ่มต้นสำหรับอุปกรณ์เสริม/หมวก',
            ],
        ];

        foreach ($settings as $item) {
            Setting::updateOrCreate(
                ['key' => $item['key']],
                $item
            );
        }
    }
}
