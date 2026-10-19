<?php

use App\Models\Feature;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'premium_projects' to the ENUM list
        DB::statement("ALTER TABLE features MODIFY COLUMN type ENUM('property_list','project_list','property_feature','project_feature','mortgage_calculator_detail','premium_properties','project_access','premium_projects') NULL");

        // Update existing Project List Access so existing packages don't break
        Feature::where('type', 'project_access')->orWhere('name', 'Project List Access')->update([
            'name' => 'Premium Projects Access',
            'type' => 'premium_projects',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Feature::where('type', 'premium_projects')->update([
            'name' => 'Project List Access',
            'type' => 'project_access',
        ]);
    }
};
