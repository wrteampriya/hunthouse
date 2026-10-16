<?php

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        // Update Email & Password Login
        $settings = array(
            'email_password_login' => 1,
            'dark_mode_logo' => 'dark_mode_logo.png',
            'app_login_background' => 'app_login_background.jpg',
        );
        foreach($settings as $key => $value){
            Setting::updateOrCreate(['type' => $key], ['data' => $value]);
        }
        /****************************************************************************** */
        /**
         * Ad Banners
         */
        Schema::create('ad_banners', function (Blueprint $table) {
            $table->id();
            $table->enum('page', ['homepage','property_listing','property_detail']);
            $table->enum('platform', ['app','web']);
            $table->enum('placement', [
                'below_categories',
                'above_all_properties',
                'above_facilities',
                'above_similar_properties',
                'below_slider',
                'sidebar_below_filters',
                'below_breadcrumb',
                'sidebar_below_mortgage_loan_calculator',
                'above_footer',
                'above_breadcrumb',
            ]);
            $table->string('image');
            $table->enum('type', ['external_link','property','banner_only']);
            $table->string('external_link_url')->nullable();
            $table->foreignId('property_id')->nullable()->references('id')->on('propertys')->onDelete('cascade');
            $table->integer('duration_days')->default(1);
            $table->datetime('starts_at')->nullable();
            $table->datetime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        /****************************************************************************** */
        // is admin added data in customer
        if (!Schema::hasColumn('customers', 'is_admin_added')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->boolean('is_admin_added')->default(false)->after('logintype');
            });
        }
        /****************************************************************************** */
        // admin_assigned in payment transaction table
        if (Schema::hasColumn('payment_transactions', 'payment_type')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                // Update 'payment_type' ENUM to add 'manual' option if using MySQL, otherwise add logic as per DB type.
                // For MySQL, you can use DB::statement to alter the column:
                DB::statement("ALTER TABLE `payment_transactions` CHANGE `payment_type` `payment_type` ENUM('online payment', 'bank transfer', 'free', 'manual') NOT NULL DEFAULT 'online payment';");
            });
        }
        /****************************************************************************** */
        // edit reason column in property and project table
        if (!Schema::hasColumn('propertys', 'edit_reason')) {
            Schema::table('propertys', function (Blueprint $table) {
                $table->text('edit_reason')->nullable()->after('request_status');
            });
        }
        if (!Schema::hasColumn('projects', 'edit_reason')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->text('edit_reason')->nullable()->after('request_status');
            });
        }
        /****************************************************************************** */
        // Add User id in property and project view count table
        if (!Schema::hasColumn('property_views', 'user_id')) {
            Schema::table('property_views', function (Blueprint $table) {
                // Drop existing unique key
                if(!Schema::hasIndex('property_views', 'property_id_index')){
                    $table->index('property_id','property_id_index');
                }
                $table->dropUnique('unique_property_date');
                $table->foreignId('user_id')->after('property_id')->nullable()->references('id')->on('customers')->onDelete('cascade');
                $table->unique(['user_id', 'property_id', 'date'], 'unique_property_view_user');
            });
        }
        if (!Schema::hasColumn('project_views', 'user_id')) {
            Schema::table('project_views', function (Blueprint $table) {
                // Drop existing unique key
                if(!Schema::hasIndex('project_views', 'project_id_index')){
                    $table->index('project_id','project_id_index');
                }
                $table->dropUnique('unique_project_date');
                $table->foreignId('user_id')->after('project_id')->nullable()->references('id')->on('customers')->onDelete('cascade');
                $table->unique(['user_id', 'project_id', 'date'], 'unique_project_view_user');
            });
        }
        /****************************************************************************** */
        // Add purchase_type to packages table
        if (!Schema::hasColumn('packages', 'purchase_type')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->enum('purchase_type', ['unlimited', 'one_time'])->default('unlimited')->after('package_type');
            });
        }
        /****************************************************************************** */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /****************************************************************************** */
        // Drop Ad Banners Table
        Schema::dropIfExists('ad_banners');
        /****************************************************************************** */
        if (Schema::hasColumn('customers', 'is_admin_added')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('is_admin_added');
            });
        }
        /****************************************************************************** */
        if (Schema::hasColumn('payment_transactions', 'payment_type')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                // Update 'payment_type' ENUM to add 'manual' option if using MySQL, otherwise add logic as per DB type.
                // For MySQL, you can use DB::statement to alter the column:
                DB::statement("ALTER TABLE `payment_transactions` CHANGE `payment_type` `payment_type` ENUM('online payment', 'bank transfer', 'free') NOT NULL DEFAULT 'online payment';");
            });
        }
        /****************************************************************************** */
        if (Schema::hasColumn('propertys', 'edit_reason')) {
            Schema::table('propertys', function (Blueprint $table) {
                $table->dropColumn('edit_reason');
            });
        }
        if (Schema::hasColumn('projects', 'edit_reason')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('edit_reason');
            });
        }
        /****************************************************************************** */
        if (Schema::hasColumn('property_views', 'user_id')) {
            Schema::table('property_views', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
                $table->dropUnique('unique_property_view_user');
                $table->unique(['property_id', 'date'], 'unique_property_date');
            });
        }
        if (Schema::hasColumn('project_views', 'user_id')) {
            Schema::table('project_views', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
                $table->dropUnique('unique_project_view_user');
                $table->unique(['project_id', 'date'], 'unique_project_date');
            });
        }
        /****************************************************************************** */
        // Drop purchase_type from packages table
        if (Schema::hasColumn('packages', 'purchase_type')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->dropColumn('purchase_type');
            });
        }
        /****************************************************************************** */
    }
};
