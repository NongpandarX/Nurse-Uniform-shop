<?php

namespace Database\Seeders;

use App\Enums\DevicePlatform;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use App\Models\UserDevice;
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
        $admin = User::updateOrCreate(
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

        // เครื่องทดสอบของ Admin (อนุมัติอัตโนมัติ)
        UserDevice::updateOrCreate(
            ['user_id' => $admin->id, 'device_uid' => 'DEVICE-ADMIN-001'],
            [
                'platform' => DevicePlatform::ANDROID,
                'fcm_token' => 'fcm-admin-token',
                'is_approved' => true,
                'last_seen_at' => now(),
            ]
        );

        // 2. พนักงานประจำสาขา (1 สาขา มีพนักงานประจำ 1 คน ตามข้อกำหนด §1 และ §2)
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
                'name' => 'พนักงานประจำ เชียงใหม่ 1 (นครพิงค์)',
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
                $employee = User::updateOrCreate(
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

                // สร้างเครื่องทดสอบที่ผ่านการอนุมัติแล้วให้พนักงานประจำแต่ละสาขา
                UserDevice::updateOrCreate(
                    ['user_id' => $employee->id, 'device_uid' => 'DEVICE-' . $data['employee_code']],
                    [
                        'platform' => DevicePlatform::ANDROID,
                        'fcm_token' => 'fcm-' . strtolower($data['employee_code']) . '-token',
                        'is_approved' => true,
                        'last_seen_at' => now(),
                    ]
                );
            }
        }
    }
}
