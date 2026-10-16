<?php

use App\Models\Setting;
use App\Services\HelperService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /****************************************************************************** */
        // Transfer Files and Images
        Artisan::call('images:migrate-to-storage');
        Artisan::call('firebase:migrate-to-storage');
        HelperService::changeEnv(['FILESYSTEM_DISK' => 'public']);
        /****************************************************************************** */
        // Add default language column to customers table
        if (!Schema::hasColumn('customers', 'default_language')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('default_language')->after('country_code')->nullable();
            });
        }

        /****************************************************************************** */
        // Add gemini_usage table
        if (!Schema::hasTable('gemini_usage')) {
            Schema::create('gemini_usage', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->comment('Customer ID or User ID');
                $table->string('user_type',50)->default('customer')->comment('customer or admin');
                $table->string('type',50)->comment('description, meta, search');
                $table->string('entity_type',50)->nullable()->comment('property, project');
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('prompt_hash')->comment('MD5 hash of prompt for caching');
                $table->integer('tokens_used')->nullable();
                $table->string('ip_address')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'user_type', 'type', 'created_at'],'gemini_usage_index');
                $table->index(['prompt_hash'],'gemini_usage_prompt_hash_index');
            });
        }
        /****************************************************************************** */
        // Add default Gemini AI settings
        $defaultSettings = [
            'gemini_ai_enabled' => '0', // Disabled by default
            'gemini_description_limit' => '10',
            'gemini_meta_limit' => '10',
            'gemini_description_limit_global' => '10',
            'gemini_meta_limit_global' => '10',
            'gemini_search_limit_user' => '50',
            'gemini_search_limit_global' => '50',
        ];

        foreach ($defaultSettings as $key => $value) {
            Setting::updateOrCreate(
                ['type' => $key],
                ['data' => $value]
            );
        }
        /****************************************************************************** */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /****************************************************************************** */
        // Drop default language column from customers table
        if (Schema::hasColumn('customers', 'default_language')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('default_language');
            });
        }
        /****************************************************************************** */
        // Drop gemini_usage table
        Schema::dropIfExists('gemini_usage');
        /****************************************************************************** */
        // Drop default Gemini AI settings
        Setting::whereIn('type', [
            'gemini_ai_enabled',
            'gemini_description_limit',
            'gemini_meta_limit',
            'gemini_search_limit_per_hour',
        ])->delete();
        /****************************************************************************** */
    }
};
