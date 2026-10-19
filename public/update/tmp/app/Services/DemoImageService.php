<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DemoImageService
{
    private const GOOGLE_DRIVE_FILE_ID = '1-zR7T7mvymnGdi8xnyFdrzrqmMh63OOQ';

    private const DEMO_FOLDER = 'demo';

    private const ZIP_FILENAME = 'demo-images.zip';

    // https://drive.google.com/file/d/1-zR7T7mvymnGdi8xnyFdrzrqmMh63OOQ/view?usp=sharing

    /**
     * Ensure demo images exist, download if necessary
     */
    public static function ensureDemoImagesExist(): bool
    {
        try {
            $demoPath = storage_path('app/public/'.self::DEMO_FOLDER);

            // Check if demo folder exists and has content
            if (self::demoFolderHasImages($demoPath)) {
                Log::info('Demo images already exist, skipping download');

                return true;
            }

            Log::info('Demo images not found, downloading from Google Drive');

            return self::downloadAndExtractDemoImages();

        } catch (Exception $e) {
            Log::error('DemoImageService::ensureDemoImagesExist error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Check if a specific demo image exists
     *
     * @param  string  $relativePath  Path relative to demo folder (e.g., 'category/villa.jpg')
     */
    public static function imageExists(string $relativePath): bool
    {
        $fullPath = self::DEMO_FOLDER.'/'.$relativePath;

        return Storage::disk('public')->exists($fullPath);
    }

    /**
     * Get the full storage path for a demo image
     *
     * @param  string  $relativePath  Path relative to demo folder (e.g., 'category/villa.jpg')
     * @return string Full path
     */
    public static function getDemoImagePath(string $relativePath): string
    {
        return storage_path('app/public/'.self::DEMO_FOLDER.'/'.$relativePath);
    }

    /**
     * Process a demo image through FileService
     * Creates UploadedFile from demo image and passes to FileService::compressAndUpload
     *
     * @param  string  $demoImagePath  Path relative to demo folder (e.g., 'category/villa.jpg')
     * @param  string  $destinationFolder  Destination folder for FileService
     * @param  bool  $addWatermark  Whether to add watermark
     * @return string|false Processed filename on success, false on failure
     */
    public static function processImageWithFileService(string $demoImagePath, string $destinationFolder, bool $addWatermark = false)
    {
        try {
            $sourcePath = self::getDemoImagePath($demoImagePath);

            if (! file_exists($sourcePath)) {
                Log::error('Demo image not found', ['path' => $sourcePath]);

                return false;
            }

            // Create a temporary UploadedFile object from the demo image
            $filename = basename($sourcePath);
            $mimeType = mime_content_type($sourcePath);
            $tempFile = tmpfile();
            $tempPath = stream_get_meta_data($tempFile)['uri'];

            // Copy demo image to temp location
            copy($sourcePath, $tempPath);

            // Create UploadedFile instance
            $uploadedFile = new UploadedFile(
                $tempPath,
                $filename,
                $mimeType,
                null,
                true // test mode - don't validate
            );

            // Use FileService to process the image (compression, watermark, optimization)
            $result = FileService::compressAndUpload($uploadedFile, $destinationFolder, $addWatermark);

            // Clean up temp file
            fclose($tempFile);

            return $result;

        } catch (Exception $e) {
            Log::error('DemoImageService::processImageWithFileService error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Download ZIP from Google Drive and extract to demo folder
     */
    private static function downloadAndExtractDemoImages(): bool
    {
        try {
            $tempZipPath = storage_path('app/temp/'.self::ZIP_FILENAME);
            $extractPath = storage_path('app/public/'.self::DEMO_FOLDER);

            // Create temp directory if it doesn't exist
            if (! file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            // Download ZIP from Google Drive
            Log::info('Downloading demo images ZIP from Google Drive');
            $downloadUrl = 'https://drive.google.com/uc?export=download&id='.self::GOOGLE_DRIVE_FILE_ID;

            $response = Http::timeout(300)->get($downloadUrl);

            if (! $response->successful()) {
                Log::error('Failed to download demo images ZIP', ['status' => $response->status()]);

                return false;
            }

            // Save ZIP file
            file_put_contents($tempZipPath, $response->body());
            Log::info('Demo images ZIP downloaded successfully', ['size' => filesize($tempZipPath)]);

            // Extract ZIP to demo folder
            $zip = new ZipArchive;
            if ($zip->open($tempZipPath) === true) {
                // Create demo folder if it doesn't exist
                if (! file_exists($extractPath)) {
                    mkdir($extractPath, 0755, true);
                }

                // Extract all files
                $zip->extractTo($extractPath);
                $zip->close();

                // --- PRE-FLATTENING LOGIC ---
                // If the user accidentally zipped the parent folder (e.g. "Demo Images"),
                // the folders will be nested inside $extractPath/Demo Images/
                // Let's check for this case and move them up.
                if (! file_exists($extractPath.'/category') && ! file_exists($extractPath.'/parameter_img')) {
                    $subfolders = glob($extractPath.'/*', GLOB_ONLYDIR);
                    if (count($subfolders) === 1) {
                        $nestedDir = $subfolders[0];
                        if (file_exists($nestedDir.'/category')) {
                            // Move everything from nested dir to root extractPath
                            $items = scandir($nestedDir);
                            foreach ($items as $item) {
                                if ($item !== '.' && $item !== '..') {
                                    rename($nestedDir.'/'.$item, $extractPath.'/'.$item);
                                }
                            }
                            rmdir($nestedDir);
                        }
                    }
                }

                // --- POST-EXTRACTION PLACEHOLDER DUPLICATION ---
                // If only a generic placeholder is provided in the zip, duplicate it for all expected property names.
                $titleImgDir = $extractPath.'/property_title_img';
                $expectedTitleImages = ['Luxury Sunset Villa.png', 'Modern Family House.png', 'Downtown Luxury Apartment.png', 'Prime Office Space.png', 'Residential Plot - Prime Location.png', 'Beachfront Paradise Villa.jpg', 'Cozy Cottage House.png', 'Modern Studio Apartment.png'];
                if (file_exists($titleImgDir)) {
                    // Find a source image (first available image)
                    $available = glob($titleImgDir.'/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE);
                    if (! empty($available)) {
                        $sourceImg = $available[0];
                        foreach ($expectedTitleImages as $expected) {
                            $targetPath = $titleImgDir.'/'.$expected;
                            if (! file_exists($targetPath)) {
                                copy($sourceImg, $targetPath);
                            }
                        }
                    }
                }

                $galleryImgDir = $extractPath.'/property_gallery_img';
                $expectedGalleryImages = ['Living Room.jpg', 'Kitchen.jpg', 'Bedroom.jpg'];
                if (file_exists($galleryImgDir)) {
                    $available = glob($galleryImgDir.'/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE);
                    if (! empty($available)) {
                        $sourceImg = $available[0];
                        foreach ($expectedGalleryImages as $expected) {
                            $targetPath = $galleryImgDir.'/'.$expected;
                            if (! file_exists($targetPath)) {
                                copy($sourceImg, $targetPath);
                            }
                        }
                    }
                }
                // ------------------------------------------------

                Log::info('Demo images extracted successfully', ['path' => $extractPath]);

                // Clean up temp ZIP file
                unlink($tempZipPath);

                return true;
            } else {
                Log::error('Failed to open ZIP file', ['path' => $tempZipPath]);

                return false;
            }

        } catch (Exception $e) {
            Log::error('DemoImageService::downloadAndExtractDemoImages error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Check if demo folder exists and has images
     */
    private static function demoFolderHasImages(string $demoPath): bool
    {
        if (! file_exists($demoPath)) {
            return false;
        }

        // Check if folder has subdirectories with images
        $expectedFolders = ['category', 'parameter_img', 'property_title_img'];

        foreach ($expectedFolders as $folder) {
            $folderPath = $demoPath.'/'.$folder;
            if (! file_exists($folderPath)) {
                Log::info('Missing demo folder: '.$folder);

                return false;
            }

            // Check if folder has at least one image file (including SVG)
            $files = glob($folderPath.'/*.{jpg,jpeg,png,webp,gif,svg}', GLOB_BRACE);
            if (empty($files)) {
                Log::info('Demo folder is empty: '.$folder);

                return false;
            }
        }

        return true;
    }

    /**
     * Get list of available images in a demo subfolder
     *
     * @param  string  $subfolder  (e.g., 'category', 'parameter_img', 'property_title_img')
     * @return array Array of filenames
     */
    public static function getAvailableImages(string $subfolder): array
    {
        $folderPath = storage_path('app/public/'.self::DEMO_FOLDER.'/'.$subfolder);

        if (! file_exists($folderPath)) {
            return [];
        }

        // Include SVG files
        $files = glob($folderPath.'/*.{jpg,jpeg,png,webp,gif,svg}', GLOB_BRACE);

        return array_map(function ($file) {
            return basename($file);
        }, $files);
    }
}
