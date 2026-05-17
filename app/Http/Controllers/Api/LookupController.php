<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Priority;
use App\Models\StatusRule;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class LookupController extends Controller
{
    public function categories(): JsonResponse
    {
        return response()->json(Category::where('is_active', true)->get(['id', 'name', 'color']));
    }

    public function priorities(): JsonResponse
    {
        return response()->json(Priority::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'color', 'sort_order']));
    }

    public function statuses(): JsonResponse
    {
        return response()->json(StatusRule::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'icon', 'color', 'sort_order']));
    }

    public function users(\Illuminate\Http\Request $request): JsonResponse
    {
        $user = $request->user();
        $query = User::where('status', 'active');

        if ($user->isAdmin()) {
            // Admin can scope to a specific company via ?company_id=
            if ($request->filled('company_id')) {
                $query->where('company_id', (int) $request->company_id);
            }
        } elseif ($user->company_id) {
            $query->where('company_id', $user->company_id);
        }

        return response()->json($query->orderBy('name')->get(['id', 'name', 'role']));
    }

    public function myFeatures(\Illuminate\Http\Request $request): JsonResponse
    {
        $user = $request->user();

        // Admin gets all features
        if ($user->isAdmin()) {
            return response()->json(\App\Models\Feature::pluck('key'));
        }

        if (! $user->company_id) {
            return response()->json([]);
        }

        $company = $user->company;
        return response()->json($company ? $company->enabledFeatureKeys() : []);
    }
}
