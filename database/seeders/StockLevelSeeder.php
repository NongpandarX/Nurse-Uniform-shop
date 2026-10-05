<?php

namespace Database\Seeders;

use App\Enums\ProductCategory;
use App\Enums\StockReason;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

class StockLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * (§10 ค่าเริ่มต้น stock_levels ทุกสาขา × ทุก variant + movement 'initial' สาขาขอนแก่น)
     */
    public function run(): void
    {
        $branches = Branch::all();
        $variants = ProductVariant::with('product')->get();
        $kkcBranch = Branch::where('code', 'KKC')->first();
        $adminUser = User::where('role', \App\Enums\Role::ADMIN)->first();

        // ตัวเลขสต็อกเริ่มต้นขอนแก่น (ถอดจากตารางใน §10)
        $kkcInitialMap = [
            'TOP-001-S' => 5,
            'TOP-001-XL' => 3,
            'TOP-002-S' => 5,
            'TOP-002-M' => 5,
            'TOP-003-M' => 5,
            'TOP-005-XL' => 5,
            'TOP-006-SS' => 15,
            'TOP-006-S' => 20,
            'TOP-006-M' => 20,
            'TOP-006-L' => 15,
            'TOP-006-XL' => 10,
            'TOP-006-XXL' => 3,
            'TOP-006-3XL' => 4,
            'TOP-006-4XL' => 4,
            'TOP-007-S' => 5,
            'TOP-008-M' => 5,
            'TOP-009-XL' => 4,
            'TOP-010-M' => 4,
            'TOP-012-S' => 3,
            'TOP-013-M' => 4,
            'TOP-014-XL' => 5,
            'TOP-015-SS' => 5,
            'TOP-020-S' => 8,
            'TOP-020-M' => 8,
            'TOP-020-L' => 8,
            'TOP-020-4XL' => 3,
            'TOP-021-S' => 5,
            'TOP-021-M' => 5,
            'TOP-021-L' => 3,
            'SKIRT-022-M' => 4,
            'SKIRT-022-L' => 5,
            'SKIRT-022-XL' => 5,
            'SKIRT-022-XXL' => 5,
            'SKIRT-023-SSS' => 5,
            'SKIRT-023-SS' => 5,
            'SKIRT-023-S' => 10,
            'SKIRT-023-M' => 5,
            'SKIRT-023-L' => 5,
            'SKIRT-023-XL' => 5,
            'PANTS-024-NORMAL-SSS' => 20,
            'PANTS-024-NORMAL-SS' => 20,
            'PANTS-024-NORMAL-S' => 20,
            'PANTS-024-NORMAL-M' => 20,
            'PANTS-024-NORMAL-L' => 20,
            'PANTS-024-NORMAL-XL' => 20,
            'PANTS-024-NORMAL-XXL' => 5,
            'PANTS-024-NORMAL-3XL' => 5,
            'PANTS-024-NORMAL-5XL' => 5,
            'PANTS-024-PLUS-S' => 20,
            'PANTS-024-PLUS-M' => 20,
            'PANTS-024-PLUS-L' => 20,
            'PANTS-024-PLUS-XL' => 15,
            'PANTS-024-PLUS-XXL' => 5,
        ];

        foreach ($branches as $branch) {
            foreach ($variants as $variant) {
                $isAccessory = in_array($variant->product->category, [ProductCategory::ACCESSORY, ProductCategory::HAT]);
                $minQty = $isAccessory ? 5 : 7;

                $initialQty = 0;
                if ($kkcBranch && $branch->id === $kkcBranch->id && isset($kkcInitialMap[$variant->sku])) {
                    $initialQty = $kkcInitialMap[$variant->sku];
                }

                $stockLevel = StockLevel::withoutGlobalScopes()->updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'variant_id' => $variant->id,
                    ],
                    [
                        'qty' => $initialQty,
                        'min_qty' => $minQty,
                    ]
                );

                // หากมีสต็อกเริ่มต้นที่ขอนแก่น ให้บันทึก StockMovement (reason: initial)
                if ($initialQty > 0) {
                    StockMovement::withoutGlobalScopes()->firstOrCreate(
                        [
                            'branch_id' => $branch->id,
                            'variant_id' => $variant->id,
                            'reason' => StockReason::INITIAL,
                        ],
                        [
                            'qty_before' => 0,
                            'qty_change' => $initialQty,
                            'qty_after' => $initialQty,
                            'user_id' => $adminUser?->id,
                            'note' => 'ยอดยกมาเริ่มต้นตามใบสั่งชุด',
                            'created_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
