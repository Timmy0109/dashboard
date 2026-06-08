<?php

namespace Database\Seeders;

use App\Models\JobTitle;
use Illuminate\Database\Seeder;

class JobTitleSeeder extends Seeder
{
    public function run(): void
    {
        $titles = ['老闆', '會計', '出納', '業務助理', '專案經理', '美編', '工程師', '行政'];
        foreach ($titles as $i => $name) {
            JobTitle::firstOrCreate(
                ['name' => $name],
                ['sort_order' => $i, 'is_active' => true],
            );
        }
    }
}
