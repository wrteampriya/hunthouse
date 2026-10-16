<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('agent_profiles')) {
            Schema::table('agent_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('agent_profiles', 'linkedin_id')) {
                    $table->string('linkedin_id')->nullable()->after('instagram_id');
                }

                if (! Schema::hasColumn('agent_profiles', 'agent_banner')) {
                    $table->string('agent_banner')->nullable()->after('about_me');
                }

                if (! Schema::hasColumn('agent_profiles', 'watermark_enabled')) {
                    $table->boolean('watermark_enabled')->default(false)->after('agent_banner');
                }

                if (! Schema::hasColumn('agent_profiles', 'watermark_image')) {
                    $table->string('watermark_image')->nullable()->after('watermark_enabled');
                }

                if (! Schema::hasColumn('agent_profiles', 'watermark_opacity')) {
                    $table->unsignedTinyInteger('watermark_opacity')->default(25)->after('watermark_image');
                }

                if (! Schema::hasColumn('agent_profiles', 'watermark_size')) {
                    $table->unsignedTinyInteger('watermark_size')->default(10)->after('watermark_opacity');
                }

                if (! Schema::hasColumn('agent_profiles', 'watermark_style')) {
                    $table->enum('watermark_style', ['tile', 'single', 'center'])->default('tile')->after('watermark_size');
                }

                if (! Schema::hasColumn('agent_profiles', 'watermark_position')) {
                    $table->enum('watermark_position', ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'])->default('center')->after('watermark_style');
                }

                if (! Schema::hasColumn('agent_profiles', 'watermark_rotation')) {
                    $table->unsignedSmallInteger('watermark_rotation')->default(30)->after('watermark_position');
                }
            });
        }

        if (Schema::hasTable('features')) {
            DB::statement("
    ALTER TABLE features 
    MODIFY COLUMN type ENUM(
        'property_list',
        'project_list',
        'property_feature',
        'project_feature',
        'mortgage_calculator_detail',
        'premium_properties',
        'project_access',
        'premium_projects',
        'agent_watermark'
    ) NULL
");

            $data = [
                'name' => 'Agent Watermark',
                'type' => 'agent_watermark',
                'status' => 1,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('features', 'user_type')) {
                $data['user_type'] = 'agent';
            }

            $existingFeature = DB::table('features')
                ->where('type', 'agent_watermark')
                ->orWhere('name', 'Agent Watermark')
                ->first();

            if ($existingFeature) {
                DB::table('features')->where('id', $existingFeature->id)->update($data);
            } else {
                DB::table('features')->insert(array_merge($data, ['created_at' => now()]));
            }
        }

        if (Schema::hasTable('homepage_sections')) {
            // Need to use raw statement because enum changes are not supported by the schema builder
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
                'premium_projects_section',
                'premium_properties_section',
                'user_recommendations_section',
                'properties_by_cities_section',
                'properties_on_map_section'
            ) NULL");

            // Repair any rows whose section_type was silently blanked by the missing enum value
            DB::table('homepage_sections')
                ->where('section_type', '')
                ->where('title', 'Premium Projects')
                ->update(['section_type' => 'premium_projects_section']);

            // Separate title for the app (web title stays in `title`)
            if (! Schema::hasColumn('homepage_sections', 'app_title')) {
                Schema::table('homepage_sections', function (Blueprint $table) {
                    $table->string('app_title')->nullable()->after('title');
                });

                // Seed the new app_title with the existing web title so nothing is blank
                DB::statement('UPDATE homepage_sections SET app_title = title WHERE app_title IS NULL OR app_title = ?', ['']);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('homepage_sections')) {
            if (Schema::hasColumn('homepage_sections', 'app_title')) {
                Schema::table('homepage_sections', function (Blueprint $table) {
                    $table->dropColumn('app_title');
                });
            }

            // Remove rows using the new value so the enum can be reverted safely
            DB::table('homepage_sections')->where('section_type', 'premium_projects_section')->delete();

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
            ) NULL");
        }

        if (Schema::hasTable('features')) {
            DB::table('features')->where('type', 'agent_watermark')->delete();

            DB::statement("
    ALTER TABLE features 
    MODIFY COLUMN type ENUM(
        'property_list',
        'project_list',
        'property_feature',
        'project_feature',
        'mortgage_calculator_detail',
        'premium_properties',
        'project_access',
        'premium_projects',
        'agent_watermark'
    ) NULL
");
        }

        if (Schema::hasTable('agent_profiles')) {
            Schema::table('agent_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('agent_profiles', 'linkedin_id')) {
                    $table->dropColumn('linkedin_id');
                }

                if (Schema::hasColumn('agent_profiles', 'agent_banner')) {
                    $table->dropColumn('agent_banner');
                }

                if (Schema::hasColumn('agent_profiles', 'watermark_enabled')) {
                    $table->dropColumn('watermark_enabled');
                }

                if (Schema::hasColumn('agent_profiles', 'watermark_image')) {
                    $table->dropColumn('watermark_image');
                }

                if (Schema::hasColumn('agent_profiles', 'watermark_opacity')) {
                    $table->dropColumn('watermark_opacity');
                }

                if (Schema::hasColumn('agent_profiles', 'watermark_size')) {
                    $table->dropColumn('watermark_size');
                }

                if (Schema::hasColumn('agent_profiles', 'watermark_style')) {
                    $table->dropColumn('watermark_style');
                }

                if (Schema::hasColumn('agent_profiles', 'watermark_position')) {
                    $table->dropColumn('watermark_position');
                }

                if (Schema::hasColumn('agent_profiles', 'watermark_rotation')) {
                    $table->dropColumn('watermark_rotation');
                }
            });
        }
    }
};
