<?php

namespace App\Http\Controllers\Reports;

use App\DTOs\AI\Reports\AiInferenceReport;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AI\Reports\AiInferenceReportStore;
use App\Services\AI\Reports\AiReportCsvExporter;
use App\Services\AI\Reports\AiReportFilename;
use App\Services\AI\Reports\AiReportPdfExporter;
use App\Services\AI\Reports\AiReportXlsxExporter;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AiInferenceReportController extends Controller
{
    public function __construct(
        private readonly AiInferenceReportStore $store,
        private readonly AiReportCsvExporter $csv,
        private readonly AiReportXlsxExporter $xlsx,
        private readonly AiReportPdfExporter $pdf,
        private readonly AiReportFilename $filenames,
        private readonly AuditLogService $audit,
    ) {
    }

    public function export(
        Request $request,
        string $token,
        string $format,
    ): Response {
        $format = strtolower(trim($format));

        abort_unless(
            in_array($format, ['csv', 'xlsx', 'pdf'], true),
            404,
        );

        $snapshot = $this->store->retrieve($request, $token);
        abort_unless(is_array($snapshot), 404);

        $user = $request->user();
        abort_unless($user instanceof User && $user->is_active, 403);
        $this->authorizeOperation($user, (string) ($snapshot['operation'] ?? ''));

        $report = AiInferenceReport::fromSnapshot($snapshot);
        $contents = match ($format) {
            'csv' => $this->csv->export($report),
            'xlsx' => $this->xlsx->export($report),
            'pdf' => $this->pdf->export($report),
        };
        $filename = $this->filenames->make($report, $format);

        $this->audit->record(
            action: 'ai.inference-report.generated',
            actor: $user,
            metadata: [
                'operation' => $report->operation,
                'task' => $report->task(),
                'format' => $format,
                'filename' => $filename,
                'model_run_id' => $report->result['metadata']['model_run_id'] ?? null,
                'source_feature_run_id' =>
                    $report->result['metadata']['source_feature_run_id'] ?? null,
                'inference_request_id' => $report->requestId,
                'data_classification' => 'simulated_prototype',
                'metrics_included' => $report->metrics !== null,
                'explanation_included' => $report->hasExplanation(),
                'explanation_id' =>
                    $report->explanation?->explanationId,
                'explanation_request_id' =>
                    $report->explanation?->requestId,
                'explanation_language' =>
                    $report->explanation?->language,
            ],
            request: $request,
        );

        return response(
            $contents,
            200,
            [
                ...$this->privateHeaders(),
                'Content-Type' => match ($format) {
                    'csv' => 'text/csv; charset=UTF-8',
                    'xlsx' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'pdf' => 'application/pdf',
                },
                'Content-Disposition' =>
                    'attachment; filename="'.$filename.'"',
                'Content-Length' => (string) strlen($contents),
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function authorizeOperation(User $user, string $operation): void
    {
        $allowed = match ($operation) {
            'production_forecast',
            'production_anomaly' =>
                $user->can(PermissionName::ExportProductionReports->value)
                || $user->can(PermissionName::ViewAdministratorDashboard->value),
            'maintenance_risk' =>
                $user->can(PermissionName::GenerateMaintenanceReports->value)
                || $user->can(PermissionName::ViewAdministratorDashboard->value),
            default => false,
        };

        abort_unless($allowed, 403);
    }

    /**
     * @return array<string, string>
     */
    private function privateHeaders(): array
    {
        return [
            'Cache-Control' =>
                'no-store, no-cache, must-revalidate, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }
}
