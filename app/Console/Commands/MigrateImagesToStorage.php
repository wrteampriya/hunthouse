<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MigrateImagesToStorage extends Command
{
    protected $signature = 'images:migrate-to-storage';
    protected $description = 'Migrate images from public/images to storage/app/public';

    public function handle()
    {
        $this->info('Starting image migration...');

        $sourcePath = public_path('images');
        $destinationPath = storage_path('app/public');

        // First, clear all files and folders in storage/app/public
        if (File::exists($destinationPath)) {
            $this->info('Clearing all files and folders in storage/app/public...');
            // Only delete contents, not the 'public' folder itself
            $files = File::allFiles($destinationPath);
            $directories = File::directories($destinationPath);

            // Delete all files
            foreach ($files as $file) {
                File::delete($file);
            }
            // Delete all directories
            foreach ($directories as $dir) {
                File::deleteDirectory($dir);
            }
        }

        // Create destination directory if it doesn't exist
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }

        // Get all subdirectories from config
        $paths = [
            'category',
            'seo_setting',
            'property_seo_img',
            'project_seo_img',
            'slider',
            'notification',
            'users',
            'chat',
            'chat_audio',
            'property_title_img',
            'project_title_img',
            'property_gallery_img',
            'property_document_img',
            'project_document_img',
            'article_img',
            'advertisement_img',
            'parameter_img',
            '3d_img',
            'facility_img',
            'city_image',
            'admin_profile',
            'bank_receipt_file',
            'adbanner_img',
        ];

        // Also add .gitignore if it exists in public/images to storage/app/public
        $gitignoreSource = $sourcePath . DIRECTORY_SEPARATOR . '.gitignore';
        $gitignoreDest = $destinationPath . DIRECTORY_SEPARATOR . '.gitignore';
        if (File::exists($gitignoreSource)) {
            if (File::copy($gitignoreSource, $gitignoreDest)) {
                $this->info("Copied .gitignore to storage/app/public");
            } else {
                $this->error("Failed to copy .gitignore");
            }
        }

        $migrated = 0;
        $failed = 0;

        foreach ($paths as $path) {
            $sourceDir = $sourcePath . '/' . $path;
            $destDir = $destinationPath . '/' . $path;

            if (File::exists($sourceDir)) {
                $this->info("Migrating: {$path}");

                // Create destination directory
                if (!File::exists($destDir)) {
                    File::makeDirectory($destDir, 0755, true);
                }

                // Copy files
                $files = File::allFiles($sourceDir);
                foreach ($files as $file) {
                    $relativePath = $file->getRelativePathname();
                    $destFile = $destDir . '/' . $relativePath;

                    // Handle nested directories
                    $destFileDir = dirname($destFile);
                    if (!File::exists($destFileDir)) {
                        File::makeDirectory($destFileDir, 0755, true);
                    }

                    if (File::copy($file->getPathname(), $destFile)) {
                        $migrated++;
                    } else {
                        $failed++;
                        $this->error("Failed to copy: {$relativePath}");
                    }
                }
            }
        }

        $this->info("Migration complete! Migrated: {$migrated}, Failed: {$failed}");
        $this->info("Run 'php artisan storage:link' to create the symbolic link");
        $this->warn("After verifying, you can delete the old files from public/images/");
        // Remove old images folder in public
        if (File::exists($sourcePath)) {
            try {
                File::deleteDirectory($sourcePath);
                $this->info("Successfully deleted old images folder: {$sourcePath}");
            } catch (\Exception $e) {
                $this->error("Failed to delete old images folder: " . $e->getMessage());
            }
        }

        return 0;
    }
}
