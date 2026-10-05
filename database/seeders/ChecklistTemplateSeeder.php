<?php

namespace Database\Seeders;

use App\Enums\ChecklistType;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use Illuminate\Database\Seeder;

class ChecklistTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * (§10 แม่แบบเช็คลิสต์: เปิดร้าน, ปิดร้าน, ความสะอาด)
     */
    public function run(): void
    {
        $templates = [
            [
                'type' => ChecklistType::OPENING,
                'name' => 'เช็คลิสต์เปิดร้าน',
                'items' => [
                    ['label' => 'เปิดไฟ ป้ายไฟ และเปิดเครื่องปรับอากาศ', 'require_photo' => false],
                    ['label' => 'ตรวจความพร้อมและถ่ายรูปหน้าร้าน', 'require_photo' => true],
                    ['label' => 'พนักงานแต่งกายเรียบร้อย พร้อมให้บริการ', 'require_photo' => false],
                ],
            ],
            [
                'type' => ChecklistType::CLOSING,
                'name' => 'เช็คลิสต์ปิดร้าน',
                'items' => [
                    ['label' => 'นำขยะไปทิ้ง และถังขยะเรียบร้อย', 'require_photo' => true],
                    ['label' => 'ปิดไฟ ปิดเครื่องปรับอากาศ ถอดปลั๊ก และล็อกประตูร้าน', 'require_photo' => false],
                    ['label' => 'ตรวจสอบความปลอดภัยและความเรียบร้อยทั่วไปก่อนออกจากร้าน', 'require_photo' => false],
                ],
            ],
            [
                'type' => ChecklistType::CLEANLINESS,
                'name' => 'เช็คลิสต์ความสะอาดประจำวัน',
                'items' => [
                    ['label' => 'กวาดและถูพื้นร้านสะอาดเรียบร้อย', 'require_photo' => true],
                    ['label' => 'เช็ดกระจกหน้าร้าน กระจกห้องลอง และตู้สินค้า', 'require_photo' => false],
                    ['label' => 'จัดชุดแขวนเรียงตามไซส์และแบบสินค้าอย่างเป็นระเบียบ', 'require_photo' => false],
                    ['label' => 'ตรวจสอบป้ายราคาสินค้าครบถ้วนถูกต้อง', 'require_photo' => false],
                    ['label' => 'ตรวจเช็คและทำความสะอาดห้องลองชุด', 'require_photo' => true],
                ],
            ],
        ];

        foreach ($templates as $tpl) {
            $template = ChecklistTemplate::updateOrCreate(
                ['type' => $tpl['type']],
                [
                    'name' => $tpl['name'],
                    'is_active' => true,
                ]
            );

            // Sync items
            $template->items()->delete();
            foreach ($tpl['items'] as $index => $itemData) {
                ChecklistTemplateItem::create([
                    'template_id' => $template->id,
                    'label' => $itemData['label'],
                    'require_photo' => $itemData['require_photo'],
                    'sort' => $index + 1,
                ]);
            }
        }
    }
}
