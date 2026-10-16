<?php

namespace App\Services;

use App\Jobs\AddWatermarkJob;
use App\Jobs\OptimizeImageJob;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class FileService
{
    /**
     * Compress and upload an image (optional watermark)
     *
     * @return string|false
     */
    public static function compressAndUpload($requestFile, string $folder, bool $addWaterMark = false, $watermarkAgentId = null)
    {
        $filenameWithoutExt = pathinfo($requestFile->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($requestFile->getClientOriginalExtension());
        $fileName = time().'-'.Str::slug($filenameWithoutExt).'.'.$extension;
        $disk = 'public';
        $path = $folder.''.$fileName;

        try {
            Log::info('FileService::compressAndUpload: Starting', ['file' => $fileName, 'extension' => $extension, 'disk' => $disk, 'path' => $path]);

            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                // Compress and save image
                try {
                    Log::info('FileService::compressAndUpload: Calling Image::make');
                    $image = Image::make($requestFile)->encode($extension, 80);
                    Log::info('FileService::compressAndUpload: Image made');
                } catch (\Throwable $e) {
                    Log::error('FileService::compressAndUpload: Image::make failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                    // Fallback to simple upload if compression fails
                    $requestFile->storeAs($folder, $fileName, $disk);

                    return $fileName;
                }

                $putResult = Storage::disk($disk)->put($path, (string) $image);

                if (! $putResult) {
                    Log::error('FileService::compressAndUpload: Storage::put failed', ['path' => $path]);

                    return false;
                }

                Log::info('FileService::compressAndUpload: Image saved', ['path' => $path]);

                // Get absolute path safely
                $absolutePath = self::getAbsolutePath($disk, $path);
                if (! $absolutePath) {
                    Log::warning('Cannot get absolute path for image', ['disk' => $disk, 'path' => $path]);

                    return $fileName;
                }

                // Optimize image (queue for background processing)
                $fileSize = filesize($absolutePath);
                $maxSizeForOptimization = 5 * 1024 * 1024; // 5MB

                if ($fileSize < $maxSizeForOptimization) {
                    OptimizeImageJob::dispatch($absolutePath);
                } else {
                    Log::info('Skipping optimization for large image', [
                        'file' => $absolutePath,
                        'size' => $fileSize,
                    ]);
                }

                // Queue watermark if a valid admin or agent config is available.
                // Callers must pass $watermarkAgentId explicitly for agent uploads —
                // a null id means the upload is not agent-context (e.g. user role),
                // so only the admin watermark config may apply.
                if ($addWaterMark) {
                    $watermarkConfig = HelperService::resolveListingWatermarkConfig($watermarkAgentId);
                    Log::info('FileService: watermark dispatch check', [
                        'file'              => $fileName,
                        'watermark_agent_id' => $watermarkAgentId,
                        'config_source'     => $watermarkConfig['source'] ?? 'none',
                        'dispatching'       => ! empty($watermarkConfig),
                    ]);
                    if (! empty($watermarkConfig)) {
                        AddWatermarkJob::dispatch($absolutePath, $extension, $watermarkConfig)->delay(now()->addSeconds(5));
                    }
                }

                return $fileName;
            }

            // Non-image files
            $requestFile->storeAs($folder, $fileName, $disk);

            return $fileName;

        } catch (Exception $e) {
            Log::error('FileService::compressAndUpload error: '.$e->getMessage(), ['file' => $path]);

            return false;
        }
    }

    /**
     * Simple upload (non-compressed)
     */
    public static function upload($requestFile, string $folder): string
    {
        $fileName = uniqid('', true).time().'.'.$requestFile->getClientOriginalExtension();
        $requestFile->storeAs($folder, $fileName, 'public');

        return $folder.'/'.$fileName;
    }

    /**
     * Replace a file with new upload
     */
    public static function replace($requestFile, string $folder, $deleteRawOriginalImage)
    {
        self::delete($folder, $deleteRawOriginalImage);

        return self::upload($requestFile, $folder);
    }

    /**
     * Compress and replace an existing image
     */
    public static function compressAndReplace($requestFile, string $folder, $deleteRawOriginalImage, bool $addWaterMark = false, $watermarkAgentId = null)
    {
        if (! empty($deleteRawOriginalImage)) {
            self::delete($folder, $deleteRawOriginalImage);
        }

        return self::compressAndUpload($requestFile, $folder, $addWaterMark, $watermarkAgentId);
    }

    /**
     * File Exists
     */
    public static function fileExists(string $filePath): bool
    {
        $disk = 'public';
        $absolutePath = self::getAbsolutePath($disk, $filePath);

        return $absolutePath ? true : false;
    }

    /**
     * Get absolute local path for a file on any disk
     */
    public static function getAbsolutePath(string $disk, string $filePath): ?string
    {
        try {
            $diskInstance = Storage::disk($disk);

            if (method_exists($diskInstance, 'path')) {
                $absolutePath = $diskInstance->path($filePath);

                // if (!file_exists($absolutePath)) {
                //     Log::info('FileService::getAbsolutePath: File not found', ['path' => $absolutePath, 'filePath' => $filePath, 'disk' => $disk]);
                // }
                return file_exists($absolutePath) ? $absolutePath : null;
            }
        } catch (Exception $e) {
            Log::warning('FileService::getAbsolutePath failed', ['disk' => $disk, 'file' => $filePath, 'error' => $e->getMessage()]);
        }

        return null; // Non-local disk
    }

    /**
     * Get URL for a file
     */
    public static function getFileUrl(string $imagePath): ?string
    {
        if (self::fileExists($imagePath)) {
            return Storage::disk('public')->url($imagePath);
        }

        return null;
    }

    /**
     * Delete a file if exists
     */
    public static function delete(string $folder, $image): bool
    {
        if (! empty($image) && Storage::disk('public')->exists($folder.'/'.$image)) {
            return Storage::disk('public')->delete($folder.'/'.$image);
        }

        return true;
    }

    public static function generateBlurData(string $path, int $width = 20, int $height = 20, int $blur = 10, int $quality = 50): string
    {
        $img = Image::make($path)
            ->resize($width, $height)
            ->blur($blur);

        // Detect original image extension
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $extension = 'jpg'; // fallback
        }

        // encode to original format
        $img->encode($extension, $quality);

        return base64_encode($img);
    }

    public static function getBlurDataUri(string $path, int $width = 20, int $height = 20, int $blur = 10, int $quality = 50): string
    {
        $filesystem = 'public';
        $storagePath = self::getAbsolutePath($filesystem, ltrim($path, '/'));
        if (! $storagePath) {
            Log::info('File not found: '.$path);

            return false;
        }

        $base64 = self::generateBlurData($storagePath, $width, $height, $blur, $quality);

        // Detect mime type
        $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.$base64;
    }

    /**
     * Generate or return cached blur image URL
     *
     * @param  string  $imagePath  Full storage path of original image
     * @param  string  $cacheKey  Unique cache key (e.g., 'module_blur_{module}_{id}')
     * @param  string|null  $filename  Optional filename to save
     * @return string|null Public URL
     */
    public static function getCachedBlurImageUrl(string $imagePath, string $cacheKey, ?string $filename = null): ?string
    {
        try {
            $filename = $filename ?? 'blur_'.basename($imagePath);
            $storagePath = "blur_cache/{$filename}";
            $filesystem = 'public';

            if (! Cache::has($cacheKey) || ! Storage::disk($filesystem)->exists($storagePath)) {

                $originalImagePath = self::getAbsolutePath($filesystem, $imagePath);
                if (! file_exists($originalImagePath)) {
                    return null;
                }

                // Generate tiny blurred image
                $blurData = self::getBlurDataUri($imagePath, 20, 20, 10, 50);

                [$type, $data] = explode(',', $blurData);
                $imageBinary = base64_decode($data);

                Storage::disk($filesystem)->put($storagePath, $imageBinary);

                Cache::forever($cacheKey, $storagePath);
            } else {
                $storagePath = Cache::get($cacheKey);
            }

            return Storage::disk($filesystem)->url($storagePath);

        } catch (Exception $e) {
            Log::error('FileService getCachedBlurImageUrl error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Clear cached blur image
     */
    public static function clearCachedBlurImageUrl(string $cacheKey): void
    {
        $storagePath = Cache::get($cacheKey);
        $filesystem = 'public';
        if ($storagePath && Storage::disk($filesystem)->exists($storagePath)) {
            Storage::disk($filesystem)->delete($storagePath);
        }
        Cache::forget($cacheKey);
    }
}
