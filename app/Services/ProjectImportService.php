<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Priority;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\StatusRule;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProjectImportService
{
    /**
     * Parse the uploaded file and return preview data without writing to DB.
     * Returns ['projects' => [...], 'tasks' => [...], 'errors' => [...]]
     */
    public function preview(UploadedFile $file, User $user): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext === 'csv') {
            return $this->parseCsv($file, $user);
        }

        return $this->parseXlsx($file, $user);
    }

    /**
     * Actually write validated data to DB inside a transaction.
     */
    public function import(array $preview, User $user): array
    {
        if (!empty($preview['errors'])) {
            return ['success' => false, 'message' => '資料驗證失敗，請修正後重新上傳'];
        }

        $importedProjects = 0;
        $importedTasks    = 0;

        DB::transaction(function () use ($preview, $user, &$importedProjects, &$importedTasks) {
            $projectMap = []; // name → Project model

            foreach ($preview['projects'] as $row) {
                $project = Project::create([
                    'name'        => $row['name'],
                    'project_no'  => $row['project_no'] ?? null,
                    'note'        => $row['note'] ?? null,
                    'category_id' => $row['category_id'],
                    'priority_id' => $row['priority_id'],
                    'status_id'   => $row['status_id'],
                    'start_date'  => $row['start_date'],
                    'due_date'    => $row['due_date'] ?? null,
                    'owner_id'    => $user->id,
                    'created_by'  => $user->id,
                    'company_id'  => $user->company_id,
                ]);

                ProjectMember::create(['project_id' => $project->id, 'user_id' => $user->id, 'role' => 'owner']);
                $projectMap[$row['name']] = $project;
                $importedProjects++;
            }

            foreach ($preview['tasks'] as $row) {
                $project = $projectMap[$row['project_name']] ?? null;
                if (!$project) continue;

                Task::create([
                    'project_id'  => $project->id,
                    'name'        => $row['name'],
                    'note'        => $row['note'] ?? null,
                    'start_date'  => $row['start_date'],
                    'end_date'    => $row['end_date'],
                    'progress'    => $row['progress'] ?? 0,
                    'assignee_id' => $row['assignee_id'] ?? null,
                    'status_id'   => $row['status_id'] ?? null,
                    'priority_id' => $row['priority_id'] ?? null,
                    'created_by'  => $user->id,
                ]);
                $importedTasks++;
            }
        });

        return [
            'success'           => true,
            'imported_projects' => $importedProjects,
            'imported_tasks'    => $importedTasks,
        ];
    }

    // ── Parsers ──────────────────────────────────────────────────────────────

    private function parseCsv(UploadedFile $file, User $user): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $headers = array_map('trim', fgetcsv($handle) ?: []);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (array_filter($row)) {
                $rows[] = array_combine($headers, array_map('trim', $row));
            }
        }
        fclose($handle);

        // Detect: task CSV if "所屬專案名稱" header exists
        if (in_array('所屬專案名稱', $headers)) {
            return $this->validateTasks(collect($rows), collect([]), $user);
        }

        return $this->validateProjects(collect($rows), collect([]), $user);
    }

    private function parseXlsx(UploadedFile $file, User $user): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());

        $projectRows = $this->sheetToRows($spreadsheet->getSheet(0));

        $taskRows = collect();
        if ($spreadsheet->getSheetCount() > 1) {
            $taskRows = $this->sheetToRows($spreadsheet->getSheet(1));
        }

        return $this->validateProjects($projectRows, $taskRows, $user);
    }

    private function sheetToRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): Collection
    {
        $data = $sheet->toArray(null, true, true, false);
        if (empty($data) || empty($data[0])) return collect();

        $headers = array_map(fn ($v) => trim((string) $v), $data[0]);
        $rows = collect();

        for ($i = 1; $i < count($data); $i++) {
            $row = array_map(fn ($v) => trim((string) $v), $data[$i]);
            if (!array_filter($row)) continue;
            $rows->push(array_combine($headers, $row));
        }

        return $rows;
    }

    // ── Validators ───────────────────────────────────────────────────────────

    private function validateProjects(Collection $projectRows, Collection $taskRows, User $user): array
    {
        $errors = [];
        $projects = [];

        $categories = Category::pluck('id', 'name');
        $priorities  = Priority::pluck('id', 'name');
        $statuses    = StatusRule::pluck('id', 'name');

        foreach ($projectRows as $i => $row) {
            $line = $i + 2;
            $rowErrors = [];

            $name = $row['專案名稱'] ?? '';
            if (empty($name)) $rowErrors[] = "第{$line}行：專案名稱必填";

            $startDate = $this->parseDate($row['開始日期'] ?? '');
            if (!$startDate) $rowErrors[] = "第{$line}行：開始日期格式錯誤（需 YYYY-MM-DD）";

            $dueDate = $this->parseDate($row['預計結束'] ?? '');
            if (!empty($row['預計結束']) && !$dueDate) {
                $rowErrors[] = "第{$line}行：預計結束日期格式錯誤";
            }

            $categoryId = $categories[$row['分類'] ?? ''] ?? null;
            if (!$categoryId) {
                $available = $categories->keys()->implode('、');
                $rowErrors[] = "第{$line}行：分類「{$row['分類']}」不存在（可用：{$available}）";
            }

            $priorityId = $priorities[$row['優先級'] ?? ''] ?? null;
            if (!$priorityId) {
                $available = $priorities->keys()->implode('、');
                $rowErrors[] = "第{$line}行：優先級「{$row['優先級']}」不存在（可用：{$available}）";
            }

            $statusId = $statuses[$row['狀態'] ?? ''] ?? null;
            if (!$statusId) {
                $available = $statuses->keys()->implode('、');
                $rowErrors[] = "第{$line}行：狀態「{$row['狀態']}」不存在（可用：{$available}）";
            }

            if ($rowErrors) {
                array_push($errors, ...$rowErrors);
                continue;
            }

            $projects[] = [
                'name'        => $name,
                'project_no'  => $row['專案編號'] ?? null,
                'note'        => $row['備註'] ?? null,
                'category_id' => $categoryId,
                'priority_id' => $priorityId,
                'status_id'   => $statusId,
                'start_date'  => $startDate,
                'due_date'    => $dueDate,
            ];
        }

        $projectNames = array_column($projects, 'name');

        return $this->validateTasks($taskRows, collect($projectNames), $user, $errors, $projects);
    }

    private function validateTasks(Collection $taskRows, Collection $projectNames, User $user, array $errors = [], array $projects = []): array
    {
        $tasks = [];
        $priorities = Priority::pluck('id', 'name');
        $statuses   = StatusRule::pluck('id', 'name');
        $members    = User::where('company_id', $user->company_id)->where('status', 'active')->pluck('id', 'email');

        foreach ($taskRows as $i => $row) {
            $line = $i + 2;
            $rowErrors = [];

            $projectName = $row['所屬專案名稱'] ?? '';
            $taskName    = $row['任務名稱'] ?? '';

            if (empty($taskName)) { $rowErrors[] = "任務第{$line}行：任務名稱必填"; }
            if (empty($projectName)) { $rowErrors[] = "任務第{$line}行：所屬專案名稱必填"; }

            if ($projectName && !$projectNames->contains($projectName)) {
                $rowErrors[] = "任務第{$line}行：找不到專案「{$projectName}」（需在專案Sheet中存在）";
            }

            $startDate = $this->parseDate($row['開始日期'] ?? '');
            $endDate   = $this->parseDate($row['截止日期'] ?? '');
            if (!$startDate) $rowErrors[] = "任務第{$line}行：開始日期格式錯誤";
            if (!$endDate)   $rowErrors[] = "任務第{$line}行：截止日期格式錯誤";

            $assigneeId = null;
            if (!empty($row['負責人Email'])) {
                $assigneeId = $members[$row['負責人Email']] ?? null;
                if (!$assigneeId) $rowErrors[] = "任務第{$line}行：找不到用戶 {$row['負責人Email']}";
            }

            $priorityId = empty($row['優先級']) ? null : ($priorities[$row['優先級']] ?? null);
            if (!empty($row['優先級']) && !$priorityId) $rowErrors[] = "任務第{$line}行：優先級「{$row['優先級']}」不存在";

            $statusId = empty($row['狀態']) ? null : ($statuses[$row['狀態']] ?? null);
            if (!empty($row['狀態']) && !$statusId) $rowErrors[] = "任務第{$line}行：狀態「{$row['狀態']}」不存在";

            $progress = (int) ($row['進度%'] ?? 0);

            if ($rowErrors) {
                array_push($errors, ...$rowErrors);
                continue;
            }

            $tasks[] = [
                'project_name' => $projectName,
                'name'         => $taskName,
                'note'         => $row['備註'] ?? null,
                'start_date'   => $startDate,
                'end_date'     => $endDate,
                'progress'     => max(0, min(100, $progress)),
                'assignee_id'  => $assigneeId,
                'priority_id'  => $priorityId,
                'status_id'    => $statusId,
            ];
        }

        return [
            'projects' => $projects,
            'tasks'    => $tasks,
            'errors'   => $errors,
        ];
    }

    private function parseDate(string $value): ?string
    {
        if (empty($value)) return null;
        // Try YYYY-MM-DD directly
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
        // Try other common formats
        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Template generator ───────────────────────────────────────────────────

    public function generateTemplate(User $user): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $categories = Category::pluck('name')->implode(' / ');
        $priorities  = Priority::pluck('name')->implode(' / ');
        $statuses    = StatusRule::pluck('name')->implode(' / ');
        $members     = User::where('company_id', $user->company_id)->where('status', 'active')->pluck('email')->implode(' / ');

        // Sheet 1 — Projects
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('專案');
        $projectHeaders = ['專案名稱*', '專案編號', '分類*', '優先級*', '狀態*', '開始日期*', '預計結束', '備註'];
        $this->writeTemplateHeader($sheet1, $projectHeaders);

        // Notes row
        $notes = ['必填', '可空白', "可用值：{$categories}", "可用值：{$priorities}", "可用值：{$statuses}", 'YYYY-MM-DD', 'YYYY-MM-DD 可空白', '可空白'];
        $sheet1->fromArray($notes, null, 'A2');
        $sheet1->getStyle('A2:H2')->getFont()->setItalic(true)->setSize(9);
        $sheet1->getStyle('A2:H2')->getFont()->getColor()->setARGB('FF9E9E9E');

        // Example row
        $sheet1->fromArray(['網站重建專案', 'WEB-001', explode(' / ', $categories)[0] ?? '開發', explode(' / ', $priorities)[0] ?? '高', explode(' / ', $statuses)[0] ?? '進行中', now()->toDateString(), now()->addMonths(3)->toDateString(), '範例備註'], null, 'A3');

        foreach (range('A', 'H') as $col) $sheet1->getColumnDimension($col)->setAutoSize(true);

        // Sheet 2 — Tasks
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('任務');
        $taskHeaders = ['所屬專案名稱*', '任務名稱*', '負責人Email', '優先級', '狀態', '開始日期*', '截止日期*', '進度%', '備註'];
        $this->writeTemplateHeader($sheet2, $taskHeaders);

        $taskNotes = ['需與專案Sheet名稱一致', '必填', "可用：{$members}", "可用值：{$priorities}", "可用值：{$statuses}", 'YYYY-MM-DD', 'YYYY-MM-DD', '0-100 整數', '可空白'];
        $sheet2->fromArray($taskNotes, null, 'A2');
        $sheet2->getStyle('A2:I2')->getFont()->setItalic(true)->setSize(9);
        $sheet2->getStyle('A2:I2')->getFont()->getColor()->setARGB('FF9E9E9E');

        $sheet2->fromArray(['網站重建專案', '設計首頁', '', '', '', now()->toDateString(), now()->addWeeks(2)->toDateString(), '0', ''], null, 'A3');

        foreach (range('A', 'I') as $col) $sheet2->getColumnDimension($col)->setAutoSize(true);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, '匯入範本.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function writeTemplateHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $headers): void
    {
        $sheet->fromArray($headers, null, 'A1');
        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF00897B']],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->freezePane('A3');
    }
}
