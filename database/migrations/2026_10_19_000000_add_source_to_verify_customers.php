<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('verify_customers') && ! Schema::hasColumn('verify_customers', 'source')) {
            Schema::table('verify_customers', function (Blueprint $table) {
                $table->string('source')->nullable()->after('user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('verify_customers') && Schema::hasColumn('verify_customers', 'source')) {
            Schema::table('verify_customers', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }
};
