<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyFeature;
use App\Models\Feature;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();

        // Demo company
        $company = Company::create([
            'name'       => 'Demo 公司',
            'status'     => 'active',
            'invite_code' => 'DEMO123456AB',
            'created_by' => $admin->id,
        ]);

        // Assign manager and member to the company
        User::where('role', 'manager')->update(['company_id' => $company->id]);
        User::where('role', 'member')->update(['company_id' => $company->id]);

        // Seed all default features for the demo company
        $defaultKeys = Feature::defaults();
        foreach ($defaultKeys as $key) {
            CompanyFeature::create([
                'company_id'  => $company->id,
                'feature_key' => $key,
                'enabled'     => true,
                'enabled_by'  => $admin->id,
                'enabled_at'  => now(),
            ]);
        }

        // Also seed non-default features (disabled)
        $allKeys = Feature::pluck('key')->toArray();
        foreach (array_diff($allKeys, $defaultKeys) as $key) {
            CompanyFeature::create([
                'company_id'  => $company->id,
                'feature_key' => $key,
                'enabled'     => false,
                'enabled_by'  => null,
                'enabled_at'  => null,
            ]);
        }
    }
}
