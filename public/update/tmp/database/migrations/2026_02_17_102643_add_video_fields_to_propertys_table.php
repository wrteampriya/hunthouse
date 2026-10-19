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
        Schema::table('propertys', function (Blueprint $table) {
            $table->tinyInteger('video_type')
                ->nullable()
                ->comment('0=custom,1=youtube,2=vimeo')
                ->after('video_link');

            $table->string('video_link')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('propertys', function (Blueprint $table) {
            $table->dropColumn('video_type');
        });
    }
};
