<?php

use App\Models\Category;
use App\Models\parameter;
use App\Models\Property;
use App\Models\OutdoorFacilities;
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
        /** Demo Data flag for demo contents */

        // Parameters (facilities)
        if(!Schema::hasColumn('parameters', 'is_demo')) {
            Schema::table('parameters', function (Blueprint $table) {
                $table->boolean('is_demo')->default(false);
            });
        }

        // Categories
        if(!Schema::hasColumn('categories', 'is_demo')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->boolean('is_demo')->default(false);
            });
        }

        // Near By places
        if(!Schema::hasColumn('outdoor_facilities', 'is_demo')) {
            Schema::table('outdoor_facilities', function (Blueprint $table) {
                $table->boolean('is_demo')->default(false);
            });
        }

        // Properties
        if(!Schema::hasColumn('propertys', 'is_demo')) {
            Schema::table('propertys', function (Blueprint $table) {
                $table->boolean('is_demo')->default(false);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /** Remove Demo Data flag for demo contents with demo data */

        // Parameters (facilities)
        if(Schema::hasColumn('parameters', 'is_demo')) {
            parameter::where('is_demo', true)->delete();
            Schema::table('parameters', function (Blueprint $table) {
                $table->dropColumn('is_demo');
            });
        }

        // Categories
        if(Schema::hasColumn('categories', 'is_demo')) {
            Category::where('is_demo', true)->delete();
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('is_demo');
            });
        }

        // Near By places
        if(Schema::hasColumn('outdoor_facilities', 'is_demo')) {
            OutdoorFacilities::where('is_demo', true)->delete();
            Schema::table('outdoor_facilities', function (Blueprint $table) {
                $table->dropColumn('is_demo');
            });
        }

        // Properties
        if(Schema::hasColumn('propertys', 'is_demo')) {
            Property::where('is_demo', true)->delete();
            Schema::table('propertys', function (Blueprint $table) {
                $table->dropColumn('is_demo');
            });
        }
    }
};
