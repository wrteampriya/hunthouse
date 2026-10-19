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
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('package_id')->nullable()->change();
            $table->unsignedBigInteger('pay_as_you_go_id')->nullable()->after('package_id');
            $table->foreign('pay_as_you_go_id')->references('id')->on('pay_as_you_gos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropForeign(['pay_as_you_go_id']);
            $table->dropColumn('pay_as_you_go_id');
            // Reverting package_id back to NOT NULL is complex and might fail if there are rows where it is null.
            // Assuming this migration adds the initial capability and doesn't explicitly guarantee non-null reversal to prevent breaking existing data.
            $table->unsignedBigInteger('package_id')->nullable(false)->change();
        });
    }
};
