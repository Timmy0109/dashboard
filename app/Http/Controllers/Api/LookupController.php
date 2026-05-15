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

    public function users(): JsonResponse
    {
        return response()->json(User::where('status', 'active')->get(['id', 'name', 'role']));
    }
}
