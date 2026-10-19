<?php

namespace App\Jobs;

use App\Services\HelperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Facades\Image;

class AddWatermarkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $imagePath;

    public string $extension;

    public $timeout = 300; // seconds (5 minutes)

    public $tries = 3;     // retry at most twice

    public $failOnTimeout = true;

    public function __construct(string $imagePath, string $extension)
    {
        // Normalize slashes — replace double (or mixed) slashes with a single one
        $normalizedPath = preg_replace('#[\\\\/]+#', DIRECTORY_SEPARATOR, $imagePath);

        $this->imagePath = $normalizedPath;
        $this->extension = $extension;
    }

    public function handle(): void
    {
        try {
            // Get watermark configuration from settings
            $watermarkConfig = HelperService::getWatermarkConfigDecoded();
            $watermarkPath = public_path('assets/images/logo/'.$watermarkConfig['watermark_image']);
            if (! isset($watermarkConfig['watermark_image']) || empty($watermarkConfig['watermark_image']) || ! file_exists($watermarkPath)) {
                $companyLogo = HelperService::getSettingData('company_logo');
                if ($companyLogo) {
                    $watermarkPath = public_path('assets/images/logo/'.$companyLogo);
                } else {
                    $watermarkPath = public_path('assets/images/logo/logo.png');
                }
            }
            if (! file_exists($watermarkPath)) {
                Log::error('Watermark not found', ['watermarkPath' => $watermarkPath]);

                return;
            }

            // Set defaults if config is empty
            $opacity = $watermarkConfig['opacity'] ?? 25;
            $size = $watermarkConfig['size'] ?? 10;
            $style = $watermarkConfig['style'] ?? 'tile';
            $position = $watermarkConfig['position'] ?? 'center';
            $rotation = $watermarkConfig['rotation'] ?? 0;

            // Convert negative rotations to positive range (legacy)
            if ($rotation < 0) {
                $rotation = 360 + $rotation;
            }

            // Load image
            $image = Image::make($this->imagePath);
            $originalWidth = $image->width();
            $originalHeight = $image->height();

            // Only resize very large images (over 3000px) to speed up processing
            // This maintains quality for most images while improving performance
            $maxDimension = 3000;

            if ($originalWidth > $maxDimension || $originalHeight > $maxDimension) {
                if ($originalWidth > $originalHeight) {
                    $image->resize($maxDimension, null, fn ($c) => $c->aspectRatio());
                } else {
                    $image->resize(null, $maxDimension, fn ($c) => $c->aspectRatio());
                }
            }

            // Load and prepare watermark once
            $watermark = Image::make($watermarkPath);
            $watermark->opacity($opacity);
            $watermarkWidth = $image->width() * ($size / 100);
            $watermark->resize($watermarkWidth, null, fn ($c) => $c->aspectRatio());
            // Convert clockwise degrees to Intervention's counterclockwise rotation
            $rotation = 360 - $rotation; // ✅ This line fixes the issue

            $watermark->rotate($rotation);

            /**
             * 🧩 Apply watermark based on style (optimized)
             */
            if ($style === 'tile') {
                // Optimized tiling: Calculate optimal spacing to limit operations
                $baseSpacing = 1.5;
                $xStep = (int) ($watermark->width() * $baseSpacing);
                $yStep = (int) ($watermark->height() * $baseSpacing);

                // Calculate number of tiles and optimize spacing if too many
                $tilesX = (int) ceil($image->width() / $xStep);
                $tilesY = (int) ceil($image->height() / $yStep);
                $totalTiles = $tilesX * $tilesY;

                // Limit to max 150 tiles for performance (adjust spacing if needed)
                $maxTiles = 150;
                if ($totalTiles > $maxTiles) {
                    $factor = sqrt($totalTiles / $maxTiles);
                    $xStep = (int) ($xStep * $factor);
                    $yStep = (int) ($yStep * $factor);
                    // Recalculate after adjustment
                    $tilesX = (int) ceil($image->width() / $xStep);
                    $tilesY = (int) ceil($image->height() / $yStep);
                }

                // Apply tiles efficiently
                for ($y = 0; $y < $image->height(); $y += $yStep) {
                    for ($x = 0; $x < $image->width(); $x += $xStep) {
                        $image->insert($watermark, 'top-left', $x, $y);
                    }
                }
            } else {
                // Single watermark at specified position
                // Use 'top-left' as anchor and calculate absolute coordinates for all positions
                $x = 0;
                $y = 0;
                $padding = 10;

                switch ($position) {
                    case 'top-left':
                        $x = $padding;
                        $y = $padding;
                        break;
                    case 'top-right':
                        $x = $image->width() - $watermark->width() - $padding;
                        $y = $padding;
                        break;
                    case 'bottom-left':
                        $x = $padding;
                        $y = $image->height() - $watermark->height() - $padding;
                        break;
                    case 'bottom-right':
                        $x = $image->width() - $watermark->width() - $padding;
                        $y = $image->height() - $watermark->height() - $padding;
                        break;
                    case 'center':
                    default:
                        $x = (int) (($image->width() - $watermark->width()) / 2);
                        $y = (int) (($image->height() - $watermark->height()) / 2);
                        break;
                }

                // Always use 'top-left' as anchor with absolute coordinates
                $image->insert($watermark, 'top-left', $x, $y);
            }

            /**
             * 💾 Save optimized image (replace original)
             */
            $image->encode($this->extension, 85)->save($this->imagePath);
            Log::info('AddWatermarkJob: Successfully processed watermark');
        } catch (\Throwable $e) {
            Log::error('Error in AddWatermarkJob: '.$e->getMessage(), [
                'imagePath' => $this->imagePath,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->fail($e); // ensures retry
            throw $e;        // optional, for standard behavior
        }
    }
}
