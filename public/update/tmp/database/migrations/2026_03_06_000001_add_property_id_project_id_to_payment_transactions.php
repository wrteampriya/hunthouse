<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('property_id')->nullable()->after('pay_as_you_go_id');
            $table->unsignedBigInteger('project_id')->nullable()->after('property_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn(['property_id', 'project_id']);
        });
    }
};
