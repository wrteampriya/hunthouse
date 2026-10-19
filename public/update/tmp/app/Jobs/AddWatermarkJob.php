<?php

namespace App\Jobs;

use App\Services\HelperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class AddWatermarkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $imagePath;

    public string $extension;

    public array $watermarkConfig = [];

    public $timeout = 300;

    public $tries = 3;

    public $failOnTimeout = true;

    // Max dimension for processing — keeps GD memory bounded (~15 MB per image at 2000px)
    private const MAX_DIMENSION = 2000;

    // Max tiles for tiled watermark style
    private const MAX_TILES = 150;

    public function __construct(string $imagePath, string $extension, array $watermarkConfig = [])
    {
        $this->imagePath = preg_replace('#[\\\\/]+#', DIRECTORY_SEPARATOR, $imagePath);
        $this->extension = $extension;
        $this->watermarkConfig = $watermarkConfig;
    }

    public function handle(): void
    {
        // GD needs width × height × 4 bytes per image in RAM.
        // Raise the limit only for this job process.
        ini_set('memory_limit', '512M');

        $image     = null;
        $watermark = null;

        try {
            $watermarkConfig = $this->watermarkConfig ?: HelperService::resolveListingWatermarkConfig();
            $watermarkPath   = $watermarkConfig['watermark_path'] ?? null;

            Log::info('AddWatermarkJob: Starting watermark process', [
                'imagePath'       => $this->imagePath,
                'extension'       => $this->extension,
                'watermarkConfig' => $watermarkConfig,
            ]);

            if (empty($watermarkPath) || ! file_exists($watermarkPath)) {
                Log::error('Watermark not found', ['watermarkPath' => $watermarkPath]);

                return;
            }

            $opacity  = (int) ($watermarkConfig['opacity'] ?? 25);
            $size     = (int) ($watermarkConfig['size'] ?? 10);
            $style    = $watermarkConfig['style'] ?? 'tile';
            $position = $watermarkConfig['position'] ?? 'center';
            $rotation = (int) ($watermarkConfig['rotation'] ?? 0);

            if ($rotation < 0) {
                $rotation = 360 + $rotation;
            }

            // Load the main image at the capped dimension immediately to avoid holding
            // both the full-size and resized bitmaps in RAM at the same time.
            $image = $this->loadAndScale($this->imagePath, self::MAX_DIMENSION);
            if ($image === null) {
                Log::error('AddWatermarkJob: Could not load image', ['path' => $this->imagePath]);

                return;
            }

            // Load watermark pre-scaled to target width so we never hold an oversized
            // watermark bitmap in memory longer than necessary.
            $targetWmWidth = max(10, (int) ($image->width() * ($size / 100)));
            $watermark     = $this->loadAndScale($watermarkPath, $targetWmWidth);
            if ($watermark === null) {
                Log::error('AddWatermarkJob: Could not load watermark', ['path' => $watermarkPath]);

                return;
            }

            Log::info('AddWatermarkJob: image and watermark loaded', [
                'image_w'        => $image->width(),
                'image_h'        => $image->height(),
                'watermark_w_before_resize' => $watermark->width(),
                'targetWmWidth'  => $targetWmWidth,
                'opacity'        => $opacity,
                'style'          => $style,
                'rotation'       => $rotation,
                'file_size_before' => file_exists($this->imagePath) ? filesize($this->imagePath) : 'missing',
            ]);

            $watermark->opacity($opacity);

            // Resize to exact target width (loadAndScale caps, not forces)
            if ($watermark->width() !== $targetWmWidth) {
                $watermark->resize($targetWmWidth, null, fn ($c) => $c->aspectRatio());
            }

            Log::info('AddWatermarkJob: watermark ready', [
                'watermark_w' => $watermark->width(),
                'watermark_h' => $watermark->height(),
            ]);

            // Convert clockwise to Intervention's counterclockwise
            $watermark->rotate(360 - $rotation);

            if ($style === 'tile') {
                $this->applyTile($image, $watermark);
            } else {
                $this->applySingle($image, $watermark, $position);
            }

            // Free watermark memory before the encode buffer is allocated
            $watermark->destroy();
            $watermark = null;

            $image->encode($this->extension, 82)->save($this->imagePath);

            // The admin panel shows a blurred low-quality preview cached in
            // blur_cache/blur_{filename}. That cache was generated before the
            // watermark was applied, so delete it now — getCachedBlurImageUrl()
            // checks file existence and regenerates automatically on next load.
            $blurStoragePath = 'blur_cache/blur_' . basename($this->imagePath);
            if (Storage::disk('public')->exists($blurStoragePath)) {
                Storage::disk('public')->delete($blurStoragePath);
            }

            Log::info('AddWatermarkJob: Successfully processed watermark', [
                'source'          => $watermarkConfig['source'] ?? 'admin',
                'driver'          => 'gd',
                'file_size_after' => file_exists($this->imagePath) ? filesize($this->imagePath) : 'missing',
                'save_path'       => $this->imagePath,
            ]);

        } catch (\Throwable $e) {
            Log::error('Error in AddWatermarkJob: ' . $e->getMessage(), [
                'imagePath' => $this->imagePath,
                'trace'     => $e->getTraceAsString(),
            ]);
            $this->fail($e);
            throw $e;
        } finally {
            if ($image !== null) {
                try { $image->destroy(); } catch (\Throwable $e) {}
            }
            if ($watermark !== null) {
                try { $watermark->destroy(); } catch (\Throwable $e) {}
            }
        }
    }

    // -------------------------------------------------------------------------
    // GD helpers
    // -------------------------------------------------------------------------

    /**
     * Load an image via native GD, downscale immediately if wider than $maxWidth,
     * free the original GD resource before returning. This avoids holding both
     * the full-size and the resized bitmap in RAM at the same time.
     */
    private function loadAndScale(string $path, int $maxWidth): ?\Intervention\Image\Image
    {
        $info = @getimagesize($path);
        if (! $info) {
            return null;
        }

        [$origW, $origH, $type] = $info;

        if ($origW <= $maxWidth) {
            // Already within budget — let Intervention Image handle it normally
            return Image::make($path);
        }

        // Scale factor to bring width down to $maxWidth
        $scale   = $maxWidth / $origW;
        $targetW = $maxWidth;
        $targetH = (int) round($origH * $scale);

        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default        => null,
        };

        if (! $src) {
            // Fall back to Intervention (will use more memory but won't crash)
            return Image::make($path);
        }

        $dst = imagecreatetruecolor($targetW, $targetH);

        // Preserve transparency for PNG/WebP
        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_WEBP])) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);
        imagedestroy($src); // free the full-size bitmap immediately

        return Image::make($dst);
    }

    private function applyTile(\Intervention\Image\Image $image, \Intervention\Image\Image $watermark): void
    {
        $xStep = max(1, (int) ($watermark->width() * 1.5));
        $yStep = max(1, (int) ($watermark->height() * 1.5));

        $tilesX = (int) ceil($image->width() / $xStep);
        $tilesY = (int) ceil($image->height() / $yStep);
        $total  = $tilesX * $tilesY;

        if ($total > self::MAX_TILES) {
            $factor = sqrt($total / self::MAX_TILES);
            $xStep  = (int) ($xStep * $factor);
            $yStep  = (int) ($yStep * $factor);
        }

        for ($y = 0; $y < $image->height(); $y += $yStep) {
            for ($x = 0; $x < $image->width(); $x += $xStep) {
                $image->insert($watermark, 'top-left', $x, $y);
            }
        }
    }

    private function applySingle(\Intervention\Image\Image $image, \Intervention\Image\Image $watermark, string $position): void
    {
        $pad = 10;
        $w   = $image->width();
        $h   = $image->height();
        $wmW = $watermark->width();
        $wmH = $watermark->height();

        [$x, $y] = match ($position) {
            'top-left'     => [$pad, $pad],
            'top-right'    => [$w - $wmW - $pad, $pad],
            'bottom-left'  => [$pad, $h - $wmH - $pad],
            'bottom-right' => [$w - $wmW - $pad, $h - $wmH - $pad],
            default        => [(int) (($w - $wmW) / 2), (int) (($h - $wmH) / 2)],
        };

        $image->insert($watermark, 'top-left', $x, $y);
    }
}
