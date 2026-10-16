<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chats can now happen without an associated property (e.g. a general
        // user<->agent or user<->admin conversation), so property_id can no
        // longer be required.
        if (Schema::hasTable('chats') && Schema::hasColumn('chats', 'property_id')) {
            $column = DB::selectOne(
                "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chats' AND COLUMN_NAME = 'property_id'"
            );

            if ($column && strtolower($column->IS_NULLABLE) === 'no') {
                DB::statement('ALTER TABLE `chats` MODIFY `property_id` INT NULL DEFAULT NULL');
            }
        }

        // Become Agent form: per-field "required" toggle.
        if (Schema::hasTable('agent_verification_forms') && ! Schema::hasColumn('agent_verification_forms', 'is_required')) {
            Schema::table('agent_verification_forms', function (Blueprint $table) {
                $table->boolean('is_required')->default(false)->after('field_type');
            });
        }

        // Become Agent form: seed the fixed "Default" section with its 8 known fields.
        // Identified by an is_default flag rather than name — admin can rename it.
        if (Schema::hasTable('agent_verification_form_sections') && Schema::hasTable('agent_verification_forms')) {
            if (! Schema::hasColumn('agent_verification_form_sections', 'is_default')) {
                Schema::table('agent_verification_form_sections', function (Blueprint $table) {
                    $table->boolean('is_default')->default(false)->after('form_type');
                });
            }

            $sectionId = DB::table('agent_verification_form_sections')
                ->where('form_type', 'become_agent')
                ->where(function ($query) {
                    $query->where('is_default', true)->orWhere('name', 'Default');
                })
                ->value('id');

            if (! $sectionId) {
                $sectionId = DB::table('agent_verification_form_sections')->insertGetId([
                    'name' => 'Default',
                    'form_type' => 'become_agent',
                    'is_default' => true,
                    'sequence' => 0,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('agent_verification_form_sections')->where('id', $sectionId)->update(['is_default' => true]);
            }

            $existingFieldIds = DB::table('agent_verification_forms')
                ->where('agent_verification_form_section_id', $sectionId)
                ->pluck('id', 'name');

            // Name/Email/Mobile are pre-filled from the customer's profile (handled in
            // VerificationApiController) but stay editable/submittable, same as any other field.
            $defaultFields = [
                ['name' => 'Name', 'field_type' => 'text', 'is_required' => true, 'sequence' => 1],
                ['name' => 'Email', 'field_type' => 'text', 'is_required' => true, 'sequence' => 2],
                ['name' => 'Mobile', 'field_type' => 'text', 'is_required' => true, 'sequence' => 3],
                ['name' => 'Service Areas', 'field_type' => 'chips', 'is_required' => true, 'sequence' => 4],
                ['name' => 'Languages Spoken', 'field_type' => 'chips', 'is_required' => true, 'sequence' => 5],
                ['name' => 'Years of Experience', 'field_type' => 'number', 'is_required' => true, 'sequence' => 6],
                ['name' => 'Working Hours - Start', 'field_type' => 'time', 'is_required' => false, 'sequence' => 7],
                ['name' => 'Working Hours - End', 'field_type' => 'time', 'is_required' => false, 'sequence' => 8],
            ];

            foreach ($defaultFields as $field) {
                if ($existingFieldIds->has($field['name'])) {
                    DB::table('agent_verification_forms')
                        ->where('id', $existingFieldIds[$field['name']])
                        ->update(['sequence' => $field['sequence'], 'updated_at' => now()]);

                    continue;
                }

                DB::table('agent_verification_forms')->insert([
                    'agent_verification_form_section_id' => $sectionId,
                    'name' => $field['name'],
                    'field_type' => $field['field_type'],
                    'is_required' => $field['is_required'],
                    'sequence' => $field['sequence'],
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Dynamic Social Media Link fields for the agent profile — replaces the 5 fixed
        // columns on agent_profiles with an admin-manageable field list + per-agent values.
        if (! Schema::hasTable('social_media_links')) {
            Schema::create('social_media_links', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('icon')->nullable();
                $table->string('status')->default('active');
                $table->integer('sequence')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('agent_social_media_values')) {
            Schema::create('agent_social_media_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('social_media_link_id');
                $table->string('url')->nullable();
                $table->timestamps();

                $table->unique(['customer_id', 'social_media_link_id'], 'agent_social_media_values_customer_link_unique');
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
                $table->foreign('social_media_link_id')->references('id')->on('social_media_links')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('social_media_links') && DB::table('social_media_links')->count() === 0) {
            $iconSourceDir = public_path('assets/extensions/bootstrap-icons/icons');
            $iconDestDir = storage_path('app/public/'.config('global.SOCIAL_MEDIA_ICON_PATH'));

            if (! is_dir($iconDestDir)) {
                mkdir($iconDestDir, 0755, true);
            }

            $platforms = [
                ['name' => 'Facebook', 'icon_source' => 'facebook.svg', 'profile_column' => 'facebook_id', 'sequence' => 1],
                ['name' => 'Twitter / X', 'icon_source' => 'twitter.svg', 'profile_column' => 'twitter_id', 'sequence' => 2],
                ['name' => 'Instagram', 'icon_source' => 'instagram.svg', 'profile_column' => 'instagram_id', 'sequence' => 3],
                ['name' => 'YouTube', 'icon_source' => 'youtube.svg', 'profile_column' => 'youtube_id', 'sequence' => 4],
                ['name' => 'LinkedIn', 'icon_source' => 'linkedin.svg', 'profile_column' => 'linkedin_id', 'sequence' => 5],
            ];

            $linkIdsByColumn = [];

            foreach ($platforms as $platform) {
                $iconFileName = null;
                $sourcePath = $iconSourceDir.'/'.$platform['icon_source'];
                if (file_exists($sourcePath)) {
                    $iconFileName = time().'-'.$platform['icon_source'];
                    copy($sourcePath, $iconDestDir.$iconFileName);
                }

                $linkId = DB::table('social_media_links')->insertGetId([
                    'name' => $platform['name'],
                    'icon' => $iconFileName,
                    'status' => 'active',
                    'sequence' => $platform['sequence'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $linkIdsByColumn[$platform['profile_column']] = $linkId;
            }

            // Carry forward any URL an agent already saved under the old fixed columns.
            if (Schema::hasTable('agent_profiles')) {
                $profiles = DB::table('agent_profiles')
                    ->select('customer_id', 'facebook_id', 'twitter_id', 'instagram_id', 'youtube_id', 'linkedin_id')
                    ->get();

                foreach ($profiles as $profile) {
                    foreach ($linkIdsByColumn as $column => $linkId) {
                        if (! empty($profile->$column)) {
                            DB::table('agent_social_media_values')->insert([
                                'customer_id' => $profile->customer_id,
                                'social_media_link_id' => $linkId,
                                'url' => $profile->$column,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
        }

        if (Schema::hasTable('agent_profiles')) {
            Schema::table('agent_profiles', function (Blueprint $table) {
                foreach (['facebook_id', 'twitter_id', 'instagram_id', 'youtube_id', 'linkedin_id'] as $column) {
                    if (Schema::hasColumn('agent_profiles', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        // SEO: admin-editable raw JSON-LD schema markup, same pattern SeoSettings already
        // uses — added to every other place SEO fields already live. Also meta_image on
        // articles for parity with propertys/projects (which already have it).
        foreach (['propertys', 'articles', 'projects', 'categories'] as $seoTable) {
            if (Schema::hasTable($seoTable) && ! Schema::hasColumn($seoTable, 'schema_markup')) {
                Schema::table($seoTable, function (Blueprint $table) {
                    $table->longText('schema_markup')->nullable();
                });
            }
        }

        if (Schema::hasTable('articles') && ! Schema::hasColumn('articles', 'meta_image')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->string('meta_image')->nullable()->after('meta_keywords');
            });
        }

        // Save as Draft: category is one of the few fields a project draft can
        // legitimately leave blank, but category_id is a real FK to
        // categories (unlike propertys.category_id, which is a plain varchar
        // with no FK) — an empty/zero value there fails the constraint
        // instead of just being an empty draft field. Making it nullable
        // keeps the FK enforced for any real value while allowing "not set
        // yet" during a draft.
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'category_id')) {
            $column = DB::selectOne(
                "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'category_id'"
            );

            if ($column && strtolower($column->IS_NULLABLE) === 'no') {
                DB::statement('ALTER TABLE `projects` MODIFY `category_id` BIGINT UNSIGNED NULL DEFAULT NULL');
            }
        }
    }

    public function down(): void
    {
        // Not reversible safely — existing property-less chats would need a
        // property_id backfilled first, which there's no sensible default for.
    }
};
