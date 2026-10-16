<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Intl\Countries;

return new class extends Migration
{
    public function up(): void
    {
        // Currencies must exist before countries can reference one
        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string('name', 512);
                $table->string('code', 8)->unique(); // ISO 4217, e.g. INR, USD
                $table->string('symbol', 16);
                $table->tinyInteger('status')->default(1)->comment('1=>active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table) {
                $table->id();
                $table->string('name', 512);
                $table->string('code', 8)->unique(); // ISO 3166-1 alpha-2, e.g. IN, US
                $table->unsignedBigInteger('default_language_id')->nullable();
                $table->unsignedBigInteger('currency_id')->nullable();
                // Reference point (e.g. capital city) used to find the nearest admin-added
                // country when a picked map location falls outside every configured country.
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->tinyInteger('status')->default(1)->comment('1=>active');
                $table->timestamps();

                // Null default_language_id falls back to the global default_language setting
                $table->foreign('default_language_id')->references('id')->on('languages')->nullOnDelete();
                $table->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();
            });
        }

        // Reference point (e.g. capital city) used to find the nearest admin-added
        // country when a picked map location falls outside every configured country.
        // Added after the initial countries table creation, so guard separately
        // in case this migration already ran without them.
        if (Schema::hasTable('countries') && ! Schema::hasColumn('countries', 'latitude')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->decimal('latitude', 10, 7)->nullable()->after('currency_id');
            });
        }
        if (Schema::hasTable('countries') && ! Schema::hasColumn('countries', 'longitude')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            });
        }

        // Link existing content/customer tables to the new countries table.
        // Existing free-text `country` column is kept as-is for backward compatibility.
        foreach (['propertys', 'projects', 'customers'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'country_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('country_id')->nullable()->after('country');
                    $table->foreign('country_id')->references('id')->on('countries')->restrictOnDelete();
                });
            }
        }

        // Currency the listing was posted in — free choice, never validated against
        // the listing's country. Properties only — projects have no price/currency concept.
        if (Schema::hasTable('propertys') && ! Schema::hasColumn('propertys', 'currency_id')) {
            Schema::table('propertys', function (Blueprint $table) {
                $table->unsignedBigInteger('currency_id')->nullable()->after('country_id');
                $table->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();
            });
        }

        // Backfill country_id/currency_id for rows that pre-date this migration, so
        // X-Country-Id-scoped requests don't silently exclude every existing listing.
        // This install only ever served one country before this feature existed, so
        // every pre-existing row genuinely belongs to whichever country the admin's
        // already-configured `currency_code` setting implies — no per-row guessing
        // needed. ISO 4217 currency codes conventionally start with the ISO 3166-1
        // country code (AED -> AE, INR -> IN, USD -> US), so that's used to resolve
        // the country without any new input. Shared/regional currencies (EUR, XOF...)
        // don't map to a single country this way — those are safely skipped (no-op)
        // rather than guessed at; the fallback for such installs is a manual fix.
        $currencyCode = strtoupper((string) DB::table('settings')->where('type', 'currency_code')->value('data'));
        $countryCode = substr($currencyCode, 0, 2);

        if ($currencyCode !== '' && $countryCode !== '' && Countries::exists($countryCode)) {
            $currencySymbol = (string) DB::table('settings')->where('type', 'currency_symbol')->value('data');
            $languageCode = (string) DB::table('settings')->where('type', 'default_language')->value('data');
            $defaultLanguageId = $languageCode !== ''
                ? DB::table('languages')->where('code', $languageCode)->value('id')
                : null;

            $currencyId = DB::table('currencies')->where('code', $currencyCode)->value('id');
            if (! $currencyId) {
                $currencyId = DB::table('currencies')->insertGetId([
                    'name' => $currencyCode,
                    'code' => $currencyCode,
                    'symbol' => $currencySymbol !== '' ? $currencySymbol : $currencyCode,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $countryId = DB::table('countries')->where('code', $countryCode)->value('id');
            if (! $countryId) {
                $countryId = DB::table('countries')->insertGetId([
                    'name' => Countries::getName($countryCode),
                    'code' => $countryCode,
                    'default_language_id' => $defaultLanguageId,
                    'currency_id' => $currencyId,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('customers')->whereNull('country_id')->update(['country_id' => $countryId]);
            DB::table('propertys')->whereNull('country_id')->update(['country_id' => $countryId]);
            DB::table('propertys')->whereNull('currency_id')->update(['currency_id' => $currencyId]);
            DB::table('projects')->whereNull('country_id')->update(['country_id' => $countryId]);
        }

        // Fallback for rows the currency-code resolution above couldn't reach
        // (currency_code setting missing/invalid, or a shared/regional currency
        // like EUR that doesn't map to a single country). If this install only
        // has one country configured, every row unambiguously belongs to it —
        // no guessing required. Installs with multiple countries are left alone
        // here since there's no safe way to pick among them automatically.
        if (DB::table('countries')->count() === 1) {
            $onlyCountryId = DB::table('countries')->value('id');
            $onlyCountryCurrencyId = DB::table('countries')->value('currency_id');

            DB::table('customers')->whereNull('country_id')->update(['country_id' => $onlyCountryId]);
            DB::table('propertys')->whereNull('country_id')->update(['country_id' => $onlyCountryId]);
            DB::table('projects')->whereNull('country_id')->update(['country_id' => $onlyCountryId]);
            if ($onlyCountryCurrencyId) {
                DB::table('propertys')->whereNull('currency_id')->update(['currency_id' => $onlyCountryCurrencyId]);
            }
        }

        // Stale row from an earlier iteration of this migration on environments that
        // already ran it — the enforce_country_mapping toggle was dropped before release.
        DB::table('settings')->where('type', 'enforce_country_mapping')->delete();

        // Content sections can be scoped to a single country or left global (null).
        foreach (['sliders', 'faqs', 'articles', 'ad_banners'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'country_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('country_id')->nullable();
                    $table->foreign('country_id')->references('id')->on('countries')->restrictOnDelete();
                });
            }
        }

        // Agent-follow feature: a customer can follow an agent. Mirrors the
        // stories.agent_id convention — agent_id=0 means the platform admin
        // (a users-table row, not a customers row), so it deliberately has no FK.
        if (! Schema::hasTable('agent_follows')) {
            Schema::create('agent_follows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id'); // follower
                $table->unsignedBigInteger('agent_id'); // followed agent, 0 = admin
                $table->timestamps();

                $table->unique(['user_id', 'agent_id']);
                $table->foreign('user_id')->references('id')->on('customers')->cascadeOnDelete();
            });
        }

        // Data fix: an earlier version stored the default language under the
        // code 'en-new' (a legacy convention that has since been removed
        // entirely — only 'en' is used now). Normalize any leftover 'en-new'
        // references on environments where this was never manually cleaned up.
        $enNewLanguage = DB::table('languages')->where('code', 'en-new')->first();
        if ($enNewLanguage) {
            $hasEnRow = DB::table('languages')->where('code', 'en')->exists();
            if ($hasEnRow) {
                // A separate 'en' row already exists — repoint everything to
                // it, then drop the now-redundant 'en-new' row.
                DB::table('settings')->where('type', 'default_language')->where('data', 'en-new')->update(['data' => 'en']);
                DB::table('customers')->where('default_language', 'en-new')->update(['default_language' => 'en']);
                DB::table('languages')->where('id', $enNewLanguage->id)->delete();
            } else {
                // 'en-new' is the only English row — rename it in place.
                DB::table('languages')->where('id', $enNewLanguage->id)->update(['code' => 'en', 'file_name' => 'en.json']);
                DB::table('settings')->where('type', 'default_language')->where('data', 'en-new')->update(['data' => 'en']);
                DB::table('customers')->where('default_language', 'en-new')->update(['default_language' => 'en']);
            }
        }

        // Data fix: propertys/projects latitude & longitude were stored as
        // free-form VARCHAR, so range queries (e.g. a map bounding-box search)
        // silently do lexicographic string comparison instead of numeric
        // comparison — "9.0" > "72.5" as strings, breaking WHERE ... BETWEEN.
        // Convert to a proper decimal type and add a range-query index.
        // Nullable with no default (not NOT NULL DEFAULT 0): a draft saved
        // without a location (allowed by design) must store a blank
        // latitude/longitude, not a fake "0,0" that looks like real data.
        // Uses raw SQL (not ->change()) deliberately — Doctrine DBAL, which
        // Schema::table()->change() relies on for column reflection, cannot
        // reflect tables containing `enum` columns (propertys has two:
        // request_status, role_context) and throws on every environment.
        foreach (['propertys' => 'propertys_lat_lng_index', 'projects' => 'projects_lat_lng_index'] as $tableName => $indexName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $columnInfo = DB::selectOne(
                'SELECT DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$tableName, 'latitude']
            );
            $alreadyDecimal = $columnInfo && strtolower($columnInfo->DATA_TYPE) === 'decimal';
            $alreadyNullable = $columnInfo && strtolower($columnInfo->IS_NULLABLE) === 'yes';

            if (! $alreadyDecimal) {
                // Normalize invalid/empty values before the type change so the
                // ALTER doesn't fail or silently mangle garbage into an
                // unpredictable value.
                foreach (['latitude', 'longitude'] as $column) {
                    DB::table($tableName)
                        ->where(function ($q) use ($column) {
                            $q->whereNull($column)->orWhere($column, '')->orWhereRaw("$column NOT REGEXP '^-?[0-9]+(\\.[0-9]+)?$'");
                        })
                        ->update([$column => null]);
                }

                DB::statement("ALTER TABLE `$tableName` ADD INDEX `$indexName` (`latitude`, `longitude`)");
            }

            if (! $alreadyDecimal || ! $alreadyNullable) {
                // Placeholder "0,0" from the old NOT NULL DEFAULT 0 era is
                // indistinguishable from "never set" — clear it too, so
                // existing location-less drafts stop showing a fake spot.
                DB::table($tableName)->where('latitude', 0)->where('longitude', 0)->update(['latitude' => null, 'longitude' => null]);

                DB::statement("ALTER TABLE `$tableName` MODIFY `latitude` DECIMAL(10,7) NULL DEFAULT NULL");
                DB::statement("ALTER TABLE `$tableName` MODIFY `longitude` DECIMAL(10,7) NULL DEFAULT NULL");
            }
        }

        // City: propertys carries a leftover demo-seed default of 'Kutch'
        // (from the original table migration) and projects' city is
        // NOT NULL with no default (silently becomes ''). Either way, a
        // draft saved without a location ends up with a city value that
        // looks real but isn't. Drop the default / allow NULL so it's
        // honestly blank instead.
        if (Schema::hasTable('propertys') && Schema::hasColumn('propertys', 'city')) {
            DB::statement("ALTER TABLE `propertys` MODIFY `city` VARCHAR(255) NULL DEFAULT NULL");
            DB::table('propertys')->where('city', 'Kutch')->where('request_status', 'draft')->update(['city' => null]);
        }
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'city')) {
            DB::statement("ALTER TABLE `projects` MODIFY `city` VARCHAR(255) NULL DEFAULT NULL");
            DB::table('projects')->where('city', '')->update(['city' => null]);
        }

        // Personalized-feed country preference: the countries a user wants
        // their recommendation feed scoped to, independent of whichever
        // country they currently have selected site-wide (X-Country-Id).
        // Comma-separated ids, same convention as category_ids on this table.
        if (Schema::hasTable('user_interests') && ! Schema::hasColumn('user_interests', 'country_ids')) {
            Schema::table('user_interests', function (Blueprint $table) {
                $table->string('country_ids')->nullable()->after('category_ids');
            });
        }

        // Admin-curated homepage sections: admin hand-picks specific
        // properties/projects to feature, instead of a filter-driven query.
        // One pivot per listing type (not a single polymorphic table) since
        // there are only ever these two fixed curated section types.
        if (! Schema::hasTable('homepage_section_properties')) {
            Schema::create('homepage_section_properties', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('homepage_section_id');
                $table->unsignedBigInteger('property_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                // Explicit short name — the auto-generated one
                // ("homepage_section_properties_homepage_section_id_property_id_unique",
                // 66 chars) exceeds MySQL's 64-char identifier limit.
                $table->unique(['homepage_section_id', 'property_id'], 'hs_properties_section_property_unique');
                $table->foreign('homepage_section_id')->references('id')->on('homepage_sections')->cascadeOnDelete();
                $table->foreign('property_id')->references('id')->on('propertys')->cascadeOnDelete();
            });
        }
        if (! Schema::hasTable('homepage_section_projects')) {
            Schema::create('homepage_section_projects', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('homepage_section_id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                // Explicit short name for symmetry with the properties pivot
                // above (its auto-generated name exceeded MySQL's 64-char limit).
                $table->unique(['homepage_section_id', 'project_id'], 'hs_projects_section_project_unique');
                $table->foreign('homepage_section_id')->references('id')->on('homepage_sections')->cascadeOnDelete();
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            });
        } elseif (Schema::hasColumn('homepage_section_projects', 'projects_id') && ! Schema::hasColumn('homepage_section_projects', 'project_id')) {
            // An earlier run of this migration created the table with the wrong
            // pivot column name (projects_id); the model's belongsToMany expects
            // project_id. Fix environments that already ran the old version.
            Schema::table('homepage_section_projects', function (Blueprint $table) {
                $table->renameColumn('projects_id', 'project_id');
            });
        }
        // Per-agent auto-approve override. Each agent's own value is the
        // effective one (HelperService::getAutoApproveStatus checks this
        // column, not the global setting, for role=agent). The global
        // agent_auto_approve setting now acts as a bulk on/off switch: toggling
        // it writes this value to every agent, and toggling any single agent
        // off flips the global setting off too (see VerifyCustomerFormController
        // and CustomersController::toggleAutoApprove).
        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'auto_approve')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->boolean('auto_approve')->default(0)->after('is_agent_verified');
            });

            // Only backfill to on when the global setting is explicitly on —
            // column already defaults to off, so there's nothing to do in
            // the off/unset case.
            $globalAgentAutoApprove = DB::table('settings')->where('type', 'agent_auto_approve')->value('data');
            if ($globalAgentAutoApprove == 1) {
                DB::table('customers')->where('is_agent', 1)->update(['auto_approve' => 1]);
            }
        }

        // Same per-customer override, for the "user" role context (mirrors
        // the global `auto_approve` setting the way `auto_approve` above
        // mirrors `agent_auto_approve`). Applies to every customer, not just
        // agents — anyone can post while acting in the "user" role.
        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'user_auto_approve')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->boolean('user_auto_approve')->default(0)->after('auto_approve');
            });

            // Only backfill to on when the global setting is explicitly on —
            // column already defaults to off, so there's nothing to do in
            // the off/unset case.
            $globalUserAutoApprove = DB::table('settings')->where('type', 'auto_approve')->value('data');
            if ($globalUserAutoApprove == 1) {
                DB::table('customers')->update(['user_auto_approve' => 1]);
            }
        }

        if (Schema::hasTable('homepage_sections')) {
            // Need to use raw statement because enum changes are not supported by the schema builder.
            // Full list must be repeated (MySQL has no "add one enum value" syntax) — same
            // pattern already used in 2026_05_25_113029_1_5_0_version.php.
            DB::statement("ALTER TABLE homepage_sections MODIFY COLUMN section_type ENUM(
                'agents_list_section',
                'articles_section',
                'categories_section',
                'faqs_section',
                'featured_properties_section',
                'featured_projects_section',
                'most_liked_properties_section',
                'most_viewed_properties_section',
                'nearby_properties_section',
                'projects_section',
                'premium_projects_section',
                'premium_properties_section',
                'user_recommendations_section',
                'properties_by_cities_section',
                'properties_on_map_section',
                'curated_properties_section',
                'curated_projects_section'
            ) NULL");
        }
    }

    public function down(): void
    {
        // Note: intentionally does not revert latitude/longitude back to
        // VARCHAR — that would be a lossy, purely cosmetic downgrade with no
        // safety benefit. Only the added index is reversible.
        foreach (['propertys' => 'propertys_lat_lng_index', 'projects' => 'projects_lat_lng_index'] as $tableName => $indexName) {
            if (Schema::hasTable($tableName)) {
                try {
                    DB::statement("ALTER TABLE `$tableName` DROP INDEX `$indexName`");
                } catch (\Throwable $e) {
                    // Index may not exist if the column was already decimal
                    // when up() ran (skip branch never created it).
                }
            }
        }

        Schema::dropIfExists('agent_follows');

        if (Schema::hasTable('user_interests') && Schema::hasColumn('user_interests', 'country_ids')) {
            Schema::table('user_interests', function (Blueprint $table) {
                $table->dropColumn('country_ids');
            });
        }

        if (Schema::hasColumn('customers', 'auto_approve')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('auto_approve');
            });
        }

        if (Schema::hasColumn('customers', 'user_auto_approve')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('user_auto_approve');
            });
        }

        Schema::dropIfExists('homepage_section_properties');
        Schema::dropIfExists('homepage_section_projects');

        if (Schema::hasTable('homepage_sections')) {
            DB::table('homepage_sections')->whereIn('section_type', ['curated_properties_section', 'curated_projects_section'])->delete();

            DB::statement("ALTER TABLE homepage_sections MODIFY COLUMN section_type ENUM(
                'agents_list_section',
                'articles_section',
                'categories_section',
                'faqs_section',
                'featured_properties_section',
                'featured_projects_section',
                'most_liked_properties_section',
                'most_viewed_properties_section',
                'nearby_properties_section',
                'projects_section',
                'premium_projects_section',
                'premium_properties_section',
                'user_recommendations_section',
                'properties_by_cities_section',
                'properties_on_map_section'
            ) NULL");
        }

        foreach (['sliders', 'faqs', 'articles', 'ad_banners'] as $tableName) {
            if (Schema::hasColumn($tableName, 'country_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['country_id']);
                    $table->dropColumn('country_id');
                });
            }
        }

        if (Schema::hasColumn('propertys', 'currency_id')) {
            Schema::table('propertys', function (Blueprint $table) {
                $table->dropForeign(['currency_id']);
                $table->dropColumn('currency_id');
            });
        }

        foreach (['propertys', 'projects', 'customers'] as $tableName) {
            if (Schema::hasColumn($tableName, 'country_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['country_id']);
                    $table->dropColumn('country_id');
                });
            }
        }

        Schema::dropIfExists('countries');
        Schema::dropIfExists('currencies');
    }
};
