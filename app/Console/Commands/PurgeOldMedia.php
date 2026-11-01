<?php

namespace App\Console\Commands;

use App\Services\ImageService;
use Illuminate\Console\Command;

class PurgeOldMedia extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'media:purge-old';

    /**
     * The console command description.
     */
    protected $description = 'ลบรูปภาพเช็คลิสต์ที่หมดอายุ (เกิน 60 วัน) ออกจาก Storage และฐานข้อมูล ยกเว้นรูปที่ถูกปักหมุดไว้';

    /**
     * Execute the console command.
     */
    public function handle(ImageService $imageService): int
    {
        $this->info('กำลังตรวจสอบและล้างรูปภาพที่หมดอายุตาม media_retention_days...');

        $deletedCount = $imageService->purgeExpiredMedia();

        $this->info("ล้างรูปภาพที่หมดอายุสำเร็จแล้วทั้งหมด {$deletedCount} รายการ");

        return self::SUCCESS;
    }
}

