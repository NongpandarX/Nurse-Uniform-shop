<?php

namespace Database\Seeders;

use App\Enums\ProductCategory;
use App\Enums\Size;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * (§10 Master Data สินค้า ถอดจากใบสั่งชุด)
     */
    public function run(): void
    {
        $allSizes = Size::cases();

        // 1-21: เสื้อพยาบาล
        $topProducts = [
            1 => 'กรุงเทพ ยาว',
            2 => 'กรุงเทพ สั้น',
            3 => 'บัวมน',
            4 => 'เทเลอมน',
            5 => 'ปีกนก',
            6 => 'เทเลอแหลม สั้น',
            7 => 'เทเลอแหลม ยาว',
            8 => 'บัวป้าย สั้น วัฒนา',
            9 => 'บัวป้าย ยาว วัฒนา',
            10 => 'จีนแยก',
            11 => 'คอปีนสั้น',
            12 => 'คอปีน ยาว',
            13 => 'ลานนา',
            14 => 'จีนป้าย สั้น',
            15 => 'จีนป้าย ยาว',
            16 => 'จีนตรง สั้น',
            17 => 'จีนตรง ยาว',
            18 => 'ริ้วฟ้าคอปก',
            19 => 'กวนู่คอปก',
            20 => 'พิธีการ สั้น',
            21 => 'พิธีการ ยาว',
        ];

        foreach ($topProducts as $index => $name) {
            $code = sprintf('TOP-%03d', $index);
            $product = Product::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'category' => ProductCategory::TOP,
                    'has_sizes' => true,
                    'sort' => $index,
                    'is_active' => true,
                ]
            );

            // Generate variants for each size
            foreach ($allSizes as $sizeIndex => $size) {
                $sku = sprintf('%s-%s', $code, $size->value);
                ProductVariant::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'product_id' => $product->id,
                        'size' => $size,
                        'option' => null,
                        'sort' => $sizeIndex,
                        'is_active' => true,
                    ]
                );
            }
        }

        // 22-23: กระโปรง
        $skirtProducts = [
            22 => 'กระโปรง ทรงเอ',
            23 => 'กระโปรง ทรงตรง',
        ];

        foreach ($skirtProducts as $index => $name) {
            $code = sprintf('SKIRT-%03d', $index);
            $product = Product::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'category' => ProductCategory::SKIRT,
                    'has_sizes' => true,
                    'sort' => $index,
                    'is_active' => true,
                ]
            );

            foreach ($allSizes as $sizeIndex => $size) {
                $sku = sprintf('%s-%s', $code, $size->value);
                ProductVariant::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'product_id' => $product->id,
                        'size' => $size,
                        'option' => null,
                        'sort' => $sizeIndex,
                        'is_active' => true,
                    ]
                );
            }
        }

        // 24: กางเกง (มี option 'normal' และ 'plus')
        $pantsProduct = Product::updateOrCreate(
            ['code' => 'PANTS-024'],
            [
                'name' => 'กางเกง',
                'category' => ProductCategory::PANTS,
                'has_sizes' => true,
                'sort' => 24,
                'is_active' => true,
            ]
        );

        $pantsOptions = ['normal', 'plus'];
        $sortOrder = 0;
        foreach ($pantsOptions as $option) {
            foreach ($allSizes as $size) {
                $sku = sprintf('PANTS-024-%s-%s', strtoupper($option), $size->value);
                ProductVariant::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'product_id' => $pantsProduct->id,
                        'size' => $size,
                        'option' => $option,
                        'sort' => $sortOrder++,
                        'is_active' => true,
                    ]
                );
            }
        }

        // อุปกรณ์เสริม (ไม่มีไซส์ has_sizes = false)
        $accessories = [
            ['code' => 'ACC-001', 'name' => 'เข็มขัด', 'category' => ProductCategory::ACCESSORY],
            ['code' => 'ACC-002', 'name' => 'กระดุมแขนพิธีการ', 'category' => ProductCategory::ACCESSORY],
            ['code' => 'ACC-003', 'name' => 'กระดุมกระทรวง', 'category' => ProductCategory::ACCESSORY],
            ['code' => 'ACC-004', 'name' => 'เข็มพยาบาล', 'category' => ProductCategory::ACCESSORY],
            ['code' => 'ACC-005', 'name' => 'เข็มผู้ช่วย', 'category' => ProductCategory::ACCESSORY],
            ['code' => 'HAT-001', 'name' => 'หมวกผู้ช่วย', 'category' => ProductCategory::HAT],
            ['code' => 'HAT-002', 'name' => 'หมวกพยาบาล', 'category' => ProductCategory::HAT],
        ];

        $accSort = 100;
        foreach ($accessories as $item) {
            $product = Product::updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'has_sizes' => false,
                    'sort' => $accSort++,
                    'is_active' => true,
                ]
            );

            // 1 variant for each accessory with no size
            ProductVariant::updateOrCreate(
                ['sku' => $item['code']],
                [
                    'product_id' => $product->id,
                    'size' => null,
                    'option' => null,
                    'sort' => 0,
                    'is_active' => true,
                ]
            );
        }
    }
}
