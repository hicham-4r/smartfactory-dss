<?php

namespace App\Services\Reports;

use App\DTOs\Reports\ProductionReport;

final class ProductionReportFilename
{
    public function make(
        ProductionReport $report,
        string $extension
    ): string {
        $extension = strtolower(
            trim($extension)
        );

        abort_unless(
            in_array(
                $extension,
                [
                    'csv',
                    'xlsx',
                    'pdf',
                ],
                true
            ),
            404
        );

        $base = implode(
            '-',
            [
                'smartfactory-production',
                $report->type->value,
                $report->filter->startDateString(),
                $report->filter->endDateString(),
                $report->generatedAt
                    ->format('YmdHis'),
            ]
        );

        $base = preg_replace(
            '/[^a-z0-9._-]+/i',
            '-',
            $base
        ) ?? 'smartfactory-production-report';

        $base = trim(
            $base,
            '-.'
        );

        return mb_substr(
            $base,
            0,
            140
        ).'.'.$extension;
    }
}
