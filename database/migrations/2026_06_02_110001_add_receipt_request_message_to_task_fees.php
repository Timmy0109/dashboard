<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_fees', function (Blueprint $table) {
            $table->string('receipt_request_message', 500)->nullable()->after('receipt_requested_by');
        });
    }

    public function down(): void
    {
        Schema::table('task_fees', function (Blueprint $table) {
            $table->dropColumn('receipt_request_message');
        });
    }
};
