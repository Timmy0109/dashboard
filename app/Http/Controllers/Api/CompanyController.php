<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyFeature;
use App\Models\Feature;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    private function adminOnly(Request $request): ?JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => '權限不足'], 403);
        }
        return null;
    }

    // GET /api/admin/companies
    public function index(Request $request): JsonResponse
    {
        if ($err = $this->adminOnly($request)) return $err;

        $companies = Company::with(['creator'])->withCount(['managers', 'members'])->get()
            ->map(fn($c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'status'         => $c->status,
                'invite_code'    => $c->invite_code,
                'managers_count' => $c->managers_count,
                'members_count'  => $c->members_count,
                'created_at'     => $c->created_at?->format('Y-m-d'),
            ]);

        return response()->json($companies);
    }

    // POST /api/admin/companies
    public function store(Request $request): JsonResponse
    {
        if ($err = $this->adminOnly($request)) return $err;

        $data = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $company = Company::create([
            'name'       => $data['name'],
            'status'     => 'active',
            'created_by' => $request->user()->id,
        ]);

        // Seed default features
        foreach (Feature::defaults() as $key) {
            CompanyFeature::create([
                'company_id'  => $company->id,
                'feature_key' => $key,
                'enabled'     => true,
                'enabled_by'  => $request->user()->id,
                'enabled_at'  => now(),
            ]);
        }
        // Non-default features → disabled
        foreach (array_diff(Feature::pluck('key')->toArray(), Feature::defaults()) as $key) {
            CompanyFeature::create([
                'company_id'  => $company->id,
                'feature_key' => $key,
                'enabled'     => false,
            ]);
        }

        return response()->json($company, 201);
    }

    // PUT /api/admin/companies/{company}
    public function update(Request $request, Company $company): JsonResponse
    {
        if ($err = $this->adminOnly($request)) return $err;

        $data = $request->validate([
            'name'   => 'sometimes|string|max:100',
            'status' => 'sometimes|in:active,suspended',
        ]);

        $company->update($data);
        return response()->json($company);
    }

    // GET /api/admin/companies/{company}/features
    public function features(Request $request, Company $company): JsonResponse
    {
        if ($err = $this->adminOnly($request)) return $err;

        $allFeatures = Feature::all()->keyBy('key');
        $companyFeatures = $company->companyFeatures()->get()->keyBy('feature_key');

        $result = $allFeatures->map(function ($feature) use ($companyFeatures, $company) {
            $cf = $companyFeatures->get($feature->key);
            return [
                'key'         => $feature->key,
                'name'        => $feature->name,
                'description' => $feature->description,
                'category'    => $feature->category,
                'is_default'  => $feature->is_default,
                'enabled'     => $cf ? (bool) $cf->enabled : false,
                'enabled_at'  => $cf?->enabled_at?->format('Y-m-d'),
            ];
        })->values();

        return response()->json($result);
    }

    // PUT /api/admin/companies/{company}/features/{key}
    public function toggleFeature(Request $request, Company $company, string $key): JsonResponse
    {
        if ($err = $this->adminOnly($request)) return $err;

        if (! Feature::where('key', $key)->exists()) {
            return response()->json(['message' => '功能不存在'], 404);
        }

        $data = $request->validate(['enabled' => 'required|boolean']);

        CompanyFeature::updateOrCreate(
            ['company_id' => $company->id, 'feature_key' => $key],
            [
                'enabled'    => $data['enabled'],
                'enabled_by' => $request->user()->id,
                'enabled_at' => $data['enabled'] ? now() : null,
            ]
        );

        return response()->json(['key' => $key, 'enabled' => $data['enabled']]);
    }

    // POST /api/admin/companies/{company}/invite-code
    public function regenerateInviteCode(Request $request, Company $company): JsonResponse
    {
        if ($err = $this->adminOnly($request)) return $err;

        $code = $company->regenerateInviteCode();
        return response()->json(['invite_code' => $code]);
    }

    // GET /api/admin/companies/{company}/users
    public function users(Request $request, Company $company): JsonResponse
    {
        if ($err = $this->adminOnly($request)) return $err;

        $users = $company->users()->get()->map(fn($u) => [
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'role'       => $u->role,
            'status'     => $u->status,
            'created_at' => $u->created_at?->format('Y-m-d'),
        ]);

        return response()->json($users);
    }
}
