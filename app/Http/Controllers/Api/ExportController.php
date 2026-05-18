<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectExportService;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __construct(private ProjectExportService $service) {}

    public function project(Request $request, Project $project)
    {
        $this->authorize('view', $project);
        return $this->service->exportProject($project);
    }

    public function allProjects(Request $request)
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            $company = $user->company;
            if (! $company || ! $company->hasFeature('report.export')) {
                abort(403, '您的公司尚未開放匯出功能');
            }
        }

        return $this->service->exportAllProjects($user);
    }
}
