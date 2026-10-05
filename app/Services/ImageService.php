<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageService
{
    /**
     * บันทึกและจัดเก็บรูปภาพเช็คลิสต์
     */
    public function storeImage(UploadedFile $file, string $directory = 'checklists'): array
    {
        $path = $file->store($directory . '/' . now()->format('Y/m'), 'local');

        return [
            'disk' => 'local',
            'path' => $path,
            'mime' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ];
    }

    /**
     * ล้างรูปภาพที่หมดอายุตาม media_retention_days (ไม่รวมที่ปักหมุด)
     */
    public function purgeExpiredMedia(): int
    {
        $expiredMedia = Media::where('is_pinned', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($expiredMedia as $media) {
            if (Storage::disk($media->disk)->exists($media->path)) {
                Storage::disk($media->disk)->delete($media->path);
            }
            if ($media->thumb_path && Storage::disk($media->disk)->exists($media->thumb_path)) {
                Storage::disk($media->disk)->delete($media->thumb_path);
            }
            $media->delete();
            $count++;
        }

        return $count;
    }
}
