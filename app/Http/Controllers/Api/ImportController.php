<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProjectImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(private ProjectImportService $service) {}

    public function template(Request $request)
    {
        return $this->service->generateTemplate($request->user());
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv|max:10240',
        ]);

        $preview = $this->service->preview($request->file('file'), $request->user());

        return response()->json([
            'project_count' => count($preview['projects']),
            'task_count'    => count($preview['tasks']),
            'errors'        => $preview['errors'],
            'projects'      => array_map(fn ($p) => ['name' => $p['name'], 'start_date' => $p['start_date'], 'due_date' => $p['due_date']], $preview['projects']),
            'tasks'         => array_map(fn ($t) => ['name' => $t['name'], 'project_name' => $t['project_name']], $preview['tasks']),
            '_raw'          => $preview,
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv|max:10240',
        ]);

        $this->authorize('create', \App\Models\Project::class);

        $preview = $this->service->preview($request->file('file'), $request->user());
        $result  = $this->service->import($preview, $request->user());

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
