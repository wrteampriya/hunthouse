<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Property;
use App\Models\Projects;
use App\Models\UserPackage;
use App\Services\HelperService;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $properties = Property::whereNull('expiry_date')->get();
        $this->seedListings($properties);

        $projects = Projects::whereNull('expiry_date')->get();
        $this->seedListings($projects);
    }

   private function seedListings($listings)
    {
        foreach ($listings as $listing) {
            $userId = $listing->added_by;

            if ($userId === 0 || $userId === null) {
                continue;
            }

            $duration = 30; // Default 30 days

            $dateField = Carbon::now();
            $expiryDate = Carbon::parse($dateField)->addDays($duration);

            $listing->expiry_date = $expiryDate;
            $listing->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration logic needed for a data backfill script
    }
};
