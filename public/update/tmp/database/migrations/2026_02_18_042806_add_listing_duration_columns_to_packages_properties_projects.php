<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('list_duration_type')->nullable()->after('duration'); // Standard, Package, Custom
            $table->integer('custom_duration')->nullable()->after('list_duration_type');
        });

        Schema::table('propertys', function (Blueprint $table) {
            $table->timestamp('expiry_date')->nullable()->after('request_status');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('expiry_date')->nullable()->after('request_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['list_duration_type', 'custom_duration']);
        });

        Schema::table('propertys', function (Blueprint $table) {
            $table->dropColumn('expiry_date');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('expiry_date');
        });
    }
};
