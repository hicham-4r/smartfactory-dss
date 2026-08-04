<?php

namespace App\Http\Controllers\Reports;

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\Production\ProductionOrderStatus;
use App\Enums\Reports\ProductionReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\BrowseProductionReportRequest;
use App\Repositories\Contracts\ProductionAnalyticsRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\Reports\CsvProductionReportExporter;
use App\Services\Reports\PdfProductionReportExporter;
use App\Services\Reports\ProductionReportFilename;
use App\Services\Reports\ProductionReportService;
use App\Services\Reports\XlsxProductionReportExporter;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class ProductionReportController extends Controller
{
    public function __construct(
        private readonly ProductionReportService $reports,
        private readonly ProductionAnalyticsRepositoryInterface $analytics,
        private readonly CsvProductionReportExporter $csv,
        private readonly XlsxProductionReportExporter $xlsx,
        private readonly PdfProductionReportExporter $pdf,
        private readonly ProductionReportFilename $filenames,
        private readonly AuditLogService $audit,
    ) {
    }

    public function index(
        BrowseProductionReportRequest $request
    ): Response {
        $user = $request->user();

        abort_unless(
            $user !== null,
            403
        );

        $filter = $request->filter();
        $type = $request->reportType();

        $report = $this->reports->build(
            filter: $filter,
            type: $type,
            generatedBy: $user,
        );

        $timezoneOptions = config(
            'analytics.allowed_timezones',
            [
                'Africa/Casablanca',
                'UTC',
            ]
        );

        $timezoneOptions = is_array(
            $timezoneOptions
        )
            ? array_values(
                array_filter(
                    $timezoneOptions,
                    static fn (mixed $timezone): bool =>
                        is_string($timezone)
                        && trim($timezone) !== ''
                )
            )
            : [];

        if (! in_array(
            $filter->timezone,
            $timezoneOptions,
            true
        )) {
            $timezoneOptions[] =
                $filter->timezone;
        }

        $reportTypes = array_values(
            array_filter(
                ProductionReportType::cases(),
                static fn (
                    ProductionReportType $candidate
                ): bool =>
                    $candidate->canBeGeneratedBy(
                        $user
                    )
            )
        );

        return response()
            ->view(
                'reports.production.index',
                [
                    'report' => $report,
                    'reportType' => $type,
                    'reportTypes' => $reportTypes,
                    'filter' => $filter,
                    'productFamilies' =>
                        $this->analytics
                            ->filterableProductFamilies(
                                $filter
                            ),
                    'products' =>
                        $this->analytics
                            ->filterableProducts(
                                $filter
                            ),
                    'productionLines' =>
                        $this->analytics
                            ->filterableProductionLines(
                                $filter
                            ),
                    'shifts' =>
                        $this->analytics
                            ->filterableShifts(
                                $filter
                            ),
                    'productionOrders' =>
                        $this->analytics
                            ->filterableProductionOrders(
                                $filter
                            ),
                    'orderStatuses' => [
                        ProductionOrderStatus::InProgress,
                        ProductionOrderStatus::Completed,
                    ],
                    'timezoneOptions' =>
                        $timezoneOptions,
                    'canExport' =>
                        $user->can(
                            PermissionName
                                ::ExportProductionReports
                                ->value
                        ),
                ]
            )
            ->withHeaders(
                $this->privateHeaders()
            );
    }

    public function export(
        BrowseProductionReportRequest $request,
        string $format
    ): SymfonyResponse {
        $format = strtolower(
            trim($format)
        );

        abort_unless(
            in_array(
                $format,
                [
                    'csv',
                    'xlsx',
                    'pdf',
                ],
                true
            ),
            404
        );

        $user = $request->user();

        abort_unless(
            $user !== null
            && $user->can(
                PermissionName
                    ::ExportProductionReports
                    ->value
            ),
            403
        );

        $report = $this->reports->build(
            filter: $request->filter(),
            type: $request->reportType(),
            generatedBy: $user,
        );

        $contents = match ($format) {
            'csv' => $this->csv->export(
                $report
            ),
            'xlsx' => $this->xlsx->export(
                $report
            ),
            'pdf' => $this->pdf->export(
                $report
            ),
        };

        $filename = $this->filenames->make(
            report: $report,
            extension: $format,
        );

        $contentType = match ($format) {
            'csv' => 'text/csv; charset=UTF-8',
            'xlsx' =>
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
        };

        $this->audit->record(
            action:
                AuditAction
                    ::ProductionReportGenerated,
            actor: $user,
            metadata: [
                'report_type' =>
                    $report->type->value,
                'format' => $format,
                'filename' => $filename,
                'filters' =>
                    $report->filter->toArray(),
                'row_count' =>
                    count(
                        $report->primaryRows()
                    ),
                'quantity_unit_count' =>
                    count(
                        $report->summary->units
                    ),
            ],
            request: $request,
        );

        return response(
            $contents,
            200,
            [
                ...$this->privateHeaders(),
                'Content-Type' => $contentType,
                'Content-Disposition' =>
                    'attachment; filename="'
                    .$filename
                    .'"',
                'Content-Length' =>
                    (string) strlen($contents),
                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
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
