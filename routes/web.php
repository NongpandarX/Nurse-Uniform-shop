<?php

use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\ChecklistReviewController;
use App\Http\Controllers\Admin\ChecklistTemplateController;
use App\Http\Controllers\Admin\ClosingController as AdminClosingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController as AdminDeviceController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StockController as AdminStockController;
use App\Http\Controllers\Admin\StockCountReportController;
use App\Http\Controllers\Web\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — ร้านชุดพยาบาล มินลดา
|--------------------------------------------------------------------------
| §7.2 Admin (เว็บ Blade) — ทุกตัว auth + active.user + role:admin
*/

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

// Authentication
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Admin Panel Routes
Route::prefix('admin')->name('admin.')->middleware(['auth', 'active.user', 'role:admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // สาขา
    Route::resource('branches', BranchController::class);

    // พนักงาน
    Route::resource('employees', EmployeeController::class);
    Route::post('/employees/{id}/reset-password', [EmployeeController::class, 'resetPassword'])->name('employees.reset-password');
    Route::post('/employees/{id}/toggle-active', [EmployeeController::class, 'toggleActive'])->name('employees.toggle-active');

    // อุปกรณ์เครื่องของพนักงาน
    Route::get('/devices', [AdminDeviceController::class, 'index'])->name('devices.index');
    Route::post('/devices/{id}/approve', [AdminDeviceController::class, 'approve'])->name('devices.approve');
    Route::post('/devices/{id}/revoke', [AdminDeviceController::class, 'revoke'])->name('devices.revoke');

    // สินค้า & ไซส์
    Route::resource('products', ProductController::class);
    Route::post('/products/min-qty', [ProductController::class, 'updateMinQty'])->name('products.min-qty');

    // สต็อกสินค้า & Ledger
    Route::get('/stock', [AdminStockController::class, 'index'])->name('stock.index');
    Route::get('/stock/branch/{branchId}', [AdminStockController::class, 'show'])->name('stock.show');
    Route::get('/stock/movements', [AdminStockController::class, 'movements'])->name('stock.movements');
    Route::post('/stock/adjust', [AdminStockController::class, 'adjust'])->name('stock.adjust');

    // รายงานการนับสต็อก
    Route::get('/stock-counts', [StockCountReportController::class, 'index'])->name('stock-counts.index');
    Route::get('/stock-counts/{id}', [StockCountReportController::class, 'show'])->name('stock-counts.show');
    Route::get('/stock-counts/export', [StockCountReportController::class, 'export'])->name('stock-counts.export');

    // บันทึกเวลาเข้างาน
    Route::get('/attendances', [AdminAttendanceController::class, 'index'])->name('attendances.index');
    Route::get('/attendances/attempts', [AdminAttendanceController::class, 'attempts'])->name('attendances.attempts');
    Route::get('/attendances/monthly', [AdminAttendanceController::class, 'monthly'])->name('attendances.monthly');
    Route::get('/attendances/{id}/edit', [AdminAttendanceController::class, 'edit'])->name('attendances.edit');
    Route::put('/attendances/{id}', [AdminAttendanceController::class, 'update'])->name('attendances.update');

    // แม่แบบเช็คลิสต์ & รีวิว
    Route::resource('checklist-templates', ChecklistTemplateController::class);
    Route::get('/checklist-reviews', [ChecklistReviewController::class, 'index'])->name('checklist-reviews.index');
    Route::get('/checklist-reviews/{id}', [ChecklistReviewController::class, 'show'])->name('checklist-reviews.show');
    Route::post('/checklist-reviews/{id}/approve', [ChecklistReviewController::class, 'approve'])->name('checklist-reviews.approve');
    Route::post('/checklist-reviews/{id}/reject', [ChecklistReviewController::class, 'reject'])->name('checklist-reviews.reject');
    Route::post('/checklist-reviews/media/{mediaId}/pin', [ChecklistReviewController::class, 'pinMedia'])->name('checklist-reviews.pin-media');

    // รายการแจ้งปิดร้าน
    Route::get('/closings', [AdminClosingController::class, 'index'])->name('closings.index');
    Route::get('/closings/{id}', [AdminClosingController::class, 'show'])->name('closings.show');

    // รายงาน & ส่งออก Excel
    Route::get('/reports/daily', [ReportController::class, 'daily'])->name('reports.daily');
    Route::get('/reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

    // ตั้งค่าระบบ
    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
});
