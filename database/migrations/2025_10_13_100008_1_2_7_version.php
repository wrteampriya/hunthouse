<?php

use App\Models\Property;
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
        /**
         * Update rentduration to Quarterly for properties where rentduration like "%Quaterly%";
         */
        Property::where('rentduration', 'like', '%Quaterly%')->update(['rentduration' => 'Quarterly']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
