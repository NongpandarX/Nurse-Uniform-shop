<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\AttendanceAttempt;
use App\Models\Branch;
use App\Models\Setting;
use App\Models\User;
use App\Services\GeofenceService;
use App\Support\Geo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeofenceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GeofenceService $geofenceService;
    protected Branch $branch;
    protected User $employee;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->geofenceService = new GeofenceService();

        // สาขาขอนแก่น พิกัด 16.4390693, 102.8277712
        $this->branch = Branch::create([
            'code' => 'KKC',
            'name' => 'ขอนแก่น (ร้านหลัก)',
            'lat' => 16.4390693,
            'lng' => 102.8277712,
            'geofence_radius_m' => 50,
            'geofence_enabled' => true,
            'open_time' => '09:00:00',
            'close_time' => '18:00:00',
            'is_active' => true,
        ]);

        $this->employee = User::create([
            'employee_code' => 'KKC-001',
            'name' => 'พนักงานประจำ ขอนแก่น',
            'phone' => '0810000001',
            'password' => bcrypt('password123'),
            'role' => Role::EMPLOYEE,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'employee_code' => 'ADMIN-001',
            'name' => 'Admin กลาง',
            'phone' => '0800000001',
            'password' => bcrypt('password123'),
            'role' => Role::ADMIN,
            'branch_id' => null,
            'is_active' => true,
        ]);

        Setting::set('max_accuracy_m', 40);
    }

    /**
     * 1. ตรวจสอบว่าพิกัดที่อยู่ในรัศมี (เช่น ตรงจุด หรือห่างประมาณ 20-30 ม.) จะผ่านเกณฑ์
     */
    public function test_allows_check_in_within_geofence_radius(): void
    {
        // พิกัดห่างประมาณ 20-30 เมตร จากร้าน
        $lat = 16.4392000;
        $lng = 102.8277712;
        $accuracy = 10.0;

        $distance = Geo::distanceMeters($lat, $lng, (float)$this->branch->lat, (float)$this->branch->lng);
        $this->assertLessThanOrEqual(50, $distance);

        $result = $this->geofenceService->validate(
            $this->employee,
            $this->branch,
            $lat,
            $lng,
            $accuracy,
            false,
            'DEVICE-001',
            '127.0.0.1'
        );

        $this->assertTrue($result['allowed']);
        $this->assertNull($result['error_code']);
        $this->assertNotNull($result['distance_m']);
    }

    /**
     * 2. ตรวจสอบว่าพิกัดที่อยู่นอกรัศมี 50 ม. จะถูกปฏิเสธและบันทึก log attempt
     */
    public function test_rejects_check_in_outside_geofence_radius_and_logs_attempt(): void
    {
        // พิกัดห่างประมาณ 150 เมตร
        $lat = 16.4405000;
        $lng = 102.8277712;
        $accuracy = 10.0;

        $distance = Geo::distanceMeters($lat, $lng, (float)$this->branch->lat, (float)$this->branch->lng);
        $this->assertGreaterThan(50, $distance);

        $result = $this->geofenceService->validate(
            $this->employee,
            $this->branch,
            $lat,
            $lng,
            $accuracy,
            false,
            'DEVICE-001',
            '127.0.0.1'
        );

        $this->assertFalse($result['allowed']);
        $this->assertEquals('outside_geofence', $result['error_code']);
        $this->assertStringContainsString('อยู่นอกพื้นที่ร้าน', $result['message']);

        // ตรวจสอบว่ามีบันทึก attendance_attempts
        $this->assertDatabaseHas('attendance_attempts', [
            'user_id' => $this->employee->id,
            'branch_id' => $this->branch->id,
            'device_uid' => 'DEVICE-001',
        ]);
    }

    /**
     * 3. ตรวจสอบว่าเมื่อสัญญาณ GPS ไม่แม่นยำ (accuracy > max_accuracy_m) จะถูกปฏิเสธ
     */
    public function test_rejects_when_gps_accuracy_is_too_low(): void
    {
        $lat = 16.4390693;
        $lng = 102.8277712;
        $accuracy = 65.0; // เกินค่า 40 ม.

        $result = $this->geofenceService->validate(
            $this->employee,
            $this->branch,
            $lat,
            $lng,
            $accuracy,
            false
        );

        $this->assertFalse($result['allowed']);
        $this->assertEquals('inaccurate_gps', $result['error_code']);
        $this->assertStringContainsString('สัญญาณ GPS ไม่แม่นยำ', $result['message']);
    }

    /**
     * 4. ตรวจสอบว่าตรวจจับ Mock Location ได้และปฏิเสธทันที
     */
    public function test_rejects_mock_location(): void
    {
        $lat = 16.4390693;
        $lng = 102.8277712;
        $accuracy = 5.0;

        $result = $this->geofenceService->validate(
            $this->employee,
            $this->branch,
            $lat,
            $lng,
            $accuracy,
            true // is_mock = true
        );

        $this->assertFalse($result['allowed']);
        $this->assertEquals('mock_location', $result['error_code']);
        $this->assertStringContainsString('Mock Location', $result['message']);
    }

    /**
     * 5. สาขาที่ไม่มีพิกัด (lat/lng = null) ไม่อนุญาตให้เช็คชื่อ
     */
    public function test_rejects_branch_without_coordinates(): void
    {
        $cnx2 = Branch::create([
            'code' => 'CNX2',
            'name' => 'เชียงใหม่ (2)',
            'lat' => null,
            'lng' => null,
            'is_active' => false,
        ]);

        $result = $this->geofenceService->validate(
            $this->employee,
            $cnx2,
            18.8500000,
            98.9600000,
            10.0
        );

        $this->assertFalse($result['allowed']);
        $this->assertEquals('no_coordinates', $result['error_code']);
    }

    /**
     * 6. Admin สามารถข้ามเงื่อนไข Geofence ได้ทุกที่
     */
    public function test_admin_bypasses_geofence(): void
    {
        // Admin อยู่ห่างหลายกิโลเมตร
        $result = $this->geofenceService->validate(
            $this->admin,
            $this->branch,
            13.7563309,
            100.5017651,
            500.0,
            false
        );

        $this->assertTrue($result['allowed']);
        $this->assertEquals(0.0, $result['distance_m']);
    }
}

