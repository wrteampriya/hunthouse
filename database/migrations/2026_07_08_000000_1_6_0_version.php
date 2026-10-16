<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop agent_id FK if it exists — agent_id=0 is reserved for admin stories
        if (Schema::hasTable('stories')) {
            try {
                Schema::table('stories', function (Blueprint $table) {
                    $table->dropForeign(['agent_id']);
                });
            } catch (\Throwable $e) {
                // FK already removed or never existed — nothing to do
            }
        }

        if (! Schema::hasTable('stories')) {
            Schema::create('stories', function (Blueprint $table) {
                $table->id();
                $table->string('story_id', 20)->unique();
                $table->unsignedBigInteger('agent_id');
                $table->enum('media_type', ['image', 'video']);
                $table->string('media_url');
                $table->string('thumbnail_url')->nullable();
                $table->unsignedSmallInteger('duration_seconds')->default(6);
                $table->enum('linked_entity_type', ['property', 'project']);
                $table->unsignedBigInteger('linked_entity_id');
                $table->unsignedInteger('view_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamp('expires_at');
                $table->timestamps();

                // No FK on agent_id — 0 is reserved for admin-uploaded stories
                $table->index(['linked_entity_type', 'linked_entity_id']);
                $table->index('expires_at');
                $table->index('agent_id');
            });
        }

        // story_views — one row per (story, logged-in user); guests never write here
        if (! Schema::hasTable('story_views')) {
            Schema::create('story_views', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('story_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('viewed_at');
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('story_id')->references('id')->on('stories')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('customers')->onDelete('cascade');
                $table->unique(['story_id', 'user_id']);
                $table->index('user_id');
            });
        }

        // Seed default story duration limit setting
        if (! DB::table('settings')->where('type', 'story_max_duration')->exists()) {
            DB::table('settings')->insert(['type' => 'story_max_duration', 'data' => '60']);
        }

        // Audit logs table
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('actor_type');           // admin | agent | user
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_name');           // snapshot — safe if account deleted
                $table->string('source');               // admin_panel | api
                $table->string('entity_type');          // property | project
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('entity_title')->nullable();
                $table->string('action');               // created | updated | deleted | status_changed | approved | rejected
                $table->text('description');
                $table->timestamp('created_at')->useCurrent();

                $table->index(['entity_type', 'entity_id']);
                $table->index('actor_type');
                $table->index('created_at');
            });
        }

        // Audit log toggle setting
        if (! DB::table('settings')->where('type', 'audit_log_enabled')->exists()) {
            DB::table('settings')->insert(['type' => 'audit_log_enabled', 'data' => '1']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('story_views');
        Schema::dropIfExists('stories');
    }
};
