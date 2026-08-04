<?php

namespace App\Services\Reports;

use App\DTOs\Reports\ProductionReport;

final class PdfProductionReportExporter
{
    public function __construct(
        private readonly SimplePdfWriter $writer,
    ) {
    }

    public function export(
        ProductionReport $report
    ): string {
        return $this->writer->writeReport(
            $report
        );
    }
}
