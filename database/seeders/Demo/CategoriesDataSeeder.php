<?php

namespace Database\Seeders\Demo;

use App\Models\Category;
use App\Models\parameter;
use App\Services\DemoImageService;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class CategoriesDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure demo images are downloaded and extracted
        DemoImageService::ensureDemoImagesExist();

        $this->createCategories();
    }

    public function createCategories()
    {
        try {
            $facilities = parameter::where('is_demo', 1)->get();
            $parameterIdForVilla = [];
            $parameterIdForHouse = [];
            $parameterIdForApartment = [];
            $parameterIdForCommercial = [];
            $parameterIdForPlot = [];
            foreach ($facilities as $facility) {
                if ($facility->name == 'Bedroom') {
                    $parameterIdForVilla[] = $facility->id;
                    $parameterIdForHouse[] = $facility->id;
                    $parameterIdForApartment[] = $facility->id;
                }
                if ($facility->name == 'Bathroom') {
                    $parameterIdForVilla[] = $facility->id;
                    $parameterIdForHouse[] = $facility->id;
                    $parameterIdForApartment[] = $facility->id;
                }
                if ($facility->name == 'Kitchen') {
                    $parameterIdForVilla[] = $facility->id;
                    $parameterIdForHouse[] = $facility->id;
                    $parameterIdForApartment[] = $facility->id;
                }
                if ($facility->name == 'Parking') {
                    $parameterIdForVilla[] = $facility->id;
                    $parameterIdForHouse[] = $facility->id;
                    $parameterIdForApartment[] = $facility->id;
                    $parameterIdForCommercial[] = $facility->id;
                }
                if ($facility->name == 'Area') {
                    $parameterIdForVilla[] = $facility->id;
                    $parameterIdForHouse[] = $facility->id;
                    $parameterIdForApartment[] = $facility->id;
                    $parameterIdForCommercial[] = $facility->id;
                    $parameterIdForPlot[] = $facility->id;
                }
            }

            // Get available demo images
            $availableImages = DemoImageService::getAvailableImages('category');
            Log::info('Available category demo images', ['images' => $availableImages]);

            $categories = [
                [
                    'category' => 'Villa',
                    'parameter_types' => implode(',', $parameterIdForVilla),
                    'image' => '',
                    'demo_image_source' => 'category/Villa.svg',
                    'status' => 1,
                    'sequence' => 0,
                    'slug_id' => 'villa',
                    'meta_title' => 'Villa',
                    'meta_keywords' => 'Lifestyle Homes,Luxury Residences,Investment Properties,Family-Friendly Homes,Urban Living Spaces',
                    'meta_description' => 'Explore our diverse range of villas, curated for every lifestyle. From luxurious residences to charming investment opportunities.',
                    'is_demo' => 1,
                ],
                [
                    'category' => 'House',
                    'parameter_types' => implode(',', $parameterIdForHouse),
                    'image' => '',
                    'demo_image_source' => 'category/House.svg',
                    'status' => 1,
                    'sequence' => 0,
                    'slug_id' => 'house',
                    'meta_title' => 'House',
                    'meta_keywords' => 'Family Homes,Residential Properties,Modern Houses,Suburban Living',
                    'meta_description' => 'Discover comfortable houses perfect for families and individuals looking for a place to call home.',
                    'is_demo' => 1,
                ],
                [
                    'category' => 'Apartment',
                    'parameter_types' => implode(',', $parameterIdForApartment),
                    'image' => '',
                    'demo_image_source' => 'category/Apartment.svg',
                    'status' => 1,
                    'sequence' => 0,
                    'slug_id' => 'apartment',
                    'meta_title' => 'Apartment',
                    'meta_keywords' => 'Urban Living,City Apartments,Modern Flats,Residential Units',
                    'meta_description' => 'Find the perfect apartment in prime locations with modern amenities and great connectivity.',
                    'is_demo' => 1,
                ],
                [
                    'category' => 'Commercial',
                    'parameter_types' => implode(',', $parameterIdForCommercial),
                    'image' => '',
                    'demo_image_source' => 'category/Commercial.svg',
                    'status' => 1,
                    'sequence' => 0,
                    'slug_id' => 'commercial',
                    'meta_title' => 'Commercial',
                    'meta_keywords' => 'Commercial Properties,Office Spaces,Retail Spaces,Business Properties',
                    'meta_description' => 'Explore commercial properties ideal for businesses, offices, and retail establishments.',
                    'is_demo' => 1,
                ],
                [
                    'category' => 'Plot',
                    'parameter_types' => implode(',', $parameterIdForPlot),
                    'image' => '',
                    'demo_image_source' => 'category/Plot.svg',
                    'status' => 1,
                    'sequence' => 0,
                    'slug_id' => 'plot',
                    'meta_title' => 'Plot',
                    'meta_keywords' => 'Land,Plot,Investment Land,Residential Plot,Commercial Plot',
                    'meta_description' => 'Invest in prime plots of land perfect for building your dream property or investment purposes.',
                    'is_demo' => 1,
                ],
            ];

            foreach ($categories as $categoryData) {
                // Slug Generate
                $categoryData['slug_id'] = generateUniqueSlug($categoryData['category'], 3, $categoryData['slug_id']);

                // Process demo image from demo folder through FileService
                $demoImagePath = $categoryData['demo_image_source'] ?? null;
                unset($categoryData['demo_image_source']);

                if ($demoImagePath && DemoImageService::imageExists($demoImagePath)) {
                    // Process image through FileService (compression, optimization)
                    // Demo image from: storage/app/public/demo/category/villa.jpg
                    // Processed to: storage/app/public/category/[processed_name].jpg
                    $processedImage = DemoImageService::processImageWithFileService(
                        $demoImagePath, // e.g., 'category/villa.jpg'
                        config('global.CATEGORY_IMG_PATH', 'category'), // destination folder
                        false // No watermark for category images
                    );

                    if ($processedImage) {
                        $categoryData['image'] = $processedImage;
                        Log::info('Processed category demo image');
                    }
                } else {
                    Log::error('Category demo image not found', [
                        'category' => $categoryData['category'],
                        'source' => $demoImagePath,
                    ]);
                }

                Category::updateOrCreate(
                    ['category' => $categoryData['category'], 'is_demo' => 1],
                    $categoryData
                );
            }
        } catch (Exception $e) {
            Log::error('Error in category data seeder: '.$e->getMessage());
            throw $e;
        }
    }
}
