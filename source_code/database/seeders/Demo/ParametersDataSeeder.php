<?php

namespace Database\Seeders\Demo;

use App\Models\parameter;
use App\Services\DemoImageService;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ParametersDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure demo images are downloaded and extracted
        DemoImageService::ensureDemoImagesExist();

        $this->createFacilities();
    }

    public function createFacilities()
    {
        try {
            // Get available demo images
            $availableImages = DemoImageService::getAvailableImages('parameter_img');
            Log::info('Available parameter demo images', ['images' => $availableImages]);

            $facilities = [
                [
                    'name' => 'Bedroom',
                    'type_of_parameter' => 'number',
                    'type_values' => null,
                    'image' => null,
                    'demo_image_source' => 'parameter_img/Bedroom.svg',
                    'is_required' => 0,
                    'is_demo' => 1,
                ],
                [
                    'name' => 'Bathroom',
                    'type_of_parameter' => 'number',
                    'type_values' => null,
                    'image' => null,
                    'demo_image_source' => 'parameter_img/Bathroom.svg',
                    'is_required' => 0,
                    'is_demo' => 1,
                ],
                [
                    'name' => 'Kitchen',
                    'type_of_parameter' => 'number',
                    'type_values' => null,
                    'image' => null,
                    'demo_image_source' => 'parameter_img/Kitchen.svg',
                    'is_required' => 0,
                    'is_demo' => 1,
                ],
                [
                    'name' => 'Parking',
                    'type_of_parameter' => 'number',
                    'type_values' => null,
                    'image' => null,
                    'demo_image_source' => 'parameter_img/Parking.svg',
                    'is_required' => 0,
                    'is_demo' => 1,
                ],
                [
                    'name' => 'Area',
                    'type_of_parameter' => 'number',
                    'type_values' => null,
                    'image' => null,
                    'demo_image_source' => 'parameter_img/Area.svg',
                    'is_required' => 0,
                    'is_demo' => 1,
                ],
            ];

            if (! empty($facilities)) {
                foreach ($facilities as $facilityData) {
                    // Process demo image from demo folder through FileService
                    $demoImagePath = $facilityData['demo_image_source'] ?? null;
                    unset($facilityData['demo_image_source']);

                    if ($demoImagePath && DemoImageService::imageExists($demoImagePath)) {
                        // Process image through FileService (compression, optimization)
                        // Demo image from: storage/app/public/demo/parameter_img/bedroom.jpg
                        // Processed to: storage/app/public/parameter_img/[processed_name].jpg
                        $processedImage = DemoImageService::processImageWithFileService(
                            $demoImagePath, // e.g., 'parameter_img/bedroom.jpg'
                            config('global.PARAMETER_IMG_PATH', 'parameter_img'), // destination folder
                            false // No watermark for parameter images
                        );

                        if ($processedImage) {
                            $facilityData['image'] = $processedImage;
                            Log::info('Processed parameter demo image');
                        }
                    } else {
                        Log::error('Parameter demo image not found', [
                            'parameter' => $facilityData['name'],
                            'source' => $demoImagePath,
                        ]);
                    }

                    parameter::updateOrCreate(
                        ['name' => $facilityData['name'], 'is_demo' => 1],
                        $facilityData
                    );
                }
            }
        } catch (Exception $e) {
            Log::error('Error parameter data seeder : '.$e->getMessage());
        }
    }
}
