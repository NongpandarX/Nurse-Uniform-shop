<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ImageService
{
    /**
     * บันทึกและบีบอัดรูปภาพเป็น WebP q=70 (ความยาวสูงสุด 1280px + Thumbnail 320px) (§5.6)
     */
    public function storeAndCompress(
        UploadedFile $file,
        Model $mediable,
        User $user,
        string $directory = 'checklists',
        string $disk = 'local'
    ): Media {
        $realPath = $file->getRealPath();
        $fileContents = file_get_contents($realPath);
        if ($fileContents === false) {
            throw new InvalidArgumentException("Cannot read uploaded file content.");
        }

        $src = @imagecreatefromstring($fileContents);
        if ($src === false) {
            throw new InvalidArgumentException("Invalid or unsupported image format.");
        }

        // แก้ไข EXIF Orientation หากถ่ายจากกล้องมือถือ
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($realPath);
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $rotated = imagerotate($src, 180, 0);
                        imagedestroy($src);
                        $src = $rotated;
                        break;
                    case 6:
                        $rotated = imagerotate($src, -90, 0);
                        imagedestroy($src);
                        $src = $rotated;
                        break;
                    case 8:
                        $rotated = imagerotate($src, 90, 0);
                        imagedestroy($src);
                        $src = $rotated;
                        break;
                }
            }
        }

        $yearMonth = now()->format('Y/m');
        $uuid = (string) Str::uuid();

        $mainPath = "{$directory}/{$yearMonth}/{$uuid}.webp";
        $thumbPath = "{$directory}/{$yearMonth}/thumbs/{$uuid}.webp";

        try {
            // 1. ปรับขนาดด้านยาวสุด ≤ 1280px + แปลงเป็น WebP q=70
            $mainResult = $this->scaleAndEncodeWebp($src, 1280, 70);

            // 2. สร้าง Thumbnail ด้านยาวสุด ≤ 320px + แปลงเป็น WebP q=70
            $thumbResult = $this->scaleAndEncodeWebp($src, 320, 70);
        } finally {
            if (is_resource($src) || (is_object($src) && $src instanceof \GdImage)) {
                imagedestroy($src);
            }
        }

        // 3. บันทึกไฟล์ลง Storage (Private disk)
        Storage::disk($disk)->put($mainPath, $mainResult['data']);
        Storage::disk($disk)->put($thumbPath, $thumbResult['data']);

        // 4. บันทึกลงตาราง media
        $retentionDays = (int) Setting::get('media_retention_days', 60);

        return Media::create([
            'mediable_type' => get_class($mediable),
            'mediable_id' => $mediable->getKey(),
            'disk' => $disk,
            'path' => $mainPath,
            'thumb_path' => $thumbPath,
            'mime' => 'image/webp',
            'size_bytes' => $mainResult['size'],
            'width' => $mainResult['width'],
            'height' => $mainResult['height'],
            'created_by' => $user->id,
            'expires_at' => now()->addDays($retentionDays),
            'is_pinned' => false,
            'created_at' => now(),
        ]);
    }

    /**
     * ย่อขนาดรูปและ encode เป็น WebP
     */
    protected function scaleAndEncodeWebp($src, int $maxDimension, int $quality = 70): array
    {
        $w = imagesx($src);
        $h = imagesy($src);

        if ($w > $maxDimension || $h > $maxDimension) {
            if ($w >= $h) {
                $targetW = $maxDimension;
                $targetH = (int) round(($h / $w) * $maxDimension);
            } else {
                $targetH = $maxDimension;
                $targetW = (int) round(($w / $h) * $maxDimension);
            }
        } else {
            $targetW = $w;
            $targetH = $h;
        }

        $targetW = max(1, $targetW);
        $targetH = max(1, $targetH);

        $scaled = imagescale($src, $targetW, $targetH);
        if ($scaled === false) {
            throw new RuntimeException("Failed to scale image.");
        }

        imagealphablending($scaled, true);
        imagesavealpha($scaled, true);

        ob_start();
        imagewebp($scaled, null, $quality);
        $data = ob_get_clean();

        $resultWidth = imagesx($scaled);
        $resultHeight = imagesy($scaled);

        imagedestroy($scaled);

        return [
            'data' => $data,
            'width' => $resultWidth,
            'height' => $resultHeight,
            'size' => strlen($data),
        ];
    }

    /**
     * ล้างรูปภาพที่หมดอายุตาม media_retention_days (ไม่รวมที่ปักหมุด is_pinned = true) (§5.6)
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
