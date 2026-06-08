<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 職稱改為「admin 受管清單」：建立 job_titles 表，並把現有 users.job_title
     * 自由文字 backfill 進清單。users.job_title 仍保留為顯示用字串，但之後一律
     * 從受管清單挑選（rename 時由 SettingController 同步傳播）。
     */
    public function up(): void
    {
        Schema::create('job_titles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // backfill：把現有 users.job_title 既有字串收進清單
        $existing = DB::table('users')
            ->whereNotNull('job_title')
            ->where('job_title', '!=', '')
            ->distinct()
            ->pluck('job_title');

        $order = 0;
        foreach ($existing as $name) {
            DB::table('job_titles')->insertOrIgnore([
                'name'       => $name,
                'sort_order' => $order++,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('job_titles');
    }
};
