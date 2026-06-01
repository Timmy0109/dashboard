<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectBudgetLog;
use App\Models\ProjectMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->query('company_id');

        $projects = match ($user->role) {
            'admin' => Project::with(['owner', 'category', 'priority', 'status'])
                ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                ->latest()->get(),
            'manager' => Project::with(['owner', 'category', 'priority', 'status'])
                ->where('company_id', $user->company_id)
                ->latest()->get(),
            default => Project::with(['owner', 'category', 'priority', 'status'])
                ->whereHas('members', fn($q) => $q->where('user_id', $user->id))
                ->latest()->get(),
        };

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'note' => 'nullable|string',
            'project_no' => 'nullable|string|max:50',
            'category_id' => 'required|exists:categories,id',
            'priority_id' => 'required|exists:priorities,id',
            'status_id' => 'required|exists:status_rules,id',
            'start_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'company_id' => 'sometimes|nullable|exists:companies,id',
            'total_budget' => 'required|numeric|min:0|max:9999999999.99',
        ]);

        $creator = $request->user();
        $project = Project::create(array_merge($validated, [
            'owner_id' => $creator->id,
            'created_by' => $creator->id,
            'company_id' => $validated['company_id'] ?? $creator->company_id,
        ]));

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'role' => 'owner',
        ]);

        return response()->json($project->load(['owner', 'category', 'priority', 'status']), 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json(
            $project->load(['owner', 'category', 'priority', 'status', 'tasks.assignee', 'tasks.status', 'tasks.priority', 'members'])
        );
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'note' => 'nullable|string',
            'project_no' => 'nullable|string|max:50',
            'category_id' => 'sometimes|exists:categories,id',
            'priority_id' => 'sometimes|exists:priorities,id',
            'status_id' => 'sometimes|exists:status_rules,id',
            'start_date' => 'sometimes|date',
            'due_date' => 'nullable|date',
            'completed_date' => 'nullable|date',
            'is_completed' => 'sometimes|boolean',
            'total_budget' => 'sometimes|required|numeric|min:0|max:9999999999.99',
        ]);

        $oldBudget = (float) $project->total_budget;
        $project->update($validated);

        if (array_key_exists('total_budget', $validated)) {
            $newBudget = (float) $validated['total_budget'];
            if (abs($oldBudget - $newBudget) > 0.001) {
                ProjectBudgetLog::create([
                    'project_id'  => $project->id,
                    'actor_id'    => $request->user()->id,
                    'from_amount' => $oldBudget,
                    'to_amount'   => $newBudget,
                    'created_at'  => now(),
                ]);
            }
        }

        return response()->json($project->load(['owner', 'category', 'priority', 'status']));
    }

    // GET /api/projects/{project}/budget-logs — manager/admin only
    public function budgetLogs(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project); // 與編輯權同等：只有 manager/admin 看得到

        $logs = ProjectBudgetLog::where('project_id', $project->id)
            ->with('actor:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json($logs);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(null, 204);
    }

    public function members(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json($project->members()->withPivot('role')->get());
    }

    public function addMember(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        ProjectMember::firstOrCreate([
            'project_id' => $project->id,
            'user_id' => $validated['user_id'],
        ], ['role' => 'member']);

        return response()->json($project->members()->withPivot('role')->get());
    }

    public function removeMember(Request $request, Project $project, int $userId): JsonResponse
    {
        $this->authorize('update', $project);

        ProjectMember::where('project_id', $project->id)
            ->where('user_id', $userId)
            ->where('role', '!=', 'owner')
            ->delete();

        return response()->json(null, 204);
    }
}
