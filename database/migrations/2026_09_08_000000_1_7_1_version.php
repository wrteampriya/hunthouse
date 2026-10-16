<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // getPropertiesOnMap() (and every other listing query) always filters
        // by status + request_status via Property::scopeOnlyActive(), and
        // always filters propery_type via whereIn('propery_type', [0, 1]) —
        // so this composite index covers the baseline WHERE clause of every
        // single request to that endpoint, not just some of them.
        if (Schema::hasTable('propertys') && ! $this->indexExists('propertys', 'propertys_status_request_status_propery_type_index')) {
            DB::statement('ALTER TABLE `propertys` ADD INDEX `propertys_status_request_status_propery_type_index` (`status`, `request_status`, `propery_type`)');
        }

        // category_id is filtered directly (category_id filter) and via
        // category_slug_id lookups; no index existed for it.
        if (Schema::hasTable('propertys') && ! $this->indexExists('propertys', 'propertys_category_id_index')) {
            DB::statement('ALTER TABLE `propertys` ADD INDEX `propertys_category_id_index` (`category_id`)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('propertys') && $this->indexExists('propertys', 'propertys_status_request_status_propery_type_index')) {
            DB::statement('ALTER TABLE `propertys` DROP INDEX `propertys_status_request_status_propery_type_index`');
        }

        if (Schema::hasTable('propertys') && $this->indexExists('propertys', 'propertys_category_id_index')) {
            DB::statement('ALTER TABLE `propertys` DROP INDEX `propertys_category_id_index`');
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $indexName]
        ) !== null;
    }
};
