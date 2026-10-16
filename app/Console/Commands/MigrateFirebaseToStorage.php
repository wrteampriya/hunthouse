<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class MigrateFirebaseToStorage extends Command
{
    protected $signature = 'firebase:migrate-to-storage';
    protected $description = 'Migrate firebase-service.json from public/assets to storage/app/private';

    public function handle()
    {
        $this->info('Starting Firebase service file migration...');

        $sourcePath = public_path('assets/firebase-service.json');
        $destinationPath = storage_path('app/private');
        $destinationFile = $destinationPath . '/firebase-service.json';

        // Check if source file exists
        if (!File::exists($sourcePath)) {
            $this->warn('Source file not found: ' . $sourcePath);
            $this->info('Migration skipped. File may have already been migrated or does not exist.');
            return 0;
        }

        // Create destination directory if it doesn't exist
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
            $this->info('Created directory: ' . $destinationPath);
        }

        // Check if file already exists in destination and replace it
        if (File::exists($destinationFile)) {
            File::delete($destinationFile);
            $this->info('Existing file found and will be replaced.');
        }

        // Copy file to storage/app/private
        if (File::copy($sourcePath, $destinationFile)) {
            $this->info('Successfully migrated firebase-service.json to storage/app/private');
            $this->warn('After verifying the file works correctly, you can delete the old file from public/assets/');
            return 0;
        } else {
            $this->error('Failed to copy file to storage/app/private');
            return 1;
        }
        // Remove old files from public/assets
        if (File::exists($sourcePath)) {
            File::delete($sourcePath);
            $this->info('Successfully deleted old files from public/assets');
        }
    }
}

