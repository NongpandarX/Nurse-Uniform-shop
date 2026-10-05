<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChecklistController;
use App\Http\Controllers\Api\V1\ClosingController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\StockCountController;
use App\Http\Controllers\Api\V1\StockUpdateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — ร้านชุดพยาบาล มินลดา
|--------------------------------------------------------------------------
| §7.1 Api\V1 (สำหรับแอปมือถือ ตอบ JSON ผ่าน API Resource)
*/

Route::prefix('v1')->group(function () {

    // Public Auth
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    // Protected Routes (auth:sanctum + active.user)
    Route::middleware(['auth:sanctum', 'active.user'])->group(function () {

        // Auth & Profile
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
        Route::get('/me', [MeController::class, 'profile']);
        Route::get('/me/today', [MeController::class, 'todayStatus']);

        // Devices & FCM Token
        Route::post('/devices', [DeviceController::class, 'register']);
        Route::delete('/devices/{uid}', [DeviceController::class, 'destroy']);

        // Attendance (เช็คชื่อเข้า/ออกงาน)
        Route::get('/attendance/me', [AttendanceController::class, 'history']);
        Route::middleware(['device.bound', 'shop.open', 'geofence', 'throttle:10,1'])->group(function () {
            Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
            Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut']);
        });

        // Stock (ดูสต็อก & ประวัติ)
        Route::middleware(['branch.scope'])->group(function () {
            Route::get('/stock', [StockController::class, 'index']);
            Route::get('/stock/low', [StockController::class, 'lowStock']);
            Route::get('/stock/movements', [StockController::class, 'movements']);

            // อัปเดตสต็อกนอกรอบ (ของเข้า/เปลี่ยน/เสีย)
            Route::post('/stock/updates', [StockUpdateController::class, 'store'])->middleware('geofence');

            // Daily Stock Count (นับสต็อกรายวัน)
            Route::get('/stock-counts/today', [StockCountController::class, 'todayDraft']);
            Route::put('/stock-counts/{id}/items/{variantId}', [StockCountController::class, 'updateItem']);
            Route::post('/stock-counts/{id}/confirm-row', [StockCountController::class, 'confirmRow']);
            Route::post('/stock-counts/{id}/submit', [StockCountController::class, 'submit'])->middleware('geofence');

            // Daily Checklist (เช็คลิสต์เปิด/ปิด/ความสะอาด)
            Route::get('/checklists/today', [ChecklistController::class, 'todayChecklists']);
            Route::put('/checklists/{id}/items/{itemId}', [ChecklistController::class, 'updateItem']);
            Route::post('/checklists/{id}/items/{itemId}/photos', [ChecklistController::class, 'uploadPhoto']);
            Route::post('/checklists/{id}/submit', [ChecklistController::class, 'submit'])->middleware('geofence');

            // Daily Closing (แจ้งปิดร้าน)
            Route::get('/closing/today', [ClosingController::class, 'todayStatus']);
            Route::post('/closing', [ClosingController::class, 'close'])->middleware('geofence');
        });

        // In-app Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    });
});
