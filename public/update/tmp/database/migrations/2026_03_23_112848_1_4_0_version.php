<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // --- Customers table: add is_agent, is_agent_verified ---
        if (! Schema::hasColumn('customers', 'is_agent')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->boolean('is_agent')->default(0)->after('isActive');
            });
        }
        if (! Schema::hasColumn('customers', 'is_agent_verified')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->boolean('is_agent_verified')->default(0)->after('is_agent');
            });
        }

        // --- Agent verification tables ---
        if (! Schema::hasTable('agent_verification_form_sections')) {
            Schema::create('agent_verification_form_sections', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('form_type')->comment('become_agent, verify_agent');
                $table->integer('sequence')->default(0);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('agent_verification_forms')) {
            Schema::create('agent_verification_forms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_verification_form_section_id')->constrained('agent_verification_form_sections', 'id', 'agent_ver_form_section_fk')->cascadeOnDelete();
                $table->string('name');
                $table->string('field_type');
                $table->integer('sequence')->default(0);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('agent_verification_form_values')) {
            Schema::create('agent_verification_form_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_verification_form_id')->constrained('agent_verification_forms', 'id', 'agent_ver_form_vals_form_fk')->cascadeOnDelete();
                $table->string('value');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('agent_verifications')) {
            Schema::create('agent_verifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->enum('form_type', ['become_agent', 'verify_agent']);
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('agent_verification_values')) {
            Schema::create('agent_verification_values', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_verification_id')->constrained('agent_verifications', 'id', 'agent_ver_vals_ver_fk')->cascadeOnDelete();
                $table->foreignId('agent_verification_form_id')->constrained('agent_verification_forms', 'id', 'agent_ver_vals_form_fk')->cascadeOnDelete();
                $table->text('value');
                $table->timestamps();
            });
        }

        // --- Packages & Features: add user_type ---
        if (! Schema::hasColumn('packages', 'user_type')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->enum('user_type', ['user', 'agent'])->default('user')->after('status');
            });
        }

        if (! Schema::hasColumn('features', 'user_type')) {
            Schema::table('features', function (Blueprint $table) {
                $table->enum('user_type', ['all', 'user', 'agent'])->default('all')->after('status');
            });
        }

        if (Schema::hasColumn('features', 'user_type')) {
            DB::statement("ALTER TABLE features MODIFY user_type ENUM('all','user','agent') DEFAULT 'all'");
        }

        // Seed the data as per user request
        DB::table('features')->whereIn('id', [1, 2, 3, 4])->update(['user_type' => 'all']);
        DB::table('features')->whereIn('id', [5, 6, 7])->update(['user_type' => 'user']);

        // --- Agent profiles table ---
        if (! Schema::hasTable('agent_profiles')) {
            Schema::create('agent_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
                $table->string('agent_name')->nullable();
                $table->string('work_email')->nullable();
                $table->string('agent_profile_photo')->nullable();
                $table->timestamps();
            });
        }

        $agentProfileTable = [
            'about_me',
            'facebook_id',
            'twitter_id',
            'youtube_id',
            'instagram_id',
        ];
        foreach ($agentProfileTable as $column) {
            if (! Schema::hasColumn('agent_profiles', $column)) {
                Schema::table('agent_profiles', function (Blueprint $table) use ($column) {
                    $table->string($column)->nullable()->after('agent_profile_photo');
                });
            }
        }
        // rename work_email to agent_email
        if (Schema::hasColumn('agent_profiles', 'work_email') && ! Schema::hasColumn('agent_profiles', 'agent_email')) {
            Schema::table('agent_profiles', function (Blueprint $table) {
                $table->renameColumn('work_email', 'agent_email');
            });
        }

        // remove about_me, facebook_id, twitter_id, youtube_id, instagram_id from customers table if exist
        $customerTableRemoveColumns = [
            'about_me',
            'facebook_id',
            'twitter_id',
            'instagram_id',
            'youtube_id',
        ];
        foreach ($customerTableRemoveColumns as $column) {
            if (Schema::hasColumn('customers', $column)) {
                Schema::table('customers', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        // add agent_address column to agent_profiles table
        if (! Schema::hasColumn('agent_profiles', 'agent_address')) {
            Schema::table('agent_profiles', function (Blueprint $table) {
                $table->text('agent_address')->nullable()->after('agent_email');
            });
        }

        // add agent_mobile and agent_country_code to agent_profiles table
        if (! Schema::hasColumn('agent_profiles', 'agent_mobile')) {
            Schema::table('agent_profiles', function (Blueprint $table) {
                $table->string('agent_mobile')->nullable()->after('agent_address');
            });
        }
        if (! Schema::hasColumn('agent_profiles', 'agent_country_code')) {
            Schema::table('agent_profiles', function (Blueprint $table) {
                $table->string('agent_country_code')->nullable()->after('agent_mobile');
            });
        }

        // --- Add role_context to all dual-mode tables ---
        $roleContextTables = [
            'propertys',
            'projects',
            'user_packages',
            'payment_transactions',
            'chats',
            'favourites',
            'advertisements',
            'notification',
        ];
        foreach ($roleContextTables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'role_context')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->enum('role_context', ['user', 'agent', 'general'])->default('user')->index();
                });
            }
        }

        // add receiver_role_context and alter role_context as sender_role_context to chats table
        if (Schema::hasTable('chats') && ! Schema::hasColumn('chats', 'receiver_role_context')) {
            Schema::table('chats', function (Blueprint $table) {
                $table->enum('receiver_role_context', ['user', 'agent', 'admin'])->default('user')->index();
            });
        }
        if (Schema::hasTable('chats') && Schema::hasColumn('chats', 'role_context')) {
            Schema::table('chats', function (Blueprint $table) {
                $table->renameColumn('role_context', 'sender_role_context');
            });
        }

        // backfill sender_role_context and receiver_role_context in chats table based on sender_id and receiver_id. if sender_id is 0 then sender_role_context will be admin and if receiver_id is 0 then receiver_role_context will be admin otherwise it will be user
        if (Schema::hasTable('chats')) {
            DB::table('chats')->update([
                'sender_role_context' => DB::raw("CASE WHEN sender_id = 0 THEN 'admin' ELSE 'user' END"),
                'receiver_role_context' => DB::raw("CASE WHEN receiver_id = 0 THEN 'admin' ELSE 'user' END"),
            ]);
        }

        // --- Backfill role_context for existing data ---
        // Properties/projects posted by agents → set role_context = 'agent'
        $backfillTables = ['propertys', 'projects'];
        foreach ($backfillTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'role_context')) {
                DB::table($tableName)
                    ->join('customers', $tableName.'.added_by', '=', 'customers.id')
                    ->where('customers.is_agent', 1)
                    ->update([$tableName.'.role_context' => 'agent']);
            }
        }

        // --- FAQs: add user_type ---
        if (! Schema::hasColumn('faqs', 'user_type')) {
            Schema::table('faqs', function (Blueprint $table) {
                $table->enum('user_type', ['user', 'agent'])->default('user')->after('status');
            });
        }

        // --- Fix verify_customers.status enum ---
        if (Schema::hasTable('verify_customers')) {
            DB::statement("ALTER TABLE verify_customers MODIFY COLUMN status ENUM('failed','success','pending','approved','rejected') DEFAULT 'pending'");
            DB::table('verify_customers')->where('status', 'success')->update(['status' => 'approved']);
            DB::table('verify_customers')->where('status', 'failed')->update(['status' => 'rejected']);
            DB::statement("ALTER TABLE verify_customers MODIFY COLUMN status ENUM('pending','approved','rejected') DEFAULT 'pending'");
        }

        // --- Add 'draft' to request_status ENUMs for properties and projects ---
        if (Schema::hasTable('propertys')) {
            DB::statement("ALTER TABLE propertys MODIFY COLUMN request_status ENUM('approved','rejected','pending','draft') NOT NULL DEFAULT 'pending'");
        }
        if (Schema::hasTable('projects')) {
            DB::statement("ALTER TABLE projects MODIFY COLUMN request_status ENUM('approved','rejected','pending','draft') NOT NULL DEFAULT 'pending'");
        }

        // --- Reject Reasons: add agent_verification_id and verify_customer_id ---
        if (Schema::hasTable('reject_reasons')) {
            Schema::table('reject_reasons', function (Blueprint $table) {
                if (! Schema::hasColumn('reject_reasons', 'agent_verification_id')) {
                    $table->foreignId('agent_verification_id')->nullable()->after('project_id')->constrained('agent_verifications')->cascadeOnDelete();
                }
                if (! Schema::hasColumn('reject_reasons', 'verify_customer_id')) {
                    $table->foreignId('verify_customer_id')->nullable()->after('agent_verification_id')->constrained('verify_customers')->cascadeOnDelete();
                }
            });
        }

        // Update Properties table where admin is the creator
        DB::table('propertys')
            ->where('added_by', 0)
            ->update(['role_context' => 'agent']);

        // Update Projects table where admin is the creator
        DB::table('projects')
            ->where('added_by', 0)
            ->update(['role_context' => 'agent']);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop reject_reasons columns
        if (Schema::hasTable('reject_reasons')) {
            Schema::table('reject_reasons', function (Blueprint $table) {
                if (Schema::hasColumn('reject_reasons', 'agent_verification_id')) {
                    $table->dropForeign(['agent_verification_id']);
                    $table->dropColumn('agent_verification_id');
                }
                if (Schema::hasColumn('reject_reasons', 'verify_customer_id')) {
                    $table->dropForeign(['verify_customer_id']);
                    $table->dropColumn('verify_customer_id');
                }
            });
        }

        // Drop customers columns
        if (Schema::hasColumn('customers', 'is_agent')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('is_agent');
            });
        }
        if (Schema::hasColumn('customers', 'is_agent_verified')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('is_agent_verified');
            });
        }

        // Drop agent verification tables
        Schema::dropIfExists('agent_verification_values');
        Schema::dropIfExists('agent_verifications');
        Schema::dropIfExists('agent_verification_form_values');
        Schema::dropIfExists('agent_verification_forms');
        Schema::dropIfExists('agent_verification_form_sections');

        // Drop packages/features user_type
        if (Schema::hasColumn('packages', 'user_type')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->dropColumn('user_type');
            });
        }
        if (Schema::hasColumn('features', 'user_type')) {
            Schema::table('features', function (Blueprint $table) {
                $table->dropColumn('user_type');
            });
        }

        // Drop agent_mobile and agent_country_code from agent_profiles
        foreach (['agent_mobile', 'agent_country_code'] as $column) {
            if (Schema::hasColumn('agent_profiles', $column)) {
                Schema::table('agent_profiles', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        // Drop agent_profiles
        Schema::dropIfExists('agent_profiles');

        // Drop role_context from all tables
        $roleContextTables = [
            'propertys',
            'projects',
            'user_packages',
            'payment_transactions',
            'chats',
            'favourites',
            'advertisements',
            'notification',
        ];
        foreach ($roleContextTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'role_context')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('role_context');
                });
            }
        }

        // Revert verify_customers.status enum
        if (Schema::hasTable('verify_customers')) {
            DB::statement("ALTER TABLE verify_customers MODIFY COLUMN status ENUM('pending','approved','rejected','success','failed') DEFAULT 'pending'");
            DB::table('verify_customers')->where('status', 'approved')->update(['status' => 'success']);
            DB::table('verify_customers')->where('status', 'rejected')->update(['status' => 'failed']);
            DB::statement("ALTER TABLE verify_customers MODIFY COLUMN status ENUM('failed','success','pending') DEFAULT 'pending'");
        }

        // Revert Properties back to 'user' for admin (assuming'user' is default fallback)
        DB::table('propertys')
            ->where('added_by', 0)
            ->update(['role_context' => 'user']);

        // Revert Projects back to 'user' for admin
        DB::table('projects')
            ->where('added_by', 0)
            ->update(['role_context' => 'user']);
    }
};
