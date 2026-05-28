<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TaskActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * GET /api/activity?scope=company|project:{id}&cursor=…
     *
     * Scope rules:
     *   - scope=company (default): activities from projects user is member of, owner of,
     *     or all company projects if admin/manager.
     *   - scope=project:{id}: only activities for one project; user must be member.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $scope = $request->query('scope', 'company');

        if (str_starts_with($scope, 'project:')) {
            $projectId = (int) substr($scope, 8);
            $project = Project::find($projectId);
            if (! $project) {
                abort(404);
            }
            if (! $this->canAccessProject($user, $project)) {
                abort(403, '無權限存取此專案');
            }

            $projectIds = [$projectId];
        } else {
            $projectIds = $this->visibleProjectIds($user);
        }

        if (empty($projectIds)) {
            return response()->json(['data' => [], 'next_cursor' => null]);
        }

        $perPage = min((int) $request->query('per_page', 50), 100);

        $query = TaskActivity::query()
            ->whereHas('task', fn ($q) => $q->whereIn('project_id', $projectIds))
            ->with([
                'actor:id,name,avatar',
                'task:id,name,project_id',
                'task.project:id,name',
            ])
            ->orderByDesc('created_at');

        $paginated = $query->cursorPaginate($perPage);

        $data = $paginated->getCollection()->map(fn ($a) => [
            'id'           => $a->id,
            'event'        => $a->event,
            'task_id'      => $a->task_id,
            'task_name'    => $a->task?->name,
            'project_id'   => $a->task?->project_id,
            'project_name' => $a->task?->project?->name,
            'payload'      => $a->payload,
            'actor'        => $a->actor ? [
                'id'         => $a->actor->id,
                'name'       => $a->actor->name,
                'avatar_url' => $a->actor->avatar_url,
            ] : null,
            'created_at'   => $a->created_at?->format('Y/m/d H:i'),
        ]);

        return response()->json([
            'data'        => $data,
            'next_cursor' => optional($paginated->nextCursor())->encode(),
        ]);
    }

    /** Returns project ids the user can see activity from. */
    private function visibleProjectIds($user): array
    {
        // admin / manager: all projects in same company
        if ($user->isAdmin() || $user->isManager()) {
            return Project::where('company_id', $user->company_id)->pluck('id')->toArray();
        }

        // member: only projects they own or are explicit member of
        return Project::query()
            ->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('members', fn ($q2) => $q2->where('user_id', $user->id));
            })
            ->where('company_id', $user->company_id)
            ->pluck('id')
            ->toArray();
    }

    private function canAccessProject($user, Project $project): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        if ($project->company_id !== $user->company_id) {
            return false;
        }
        return $project->owner_id === $user->id
            || $project->members()->where('user_id', $user->id)->exists();
    }
}
