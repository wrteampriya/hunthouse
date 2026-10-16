<?php

namespace Database\Seeders\Demo;

use App\Models\AssignParameters;
use App\Models\Category;
use App\Models\parameter;
use App\Models\Property;
use App\Models\PropertyImages;
use App\Services\DemoImageService;
use App\Services\HelperService;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class PropertiesDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure demo images are downloaded and extracted
        DemoImageService::ensureDemoImagesExist();

        $this->createProperties();
    }

    private function createProperties(): void
    {
        try {
            // 🌍 Admin base location
            $adminData = HelperService::getMultipleSettingData([
                'company_address',
                'latitude',
                'longitude',
            ]);
            $adminAddress = $adminData['company_address'] ?? '123 Demo Street';
            $adminLatitude = $adminData['latitude'] ?? 40.7128;
            $adminLongitude = $adminData['longitude'] ?? -74.0060;

            // Categories
            $villaCategoryId = Category::where(['category' => 'Villa', 'is_demo' => 1])->first()->id;
            $houseCategoryId = Category::where(['category' => 'House', 'is_demo' => 1])->first()->id;
            $apartmentCategoryId = Category::where(['category' => 'Apartment', 'is_demo' => 1])->first()->id;
            $commercialCategoryId = Category::where(['category' => 'Commercial', 'is_demo' => 1])->first()->id;
            $plotCategoryId = Category::where(['category' => 'Plot', 'is_demo' => 1])->first()->id;

            // If Any of category is empty then populate category demo data and get id
            if (empty($villaCategoryId) || empty($houseCategoryId) || empty($apartmentCategoryId) || empty($commercialCategoryId) || empty($plotCategoryId)) {
                Artisan::call('db:seed --class=\Database\Seeders\Demo\CategoriesDataSeeder');
                $villaCategoryId = Category::where(['category' => 'Villa', 'is_demo' => 1])->first()->id;
                $houseCategoryId = Category::where(['category' => 'House', 'is_demo' => 1])->first()->id;
                $apartmentCategoryId = Category::where(['category' => 'Apartment', 'is_demo' => 1])->first()->id;
                $commercialCategoryId = Category::where(['category' => 'Commercial', 'is_demo' => 1])->first()->id;
                $plotCategoryId = Category::where(['category' => 'Plot', 'is_demo' => 1])->first()->id;
            }

            // Parameters
            $bedroomParameterId = parameter::where(['name' => 'Bedroom', 'is_demo' => 1])->first()->id;
            $bathroomParameterId = parameter::where(['name' => 'Bathroom', 'is_demo' => 1])->first()->id;
            $kitchenParameterId = parameter::where(['name' => 'Kitchen', 'is_demo' => 1])->first()->id;
            $parkingParameterId = parameter::where(['name' => 'Parking', 'is_demo' => 1])->first()->id;
            $areaParameterId = parameter::where(['name' => 'Area', 'is_demo' => 1])->first()->id;

            // If Any of parameter is empty then populate parameter demo data and get id
            if (empty($bedroomParameterId) || empty($bathroomParameterId) || empty($kitchenParameterId) || empty($parkingParameterId) || empty($areaParameterId)) {
                Artisan::call('db:seed --class=\Database\Seeders\Demo\ParametersDataSeeder');
                $bedroomParameterId = parameter::where(['name' => 'Bedroom', 'is_demo' => 1])->first()->id;
                $bathroomParameterId = parameter::where(['name' => 'Bathroom', 'is_demo' => 1])->first()->id;
                $kitchenParameterId = parameter::where(['name' => 'Kitchen', 'is_demo' => 1])->first()->id;
                $parkingParameterId = parameter::where(['name' => 'Parking', 'is_demo' => 1])->first()->id;
                $areaParameterId = parameter::where(['name' => 'Area', 'is_demo' => 1])->first()->id;
            }

            // Properties
            $properties = [
                [
                    'category_id' => $villaCategoryId,
                    'package_id' => null,
                    'title' => 'Luxury Sunset Villa',
                    'description' => 'Experience luxury living in this stunning villa with breathtaking sunset views. Features modern architecture, spacious rooms, and premium finishes throughout.',
                    'address' => '123 Palm Boulevard, Beverly Hills',
                    'propery_type' => 0, // Sell
                    'price' => 2500000,
                    'post_type' => '0', // Admin
                    'city' => 'Los Angeles',
                    'country' => 'United States',
                    'state' => 'California',
                    'title_image' => 'Luxury Sunset Villa.png',
                    'three_d_image' => '',
                    'video_link' => '',
                    'latitude' => '34.0736',
                    'longitude' => '-118.4004',
                    'added_by' => 0, // Admin
                    'status' => 1,
                    'request_status' => 'approved',
                    'total_click' => 0,
                    'slug_id' => 'luxury-sunset-villa',
                    'meta_title' => 'Luxury Sunset Villa - Premium Property',
                    'meta_description' => 'Experience luxury living in this stunning villa with breathtaking sunset views.',
                    'meta_keywords' => 'villa, luxury, sunset, modern, premium',
                    'is_premium' => 0,
                    'parameters' => [
                        ['parameter_id' => $bedroomParameterId, 'value' => 5], // Bedroom
                        ['parameter_id' => $bathroomParameterId, 'value' => 4], // Bathroom
                        ['parameter_id' => $kitchenParameterId, 'value' => 2], // Kitchen
                        ['parameter_id' => $parkingParameterId, 'value' => 3], // Parking
                        ['parameter_id' => $areaParameterId, 'value' => 6500], // Area
                    ],
                    'gallery_images' => ['Living Room.jpg', 'Kitchen.jpg', 'Bedroom.jpg'],
                    'is_demo' => 1,
                ],
                [
                    'category_id' => $houseCategoryId,
                    'package_id' => null,
                    'title' => 'Modern Family House',
                    'description' => 'Perfect family home with spacious rooms, beautiful garden, and located in a peaceful neighborhood. Close to schools and shopping centers.',
                    'address' => '456 Oak Street, Suburban Area',
                    'propery_type' => 0, // Sell
                    'price' => 850000,
                    'post_type' => '0', // Admin
                    'city' => 'Austin',
                    'country' => 'United States',
                    'state' => 'Texas',
                    'title_image' => 'Modern Family House.png',
                    'three_d_image' => '',
                    'video_link' => '',
                    'latitude' => '30.2672',
                    'longitude' => '-97.7431',
                    'added_by' => 0,
                    'status' => 1,
                    'request_status' => 'approved',
                    'total_click' => 0,
                    'slug_id' => 'modern-family-house',
                    'meta_title' => 'Modern Family House - Comfortable Living',
                    'meta_description' => 'Perfect family home with spacious rooms and beautiful garden.',
                    'meta_keywords' => 'house, family, modern, garden, suburban',
                    'is_premium' => 0,
                    'parameters' => [
                        ['parameter_id' => $bedroomParameterId, 'value' => 4],
                        ['parameter_id' => $bathroomParameterId, 'value' => 3],
                        ['parameter_id' => $kitchenParameterId, 'value' => 1],
                        ['parameter_id' => $parkingParameterId, 'value' => 2],
                        ['parameter_id' => $areaParameterId, 'value' => 3200],
                    ],
                    'gallery_images' => ['Living Room.jpg', 'Kitchen.jpg', 'Bedroom.jpg'],
                    'is_demo' => 1,
                ],
                [
                    'category_id' => $apartmentCategoryId,
                    'package_id' => null,
                    'title' => 'Downtown Luxury Apartment',
                    'description' => 'Stylish apartment in the heart of downtown with stunning city views. Walking distance to restaurants, shops, and entertainment.',
                    'address' => '789 City Center Plaza, Unit 1502',
                    'propery_type' => 1, // Rent
                    'rentduration' => 'Monthly',
                    'price' => 3500,
                    'post_type' => '0',
                    'city' => 'New York',
                    'country' => 'United States',
                    'state' => 'New York',
                    'title_image' => 'Downtown Luxury Apartment.png',
                    'three_d_image' => '',
                    'video_link' => '',
                    'latitude' => '40.7128',
                    'longitude' => '-74.0060',
                    'added_by' => 0,
                    'status' => 1,
                    'request_status' => 'approved',
                    'total_click' => 0,
                    'slug_id' => 'downtown-luxury-apartment',
                    'meta_title' => 'Downtown Luxury Apartment - City Living',
                    'meta_description' => 'Stylish apartment in the heart of downtown with stunning city views.',
                    'meta_keywords' => 'apartment, downtown, luxury, city view, modern',
                    'is_premium' => 1,
                    'parameters' => [
                        ['parameter_id' => $bedroomParameterId, 'value' => 2],
                        ['parameter_id' => $bathroomParameterId, 'value' => 2],
                        ['parameter_id' => $kitchenParameterId, 'value' => 1],
                        ['parameter_id' => $parkingParameterId, 'value' => 1],
                        ['parameter_id' => $areaParameterId, 'value' => 1200],
                    ],
                    'gallery_images' => ['Living Room.jpg', 'Kitchen.jpg', 'Bedroom.jpg'],
                    'is_demo' => 1,
                ],
                [
                    'category_id' => $commercialCategoryId,
                    'package_id' => null,
                    'title' => 'Prime Office Space',
                    'description' => 'Modern office space in premium business district. Perfect for startups and established businesses. High-speed internet and modern amenities included.',
                    'address' => '321 Business Park Drive',
                    'propery_type' => 1, // Rent
                    'rentduration' => 'Monthly',
                    'price' => 8000,
                    'post_type' => '0',
                    'city' => 'San Francisco',
                    'country' => 'United States',
                    'state' => 'California',
                    'title_image' => 'Prime Office Space.png',
                    'three_d_image' => '',
                    'video_link' => '',
                    'latitude' => '37.7749',
                    'longitude' => '-122.4194',
                    'added_by' => 0,
                    'status' => 1,
                    'request_status' => 'approved',
                    'total_click' => 0,
                    'slug_id' => 'prime-office-space',
                    'meta_title' => 'Prime Office Space - Business District',
                    'meta_description' => 'Modern office space in premium business district.',
                    'meta_keywords' => 'commercial, office, business, modern, premium',
                    'is_premium' => 0,
                    'parameters' => [
                        ['parameter_id' => $kitchenParameterId, 'value' => 1],
                        ['parameter_id' => $parkingParameterId, 'value' => 10],
                        ['parameter_id' => $areaParameterId, 'value' => 5000],
                    ],
                    'gallery_images' => ['Living Room.jpg', 'Kitchen.jpg', 'Bedroom.jpg'],
                    'is_demo' => 1,
                ],
                [
                    'category_id' => $plotCategoryId,
                    'package_id' => null,
                    'title' => 'Residential Plot - Prime Location',
                    'description' => 'Excellent investment opportunity! Prime residential plot in developing area with all utilities available. Perfect for building your dream home.',
                    'address' => '555 Development Avenue',
                    'propery_type' => 0, // Sell
                    'price' => 450000,
                    'post_type' => '0',
                    'city' => 'Phoenix',
                    'country' => 'United States',
                    'state' => 'Arizona',
                    'title_image' => 'Residential Plot - Prime Location.png',
                    'three_d_image' => '',
                    'video_link' => '',
                    'latitude' => '33.4484',
                    'longitude' => '-112.0740',
                    'added_by' => 0,
                    'status' => 1,
                    'request_status' => 'approved',
                    'total_click' => 0,
                    'slug_id' => 'residential-plot-prime-location',
                    'meta_title' => 'Residential Plot - Prime Location',
                    'meta_description' => 'Prime residential plot in developing area with all utilities available.',
                    'meta_keywords' => 'plot, land, residential, investment, development',
                    'is_premium' => 0,
                    'parameters' => [
                        ['parameter_id' => $areaParameterId, 'value' => 8000],
                    ],
                    'gallery_images' => ['Living Room.jpg', 'Kitchen.jpg', 'Bedroom.jpg'],
                    'is_demo' => 1,
                ],
                [
                    'category_id' => $villaCategoryId,
                    'package_id' => null,
                    'title' => 'Beachfront Paradise Villa',
                    'description' => 'Wake up to ocean views every day! This magnificent beachfront villa offers direct beach access, infinity pool, and luxurious amenities.',
                    'address' => '888 Coastal Highway',
                    'propery_type' => 0, // Sell
                    'price' => 3200000,
                    'post_type' => '0',
                    'city' => 'Miami',
                    'country' => 'United States',
                    'state' => 'Florida',
                    'title_image' => 'Beachfront Paradise Villa.jpg',
                    'three_d_image' => '',
                    'video_link' => '',
                    'latitude' => '25.7617',
                    'longitude' => '-80.1918',
                    'added_by' => 0,
                    'status' => 1,
                    'request_status' => 'approved',
                    'total_click' => 0,
                    'slug_id' => 'beachfront-paradise-villa',
                    'meta_title' => 'Beachfront Paradise Villa - Ocean Views',
                    'meta_description' => 'Magnificent beachfront villa with direct beach access and infinity pool.',
                    'meta_keywords' => 'villa, beachfront, ocean, luxury, paradise',
                    'is_premium' => 1,
                    'parameters' => [
                        ['parameter_id' => $bedroomParameterId, 'value' => 6],
                        ['parameter_id' => $bathroomParameterId, 'value' => 5],
                        ['parameter_id' => $kitchenParameterId, 'value' => 2],
                        ['parameter_id' => $parkingParameterId, 'value' => 4],
                        ['parameter_id' => $areaParameterId, 'value' => 7500],
                    ],
                    'gallery_images' => ['Living Room.jpg', 'Kitchen.jpg', 'Bedroom.jpg'],
                    'is_demo' => 1,
                ],
                [
                    'category_id' => $houseCategoryId,
                    'package_id' => null,
                    'title' => 'Cozy Cottage House',
                    'description' => 'Charming cottage-style house perfect for small families or couples. Features a beautiful garden, fireplace, and peaceful surroundings.',
                    'address' => '234 Maple Lane',
                    'propery_type' => 1, // Rent,
                    'rentduration' => 'Yearly',
                    'price' => 2200,
                    'post_type' => '0',
                    'city' => 'Portland',
                    'country' => 'United States',
                    'state' => 'Oregon',
                    'title_image' => 'Cozy Cottage House.png',
                    'three_d_image' => '',
                    'video_link' => '',
                    'latitude' => '45.5152',
                    'longitude' => '-122.6784',
                    'added_by' => 0,
                    'status' => 1,
                    'request_status' => 'approved',
                    'total_click' => 0,
                    'slug_id' => 'cozy-cottage-house',
                    'meta_title' => 'Cozy Cottage House - Peaceful Living',
                    'meta_description' => 'Charming cottage-style house with beautiful garden and fireplace.',
                    'meta_keywords' => 'house, cottage, cozy, garden, peaceful',
                    'is_premium' => 0,
                    'parameters' => [
                        ['parameter_id' => $bedroomParameterId, 'value' => 3],
                        ['parameter_id' => $bathroomParameterId, 'value' => 2],
                        ['parameter_id' => $kitchenParameterId, 'value' => 1],
                        ['parameter_id' => $parkingParameterId, 'value' => 2],
                        ['parameter_id' => $areaParameterId, 'value' => 1800],
                    ],
                    'gallery_images' => ['Living Room.jpg', 'Kitchen.jpg', 'Bedroom.jpg'],
                    'is_demo' => 1,
                ],
                [
                    'category_id' => $apartmentCategoryId,
                    'package_id' => null,
                    'title' => 'Modern Studio Apartment',
                    'description' => 'Efficient and stylish studio apartment perfect for young professionals. Fully furnished with modern appliances and great location.',
                    'address' => '567 Urban Street, Apt 8B',
                    'propery_type' => 1, // Rent,
                    'rentduration' => 'Monthly',
                    'price' => 1800,
                    'post_type' => '0',
                    'city' => 'Seattle',
                    'country' => 'United States',
                    'state' => 'Washington',
                    'title_image' => 'Modern Studio Apartment.png',
                    'three_d_image' => '',
                    'video_link' => '',
                    'latitude' => '47.6062',
                    'longitude' => '-122.3321',
                    'added_by' => 0,
                    'status' => 1,
                    'request_status' => 'approved',
                    'total_click' => 0,
                    'slug_id' => 'modern-studio-apartment',
                    'meta_title' => 'Modern Studio Apartment - Urban Living',
                    'meta_description' => 'Efficient and stylish studio apartment for young professionals.',
                    'meta_keywords' => 'apartment, studio, modern, urban, furnished',
                    'is_premium' => 0,
                    'parameters' => [
                        ['parameter_id' => $bedroomParameterId, 'value' => 1],
                        ['parameter_id' => $bathroomParameterId, 'value' => 1],
                        ['parameter_id' => $kitchenParameterId, 'value' => 1],
                        ['parameter_id' => $parkingParameterId, 'value' => 1],
                        ['parameter_id' => $areaParameterId, 'value' => 600],
                    ],
                    'gallery_images' => ['Living Room.jpg', 'Kitchen.jpg', 'Bedroom.jpg'],
                    'is_demo' => 1,
                ],
            ];

            // Get available demo images for properties
            $availablePropertyImages = DemoImageService::getAvailableImages('property_title_img');
            Log::info('Available property demo images', ['images' => $availablePropertyImages]);

            foreach ($properties as $property) {
                // Slug Generate
                $property['slug_id'] = generateUniqueSlug($property['title'], 1, $property['slug_id']);

                $coords = $this->getRandomNearbyCoordinates($adminLatitude, $adminLongitude);
                if ($coords) {
                    $property['latitude'] = $coords['latitude'];
                    $property['longitude'] = $coords['longitude'];
                }

                // Reverse geocode (OpenStreetMap)
                $geo = $this->reverseGeocode($property['latitude'], $property['longitude']);
                if ($geo) {
                    $property['address'] = $geo['address'];
                    $property['city'] = $geo['city'];
                    $property['state'] = $geo['state'];
                    $property['country'] = $geo['country'];
                    $property['client_address'] = $adminAddress;
                }

                // Title Image
                $titleImage = $property['title_image'];
                unset($property['title_image']);

                // Gallery Images
                $galleryImages = $property['gallery_images'];
                unset($property['gallery_images']);

                // Parameters
                $parameters = $property['parameters'];
                unset($property['parameters']);

                // Process demo image from demo folder through FileService with watermark
                if (! empty($titleImage)) {
                    $demoTitleImageFilename = $titleImage;
                    $demoTitleImagePath = 'property_title_img/'.$demoTitleImageFilename;

                    if (DemoImageService::imageExists($demoTitleImagePath)) {
                        // Process image through FileService (compression, watermark, optimization)
                        // Demo image from: storage/app/public/demo/property_title_img/demo_villa_1.jpg
                        // Processed to: storage/app/public/property_title_img/[processed_name].jpg
                        $processedImage = DemoImageService::processImageWithFileService(
                            $demoTitleImagePath, // e.g., 'property_title_img/demo_villa_1.jpg'
                            config('global.PROPERTY_TITLE_IMG_PATH', 'property_title_img'), // destination folder
                            true // Add watermark for property images
                        );

                        if ($processedImage) {
                            $property['title_image'] = $processedImage;
                            Log::info('Processed property title demo image');
                        }
                    } else {
                        Log::error('Property title demo image not found', [
                            'property' => $property['title'],
                            'source' => $demoTitleImagePath,
                        ]);
                    }
                }

                // Add timestamps
                $property['created_at'] = now();
                $property['updated_at'] = now();

                $propertyId = Property::insertGetId($property);
                // Insert property gallery images - process from demo folder through FileService with watermark
                if (isset($galleryImages)) {
                    foreach ($galleryImages as $imageFilename) {
                        $demoImagePath = 'property_gallery_img/'.$imageFilename;

                        if (DemoImageService::imageExists($demoImagePath)) {
                            // Process image through FileService (compression, watermark, optimization)
                            // Demo image from: storage/app/public/demo/property_gallery_img/demo_villa_1.jpg
                            // Processed to: storage/app/public/property_gallery_img/property_id/[processed_name].jpg
                            $destinationPath = config('global.PROPERTY_GALLERY_IMG_PATH', 'property_gallery_img').'/'.$propertyId.'/';
                            $processedGalleryImage = DemoImageService::processImageWithFileService(
                                $demoImagePath, // e.g., 'property_gallery_img/demo_villa_1.jpg'
                                $destinationPath, // destination folder
                                true // Add watermark for gallery images
                            );

                            if ($processedGalleryImage) {
                                PropertyImages::insert([
                                    'propertys_id' => $propertyId,
                                    'image' => $processedGalleryImage,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);

                                Log::info('Processed property gallery demo image');
                            }
                        } else {
                            Log::error('Property gallery demo image not found', [
                                'property' => $property['title'],
                                'source' => $demoImagePath,
                            ]);
                        }
                    }
                }

                if (isset($parameters)) {
                    // Insert property parameters
                    foreach ($parameters as $parameter) {
                        AssignParameters::insert([
                            'modal_type' => 'App\\Models\\Property',
                            'modal_id' => $propertyId,
                            'property_id' => 0,
                            'parameter_id' => $parameter['parameter_id'],
                            'value' => $parameter['value'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        } catch (Exception $e) {
            Log::error('Error in property data seeder: '.$e->getMessage());
            throw $e;
        }
    }

    /** Random coordinate generator */
    private function getRandomNearbyCoordinates(float $lat, float $lng, float $minKm = 5, float $maxKm = 10): array
    {
        $radius = 6371;
        $dist = mt_rand($minKm * 1000, $maxKm * 1000) / 1000;
        $bearing = deg2rad(mt_rand(0, 360));

        $lat1 = deg2rad($lat);
        $lng1 = deg2rad($lng);

        $lat2 = asin(sin($lat1) * cos($dist / $radius) +
            cos($lat1) * sin($dist / $radius) * cos($bearing));
        $lng2 = $lng1 + atan2(
            sin($bearing) * sin($dist / $radius) * cos($lat1),
            cos($dist / $radius) - sin($lat1) * sin($lat2)
        );

        return [
            'latitude' => round(rad2deg($lat2), 6),
            'longitude' => round(rad2deg($lng2), 6),
        ];
    }

    /** Free reverse geocoding using OpenStreetMap */
    private function reverseGeocode(float $lat, float $lng): ?array
    {
        try {
            $url = "https://nominatim.openstreetmap.org/reverse?lat={$lat}&lon={$lng}&format=json&zoom=18&addressdetails=1";
            $opts = ['http' => ['header' => "User-Agent: Laravel-DemoSeeder/1.0\r\n"]];
            $context = stream_context_create($opts);
            $resp = @file_get_contents($url, false, $context);
            if (! $resp) {
                return null;
            }
            $data = json_decode($resp, true);
            if (empty($data['address'])) {
                return null;
            }

            return [
                'address' => $data['display_name'] ?? null,
                'city' => $data['address']['city'] ?? ($data['address']['town'] ?? ($data['address']['village'] ?? null)),
                'state' => $data['address']['state'] ?? null,
                'country' => $data['address']['country'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error('Reverse geocode failed: '.$e->getMessage());

            return null;
        }
    }
}
