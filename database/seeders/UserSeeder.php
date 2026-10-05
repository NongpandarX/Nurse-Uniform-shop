<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * (§2 Roles และสิทธิ์: admin, employee ผูก 1 สาขา)
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('password123');

        // 1. Admin (ผู้ดูแลระบบกลาง ไม่ผูกสาขา branch_id = null)
        User::updateOrCreate(
            ['employee_code' => 'ADMIN-001'],
            [
                'name' => 'ผู้ดูแลระบบ (Admin)',
                'phone' => '0800000001',
                'password' => $defaultPassword,
                'role' => Role::ADMIN,
                'branch_id' => null,
                'is_active' => true,
            ]
        );

        // 2. พนักงานประจำสาขา (1 สาขา มีพนักงานประจำ 1 คน)
        $branchUsers = [
            'KKC' => [
                'employee_code' => 'KKC-001',
                'name' => 'พนักงานประจำ ขอนแก่น',
                'phone' => '0810000001',
            ],
            'NMA' => [
                'employee_code' => 'NMA-001',
                'name' => 'พนักงานประจำ โคราช',
                'phone' => '0820000002',
            ],
            'UBP' => [
                'employee_code' => 'UBP-001',
                'name' => 'พนักงานประจำ อุบลราชธานี',
                'phone' => '0830000003',
            ],
            'CNX1' => [
                'employee_code' => 'CNX-001',
                'name' => 'พนักงานประจำ เชียงใหม่ 1',
                'phone' => '0840000004',
            ],
            'CNX2' => [
                'employee_code' => 'CNX-002',
                'name' => 'พนักงานประจำ เชียงใหม่ 2',
                'phone' => '0850000005',
            ],
        ];

        foreach ($branchUsers as $branchCode => $data) {
            $branch = Branch::where('code', $branchCode)->first();
            if ($branch) {
                User::updateOrCreate(
                    ['employee_code' => $data['employee_code']],
                    [
                        'name' => $data['name'],
                        'phone' => $data['phone'],
                        'password' => $defaultPassword,
                        'role' => Role::EMPLOYEE,
                        'branch_id' => $branch->id,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
