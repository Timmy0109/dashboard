<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Faster pending-member lookups (MemberApprovalController, RegisterController)
        Schema::table('users', function (Blueprint $table) {
            $table->index(['company_id', 'status'], 'idx_users_company_status');
        });

        // hasFeature() query: company_id + enabled filter
        Schema::table('company_features', function (Blueprint $table) {
            $table->index(['company_id', 'enabled'], 'idx_cf_company_enabled');
        });

        // feature toggle audit queries
        Schema::table('feature_change_logs', function (Blueprint $table) {
            $table->index(['company_id', 'feature_key'], 'idx_fcl_company_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_company_status');
        });
        Schema::table('company_features', function (Blueprint $table) {
            $table->dropIndex('idx_cf_company_enabled');
        });
        Schema::table('feature_change_logs', function (Blueprint $table) {
            $table->dropIndex('idx_fcl_company_key');
        });
    }
};
