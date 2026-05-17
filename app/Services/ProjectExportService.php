<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectExportService
{
    private const PRIMARY = 'FF00897B';
    private const HEADER_TEXT = 'FFFFFFFF';
    private const ALT_ROW = 'FFF5F5F5';

    public function exportProject(Project $project): StreamedResponse
    {
        $project->load(['owner', 'category', 'priority', 'status', 'tasks.assignee', 'tasks.status', 'tasks.priority']);

        $spreadsheet = new Spreadsheet();

        $this->buildProjectSheet($spreadsheet->getActiveSheet(), $project);

        $tasksSheet = $spreadsheet->createSheet();
        $tasksSheet->setTitle('任務列表');
        $this->buildTasksSheet($tasksSheet, $project->tasks->all());

        $spreadsheet->setActiveSheetIndex(0);

        $filename = '專案報告_' . str_replace(['/', '\\', ' '], '_', $project->name) . '_' . now()->format('Ymd') . '.xlsx';

        return $this->stream($spreadsheet, $filename);
    }

    public function exportAllProjects(User $user): StreamedResponse
    {
        $projects = match ($user->role) {
            'admin'   => Project::with(['owner', 'status', 'priority', 'tasks'])->latest()->get(),
            'manager' => Project::with(['owner', 'status', 'priority', 'tasks'])->where('company_id', $user->company_id)->latest()->get(),
            default   => Project::with(['owner', 'status', 'priority', 'tasks'])->whereHas('members', fn ($q) => $q->where('user_id', $user->id))->latest()->get(),
        };

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('專案概覽');

        $headers = ['#', '專案名稱', '專案編號', '負責人', '狀態', '優先級', '開始日期', '預計結束', '整體進度', '總任務', '已完成', '逾期任務', '是否完成'];
        $this->writeHeader($sheet, $headers, 1);

        foreach ($projects as $idx => $project) {
            $row = $idx + 2;
            $overdue = $project->tasks->filter(fn ($t) => !$t->is_completed && $t->end_date && $t->end_date->isPast())->count();
            $completed = $project->tasks->where('is_completed', true)->count();
            $sheet->fromArray([
                $idx + 1,
                $project->name,
                $project->project_no ?? '',
                $project->owner?->name ?? '',
                $project->status?->name ?? '',
                $project->priority?->name ?? '',
                $project->start_date?->toDateString() ?? '',
                $project->due_date?->toDateString() ?? '',
                $project->progress_percent . '%',
                $project->tasks->count(),
                $completed,
                $overdue,
                $project->is_completed ? '是' : '否',
            ], null, 'A' . $row);

            if ($idx % 2 === 1) {
                $sheet->getStyle('A' . $row . ':M' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ALT_ROW);
            }
        }

        $this->autoWidth($sheet, count($headers));

        $filename = '全部專案_' . now()->format('Ymd') . '.xlsx';

        return $this->stream($spreadsheet, $filename);
    }

    private function buildProjectSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, Project $project): void
    {
        $sheet->setTitle('專案資訊');

        $infoRows = [
            ['專案名稱', $project->name],
            ['專案編號', $project->project_no ?? ''],
            ['負責人',   $project->owner?->name ?? ''],
            ['分類',     $project->category?->name ?? ''],
            ['優先級',   $project->priority?->name ?? ''],
            ['狀態',     $project->status?->name ?? ''],
            ['開始日期', $project->start_date?->toDateString() ?? ''],
            ['預計結束', $project->due_date?->toDateString() ?? ''],
            ['整體進度', $project->progress_percent . '%'],
            ['是否完成', $project->is_completed ? '是' : '否'],
            ['備註',     $project->note ?? ''],
        ];

        // Title row
        $sheet->mergeCells('A1:B1');
        $sheet->setCellValue('A1', '專案資訊');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => self::HEADER_TEXT]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::PRIMARY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        foreach ($infoRows as $i => $row) {
            $r = $i + 2;
            $sheet->setCellValue('A' . $r, $row[0]);
            $sheet->setCellValue('B' . $r, $row[1]);
            $sheet->getStyle('A' . $r)->getFont()->setBold(true);
            $sheet->getStyle('A' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8F5E9');
            if ($i % 2 === 1) {
                $sheet->getStyle('B' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ALT_ROW);
            }
        }

        $sheet->getColumnDimension('A')->setWidth(16);
        $sheet->getColumnDimension('B')->setWidth(40);
    }

    private function buildTasksSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $tasks): void
    {
        $headers = ['#', '任務名稱', '負責人', '開始日期', '截止日期', '狀態', '優先級', '進度', '是否完成', '是否逾期', '備註'];
        $this->writeHeader($sheet, $headers, 1);

        $today = now()->toDateString();
        foreach ($tasks as $idx => $task) {
            $row = $idx + 2;
            $isOverdue = !$task->is_completed && $task->end_date && $task->end_date->toDateString() < $today;
            $sheet->fromArray([
                $idx + 1,
                $task->name,
                $task->assignee?->name ?? '未指派',
                $task->start_date?->toDateString() ?? '',
                $task->end_date?->toDateString() ?? '',
                $task->status?->name ?? '',
                $task->priority?->name ?? '',
                $task->progress . '%',
                $task->is_completed ? '是' : '否',
                $isOverdue ? '是' : '否',
                $task->note ?? '',
            ], null, 'A' . $row);

            if ($isOverdue) {
                $sheet->getStyle('E' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFEF5350'));
            }

            if ($idx % 2 === 1) {
                $sheet->getStyle('A' . $row . ':K' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ALT_ROW);
            }
        }

        $this->autoWidth($sheet, count($headers));
    }

    private function writeHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $headers, int $row): void
    {
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $col++;
        }

        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => self::HEADER_TEXT]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::PRIMARY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);

        // Freeze header row
        $sheet->freezePane('A' . ($row + 1));
    }

    private function autoWidth(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $colCount): void
    {
        for ($i = 0; $i < $colCount; $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function stream(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
