<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('role')
                  ->constrained()->nullOnDelete();
            $table->enum('status', ['pending', 'active', 'inactive'])
                  ->default('active')->change();
            $table->foreignId('invited_by')->nullable()->after('company_id')
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('invited_by');
            $table->enum('status', ['active', 'inactive'])->default('active')->change();
        });
    }
};
