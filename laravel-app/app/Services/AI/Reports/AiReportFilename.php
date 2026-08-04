<?php

namespace App\Services\AI\Reports;

use App\DTOs\AI\Reports\AiInferenceReport;

final class AiReportFilename
{
    public function make(
        AiInferenceReport $report,
        string $extension,
    ): string {
        $operation = preg_replace(
            '/[^a-z0-9-]+/',
            '-',
            strtolower(str_replace('_', '-', $report->operation)),
        ) ?? 'ai-report';

        return sprintf(
            'smartfactory-ai-%s-%s.%s',
            trim($operation, '-'),
            $report->generatedAt->utc()->format('Ymd-His'),
            strtolower($extension),
        );
    }
}
