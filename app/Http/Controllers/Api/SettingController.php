<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Priority;
use App\Models\StatusRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    // ── Categories ────────────────────────────────────────────────────────────

    public function categoriesIndex(): JsonResponse
    {
        return response()->json(Category::orderBy('name')->get());
    }

    public function categoriesStore(Request $request): JsonResponse
    {
        $this->adminOnly($request);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string',
        ]);
        return response()->json(Category::create($data), 201);
    }

    public function categoriesUpdate(Request $request, Category $category): JsonResponse
    {
        $this->adminOnly($request);
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);
        $category->update($data);
        return response()->json($category);
    }

    public function categoriesDestroy(Request $request, Category $category): JsonResponse
    {
        $this->adminOnly($request);
        $category->delete();
        return response()->json(null, 204);
    }

    // ── Priorities ────────────────────────────────────────────────────────────

    public function prioritiesIndex(): JsonResponse
    {
        return response()->json(Priority::orderBy('sort_order')->get());
    }

    public function prioritiesStore(Request $request): JsonResponse
    {
        $this->adminOnly($request);
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'integer|min:0',
        ]);
        return response()->json(Priority::create($data), 201);
    }

    public function prioritiesUpdate(Request $request, Priority $priority): JsonResponse
    {
        $this->adminOnly($request);
        $data = $request->validate([
            'name' => 'sometimes|string|max:50',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);
        $priority->update($data);
        return response()->json($priority);
    }

    public function prioritiesDestroy(Request $request, Priority $priority): JsonResponse
    {
        $this->adminOnly($request);
        $priority->delete();
        return response()->json(null, 204);
    }

    // ── Status Rules ──────────────────────────────────────────────────────────

    public function statusesIndex(): JsonResponse
    {
        return response()->json(StatusRule::orderBy('sort_order')->get());
    }

    public function statusesStore(Request $request): JsonResponse
    {
        $this->adminOnly($request);
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'icon' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'sort_order' => 'integer|min:0',
        ]);
        return response()->json(StatusRule::create($data), 201);
    }

    public function statusesUpdate(Request $request, StatusRule $status): JsonResponse
    {
        $this->adminOnly($request);
        $data = $request->validate([
            'name' => 'sometimes|string|max:50',
            'icon' => 'nullable|string|max:10',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);
        $status->update($data);
        return response()->json($status);
    }

    public function statusesDestroy(Request $request, StatusRule $status): JsonResponse
    {
        $this->adminOnly($request);
        $status->delete();
        return response()->json(null, 204);
    }

    private function adminOnly(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, '僅管理員可操作');
    }
}
