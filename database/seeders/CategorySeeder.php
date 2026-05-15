<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => '網站建置', 'color' => '#3b82f6'],
            ['name' => '行銷活動', 'color' => '#f59e0b'],
            ['name' => '社群行銷', 'color' => '#10b981'],
            ['name' => '系統開發', 'color' => '#8b5cf6'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
