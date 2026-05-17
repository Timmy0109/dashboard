<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyFeature;
use App\Models\Feature;
use App\Models\FeatureChangeLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $query = $request->boolean('with_trashed')
            ? Company::withTrashed()->with(['creator'])->withCount(['managers', 'members'])
            : Company::with(['creator'])->withCount(['managers', 'members']);

        $companies = $query->get()->map(fn($c) => [
            'id'             => $c->id,
            'name'           => $c->name,
            'status'         => $c->status,
            'invite_code'            => $c->invite_code,
            'invite_code_expires_at' => $c->invite_code_expires_at?->format('Y-m-d'),
            'managers_count'         => $c->managers_count,
            'members_count'          => $c->members_count,
            'created_at'             => $c->created_at?->format('Y-m-d'),
            'deleted_at'             => $c->deleted_at?->format('Y-m-d'),
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

        $company = DB::transaction(function () use ($data, $request) {
            $company = Company::create([
                'name'       => $data['name'],
                'status'     => 'active',
                'created_by' => $request->user()->id,
            ]);

            $allKeys     = Feature::pluck('key')->toArray();
            $defaultKeys = Feature::where('is_default', true)->pluck('key')->toArray();

            $rows = [];
            foreach ($allKeys as $key) {
                $isDefault = in_array($key, $defaultKeys);
                $rows[] = [
                    'company_id'  => $company->id,
                    'feature_key' => $key,
                    'enabled'     => $isDefault,
                    'enabled_by'  => $isDefault ? $request->user()->id : null,
                    'enabled_at'  => $isDefault ? now() : null,
                ];
            }
            CompanyFeature::insert($rows);

            return $company;
        });

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

        $existing = CompanyFeature::where('company_id', $company->id)
            ->where('feature_key', $key)
            ->first();

        $oldValue = $existing ? (bool) $existing->enabled : false;

        CompanyFeature::updateOrCreate(
            ['company_id' => $company->id, 'feature_key' => $key],
            [
                'enabled'    => $data['enabled'],
                'enabled_by' => $request->user()->id,
                'enabled_at' => $data['enabled'] ? now() : null,
            ]
        );

        FeatureChangeLog::create([
            'company_id'  => $company->id,
            'feature_key' => $key,
            'old_value'   => $oldValue,
            'new_value'   => $data['enabled'],
            'changed_by'  => $request->user()->id,
        ]);

        return response()->json(['key' => $key, 'enabled' => $data['enabled']]);
    }

    // POST /api/admin/companies/{company}/invite-code
    public function regenerateInviteCode(Request $request, Company $company): JsonResponse
    {
        if ($err = $this->adminOnly($request)) return $err;

        try {
            $code = $company->regenerateInviteCode();
        } catch (\RuntimeException $e) {
            return response()->json(['message' => '邀請碼產生失敗，請稍後再試'], 422);
        }

        return response()->json([
            'invite_code'            => $code,
            'invite_code_expires_at' => $company->invite_code_expires_at?->format('Y-m-d'),
        ]);
    }

    // DELETE /api/admin/companies/{company}
    public function destroy(Request $request, Company $company): JsonResponse
    {
        if ($err = $this->adminOnly($request)) return $err;

        $company->delete();
        return response()->json(['message' => '公司已刪除']);
    }

    // POST /api/admin/companies/{company}/restore
    public function restore(Request $request, int $id): JsonResponse
    {
        if ($err = $this->adminOnly($request)) return $err;

        $company = Company::withTrashed()->findOrFail($id);
        $company->restore();
        return response()->json(['message' => '公司已還原']);
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
