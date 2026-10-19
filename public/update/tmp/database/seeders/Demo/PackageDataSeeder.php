<?php

namespace Database\Seeders\Demo;

use App\Models\Customer;
use App\Models\Feature;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\UserPackage;
use App\Models\UserPackageLimit;
use App\Services\HelperService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class PackageDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createPackages();
    }

    /** Add Packages Data */
    public function createPackages()
    {
        /** Packages Data */
        $packageLastId = Package::latest()->pluck('id')->first();
        if (empty($packageLastId)) {
            $packageLastId = 0;
        }

        $this->createPackage($packageLastId);

        // Get Last 3 Packages ID
        $packagesId = Package::latest()->limit(3)->pluck('id')->toArray();

        /** Features Data */
        $this->createFeatures();

        /** Package Features Data */
        $this->createPackageFeatures($packagesId);

        /** User Packages Data */
        $this->createUserPackages($packagesId);

    }

    /** Packages Demo Functions */
    // Create Packages
    private function createPackage($packageLastId)
    {
        $packageData = [
            [
                'id' => $packageLastId + 1,
                'name' => 'Free Listing and Feature',
                'package_type' => 'free',
                'duration' => 240,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $packageLastId + 2,
                'name' => 'Only Premium Features',
                'package_type' => 'free',
                'duration' => 240,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $packageLastId + 3,
                'name' => 'All Features Unlimited',
                'package_type' => 'free',
                'duration' => 120,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        Package::upsert($packageData, ['id'], ['name', 'package_type', 'duration', 'status']);
    }

    // Create Features
    private function createFeatures()
    {
        // Check All Features Exists
        $featureNames = HelperService::getFeatureNames();
        $featureTypes = collect($featureNames)->pluck('TYPE')->toArray();
        $featuresQuery = Feature::whereIn('type', $featureTypes);
        $featuresCount = $featuresQuery->count();
        $features = $featuresQuery->get();

        if (empty($features) || $featuresCount != count($featureNames)) {
            Log::error('No features found');
            /** Add Data */
            $featureData = collect($featureNames)->map(function ($feature, $index) {
                $userType = in_array($feature['TYPE'], [
                    config('constants.FEATURES.MORTGAGE_CALCULATOR_DETAIL.TYPE'),
                    config('constants.FEATURES.PREMIUM_PROPERTIES.TYPE'),
                    config('constants.FEATURES.PREMIUM_PROJECTS.TYPE'),
                ]) ? 'user' : 'all';

                if ($feature['TYPE'] === config('constants.FEATURES.AGENT_WATERMARK.TYPE')) {
                    $userType = 'agent';
                }

                return [
                    'id' => $index + 1,
                    'name' => $feature['NAME'],
                    'type' => $feature['TYPE'],
                    'status' => 1,
                    'user_type' => $userType,
                ];
            })->toArray();
            Feature::upsert($featureData, ['id'], ['name', 'type', 'status', 'user_type']);
            $features = Feature::get();
        }
    }

    /** Add Package Features Data */
    private function createPackageFeatures($packagesId)
    {

        $packageFeaturesData = [];
        foreach ($packagesId as $key => $packageId) {
            if ($key == 0) {
                $packageFeaturesData = array_merge($packageFeaturesData, [
                    [
                        'package_id' => $packageId,
                        'feature_id' => HelperService::getFeatureId('property_list'),
                        'limit_type' => 'limited',
                        'limit' => 5,
                    ],
                    [
                        'package_id' => $packageId,
                        'feature_id' => HelperService::getFeatureId('project_list'),
                        'limit_type' => 'limited',
                        'limit' => 5,
                    ],
                    [
                        'package_id' => $packageId,
                        'feature_id' => HelperService::getFeatureId('property_feature'),
                        'limit_type' => 'limited',
                        'limit' => 5,
                    ],
                    [
                        'package_id' => $packageId,
                        'feature_id' => HelperService::getFeatureId('project_feature'),
                        'limit_type' => 'limited',
                        'limit' => 5,
                    ],
                ]);
            } elseif ($key == 1) {
                $packageFeaturesData = array_merge($packageFeaturesData, [
                    [
                        'package_id' => $packageId,
                        'feature_id' => HelperService::getFeatureId('mortgage_calculator_detail'),
                        'limit_type' => 'unlimited',
                        'limit' => null,
                    ],
                    [
                        'package_id' => $packageId,
                        'feature_id' => HelperService::getFeatureId('premium_properties'),
                        'limit_type' => 'unlimited',
                        'limit' => null,
                    ],
                    [
                        'package_id' => $packageId,
                        'feature_id' => HelperService::getFeatureId('premium_projects'),
                        'limit_type' => 'unlimited',
                        'limit' => null,
                    ],
                ]);
            } else {
                $packageFeaturesData = array_merge($packageFeaturesData, [
                    [
                        'package_id' => $packageId,
                        'feature_id' => HelperService::getFeatureId('property_list'),
                        'limit_type' => 'unlimited',
                        'limit' => null,
                    ],
                    [
                        'package_id' => $packageId,
                        'feature_id' => HelperService::getFeatureId('project_list'),
                        'limit_type' => 'unlimited',
                        'limit' => null,
                    ],
                    [
                        'package_id' => $packageId,
                        'feature_id' => HelperService::getFeatureId('property_feature'),
                        'limit_type' => 'unlimited',
                        'limit' => null,
                    ],
                    [
                        'package_id' => $packageId,
                        'feature_id' => HelperService::getFeatureId('project_feature'),
                        'limit_type' => 'unlimited',
                        'limit' => null,
                    ],
                    [
                        'package_id' => $packageId,
                        'feature_id' => HelperService::getFeatureId('mortgage_calculator_detail'),
                        'limit_type' => 'unlimited',
                        'limit' => null,
                    ],
                    [
                        'package_id' => $packageId,
                        'feature_id' => HelperService::getFeatureId('premium_properties'),
                        'limit_type' => 'unlimited',
                        'limit' => null,
                    ],
                    [
                        'package_id' => $packageId,
                        'feature_id' => HelperService::getFeatureId('premium_projects'),
                        'limit_type' => 'unlimited',
                        'limit' => null,
                    ],
                ]);
            }
        }
        PackageFeature::upsert($packageFeaturesData, ['package_id', 'feature_id'], ['limit_type', 'limit']);
    }

    /** Add User Packages Data */
    private function createUserPackages($packagesId)
    {
        // Get Last 3 Users ID
        $lastUsersID = Customer::latest()->limit(3)->pluck('id')->toArray();

        // Create User Package Data
        $userPackagesData = [];
        foreach ($lastUsersID as $key => $userId) {
            $selectedPackageId = $packagesId[rand(0, 2)]; // Get Random Package ID
            $getPackage = Package::find($selectedPackageId); // Get Package Data

            // Create Or Update User Package Data
            $userPackagesData = [
                'user_id' => $userId,
                'package_id' => $selectedPackageId,
                'start_date' => now(),
                'end_date' => now()->addDays($getPackage->duration),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $userPackage = UserPackage::updateOrCreate(['user_id' => $userId, 'package_id' => $selectedPackageId], $userPackagesData);

            // Get Limited Count Feature
            $packageFeatures = PackageFeature::where(['package_id' => $selectedPackageId, 'limit_type' => 'limited'])->get();

            // If Limited Count Feature Found Then Create User Package Limit Data
            if (collect($packageFeatures)->isNotEmpty()) {
                $userPackageLimitData = [];
                foreach ($packageFeatures as $key => $feature) {
                    $userPackageLimitData[] = [
                        'user_package_id' => $userPackage->id,
                        'package_feature_id' => $feature->id,
                        'total_limit' => $feature->limit,
                        'used_limit' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (! empty($userPackageLimitData)) {
                    UserPackageLimit::insert($userPackageLimitData);
                }
            }
        }
    }
}
