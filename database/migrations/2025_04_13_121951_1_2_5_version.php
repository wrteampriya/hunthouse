<?php

use App\Models\Feature;
use App\Models\Setting;
use App\Models\Customer;
use App\Models\HomepageSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Support\Facades\Schema;
use libphonenumber\NumberParseException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if the table exists before attempting to insert data
        if (Schema::hasTable('homepage_sections')) {

            // Need to use because changes in enum type is not supported in the migration file
            DB::statement("ALTER TABLE homepage_sections MODIFY COLUMN section_type ENUM(
                'agents_list_section',
                'articles_section',
                'categories_section',
                'faqs_section',
                'featured_properties_section',
                'featured_projects_section',
                'most_liked_properties_section',
                'most_viewed_properties_section',
                'nearby_properties_section',
                'projects_section',
                'premium_properties_section',
                'user_recommendations_section',
                'properties_by_cities_section',
                'properties_on_map_section'
            )");

            // Add New Properties on Map Section
            $homepageSections = [
                ['id' => 14, 'title' => "Find Homes, Apartments & More with Real-Time Listings on the Map", 'section_type' => 'properties_on_map_section', 'is_active' => true, 'sort_order' => 14],
            ];
            HomepageSection::upsert($homepageSections, ['id']);
            // Update Sort Order of All Sections
            $homepageData = array(
                [
                    'id' => 1,
                    'sort_order' => 5,
                ],
                [
                    'id' => 2,
                    'sort_order' => 13,
                ],
                [
                    'id' => 3,
                    'sort_order' => 2,
                ],
                [
                    'id' => 4,
                    'sort_order' => 14,
                ],
                [
                    'id' => 5,
                    'sort_order' => 3,
                ],
                [
                    'id' => 6,
                    'sort_order' => 9,
                ],
                [
                    'id' => 7,
                    'sort_order' => 10,
                ],
                [
                    'id' => 8,
                    'sort_order' => 4,
                ],
                [
                    'id' => 9,
                    'sort_order' => 1,
                ],
                [
                    'id' => 10,
                    'sort_order' => 8,
                ],
                [
                    'id' => 11,
                    'sort_order' => 11,
                ],
                [
                    'id' => 12,
                    'sort_order' => 6,
                ],
                [
                    'id' => 13,
                    'sort_order' => 12,
                ],
                [
                    'id' => 14,
                    'sort_order' => 7,
                ],
            );

            HomepageSection::upsert($homepageData, ['id'],['sort_order']);
        }
        /********************************************************************************* */

        /**
         * Article View Count
         */
        if (Schema::hasTable('articles') && !Schema::hasColumn('articles', 'view_count')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->integer('view_count')->default(0);
            });
        }

        /********************************************************************************* */

        /**
         * Features
         */
        if(Schema::hasTable('features') && !Schema::hasColumn('features','type')){
            Schema::table('features', function (Blueprint $table) {
                $table->enum('type',['property_list','project_list','property_feature','project_feature','mortgage_calculator_detail','premium_properties','project_access'])->nullable()->after('name');
            });
            Feature::get()->each(function($feature){
                switch($feature->name){
                    case !empty(config('constants.FEATURES.PROPERTY_LIST.NAME')) ? config('constants.FEATURES.PROPERTY_LIST.NAME') : config('constants.FEATURES.PROPERTY_LIST'):
                        $feature->type = 'property_list';
                        break;
                    case !empty(config('constants.FEATURES.PROJECT_LIST.NAME')) ? config('constants.FEATURES.PROJECT_LIST.NAME') : config('constants.FEATURES.PROJECT_LIST'):
                        $feature->type = 'project_list';
                        break;
                    case !empty(config('constants.FEATURES.PROPERTY_FEATURE.NAME')) ? config('constants.FEATURES.PROPERTY_FEATURE.NAME') : config('constants.FEATURES.PROPERTY_FEATURE'):
                        $feature->type = 'property_feature';
                        break;
                    case !empty(config('constants.FEATURES.PROJECT_FEATURE.NAME')) ? config('constants.FEATURES.PROJECT_FEATURE.NAME') : config('constants.FEATURES.PROJECT_FEATURE'):
                        $feature->type = 'project_feature';
                        break;
                    case !empty(config('constants.FEATURES.MORTGAGE_CALCULATOR_DETAIL.NAME')) ? config('constants.FEATURES.MORTGAGE_CALCULATOR_DETAIL.NAME') : config('constants.FEATURES.MORTGAGE_CALCULATOR_DETAIL'):
                        $feature->type = 'mortgage_calculator_detail';
                        break;
                    case !empty(config('constants.FEATURES.PREMIUM_PROPERTIES.NAME')) ? config('constants.FEATURES.PREMIUM_PROPERTIES.NAME') : config('constants.FEATURES.PREMIUM_PROPERTIES'):
                        $feature->type = 'premium_properties';
                        break;
                    case !empty(config('constants.FEATURES.PROJECT_ACCESS.NAME')) ? config('constants.FEATURES.PROJECT_ACCESS.NAME') : config('constants.FEATURES.PROJECT_ACCESS'):
                        $feature->type = 'project_access';
                        break;
                    default:
                        $feature->type = null;
                        break;
                }

                $feature->save();
            });
        }

        /********************************************************************************* */
        if(!Schema::hasTable('translations')){
            /**
             * Translations
             */
            Schema::create('translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('language_id')->constrained('languages')->onDelete('cascade'); // foreign key to languages table
                $table->string('key',100); // e.g. 'name', 'title', 'description'
                $table->text('value'); // actual translated text
                $table->morphs('translatable'); // translatable_id and translatable_type
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['language_id', 'key', 'translatable_id', 'translatable_type'], 'unique_translation');
            });
        }
        /********************************************************************************* */

        /**
         * Seo Settings
         */
        if(Schema::hasTable('seo_settings') && !Schema::hasColumn('seo_settings','schema_markup')){
            Schema::table('seo_settings', function (Blueprint $table) {
                $table->text('schema_markup')->nullable()->after('description');
            });
        }
        /********************************************************************************* */


        /**
         * Update .env file
         */
        $envData = [
            'QUEUE_CONNECTION' => 'database',
        ];
        updateEnv($envData);

        /********************************************************************************* */
        $settingsData = array(
            'homepage_location_alert_status' => 1,
        );
        foreach ($settingsData as $key => $settingData) {
            // Adding default data for verification required for user settings
            Setting::updateOrCreate(['type' => $key],['data' => $settingData]);
        }
        /********************************************************************************* */

        /**
         * Parameter
         */
        if(Schema::hasTable('parameters') && Schema::hasColumn('parameters','type_values')){
            Schema::table('parameters', function (Blueprint $table) {
                $table->longText('type_values')->nullable()->change();
            });
        }
        // No Need Rollback
        /********************************************************************************* */

        /**
         * Country Code in Customers
         */
        if(Schema::hasTable('customers') && !Schema::hasColumn('customers','full_mobile')){
            Schema::table('customers', function (Blueprint $table) {
                $table->renameColumn('mobile', 'full_mobile');
            });
        }
        if(Schema::hasTable('customers') && !Schema::hasColumn('customers','country_code')){
            Schema::table('customers', function (Blueprint $table) {
                $table->after('full_mobile',function($query){
                    $query->string('country_code', 10)->nullable();
                    $query->string('mobile',256)->nullable();
                });
            });

            
            /**
             * Drop old unique index on full_mobile + email + type
             */
            Schema::table('customers', function (Blueprint $table) {
                $table->dropUnique('unique_ids'); // Drop the existing unique index
            });

            /**
             * Add new unique index on mobile + email + type
             */
            Schema::table('customers', function (Blueprint $table) {
                $table->unique(['mobile', 'email', 'logintype','country_code'], 'unique_ids'); // Reuse same name
            });
        }

        // Manage Number
        $this->ManageNumber();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if the table exists before attempting to delete data
        if (Schema::hasTable('homepage_sections')) {
            HomepageSection::where('id', 14)->delete();
        }
        /********************************************************************************* */

        /**
         * Article View Count
         */
        if (Schema::hasTable('articles') && Schema::hasColumn('articles', 'view_count')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->dropColumn('view_count');
            });
        }
        /********************************************************************************* */
        Schema::dropIfExists('translations');
        /********************************************************************************* */
        if(Schema::hasTable('seo_settings') && Schema::hasColumn('seo_settings','schema_markup')){
            Schema::table('seo_settings', function (Blueprint $table) {
                $table->dropColumn('schema_markup');
            });
        }
        /********************************************************************************* */

        if(Schema::hasColumn('customers','full_mobile')){
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('country_code');
                $table->renameColumn('mobile', 'temp_mobile');
                $table->renameColumn('full_mobile', 'mobile');
            });

             /**
             * Drop old unique index on full_mobile + email + type
             */
            Schema::table('customers', function (Blueprint $table) {
                $table->dropUnique('unique_ids'); // Drop the existing unique index
            });
    
            // Recreate the original unique index on full_mobile, email, type
            Schema::table('customers', function (Blueprint $table) {
                $table->unique(['mobile', 'email', 'logintype'], 'unique_ids');
            });

            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('temp_mobile');
            });
        };

        /********************************************************************************* */
    }

    public function ManageNumber()
    {
        $users = Customer::whereNotNull('full_mobile')->get();
        $phoneUtil = PhoneNumberUtil::getInstance();
    
        foreach ($users as $user) {
            $raw = trim($user->full_mobile);
            $raw = preg_replace('/[^\d\+]/', '', $raw); // Keep digits and + only
    
            // Try to parse with "+" if not present and number looks long
            if (!str_starts_with($raw, '+') && preg_match('/^\d{11,15}$/', $raw)) {
                $raw = '+' . $raw;
            }
    
            try {
                $proto = $phoneUtil->parse($raw, null); // autodetect region
    
                if (!$phoneUtil->isValidNumber($proto)) {
                    Log::warning("⚠️ Invalid number after parsing: {$user->full_mobile} (User ID: {$user->id})");
                    continue;
                }
    
                $user->country_code = $proto->getCountryCode();           // e.g. 91
                $user->mobile = $proto->getNationalNumber();              // e.g. 9876543210
                $user->save();
    
            } catch (NumberParseException $e) {
                Log::warning("❌ Could not parse number: {$user->full_mobile} (User ID: {$user->id})");
                continue;
            }
        }
    
        Log::info("✅ Phone number migration completed.");
    }

};
