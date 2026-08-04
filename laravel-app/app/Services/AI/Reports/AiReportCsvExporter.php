<?php

namespace App\Services\AI\Reports;

use App\DTOs\AI\Reports\AiInferenceReport;
use App\Services\Reports\SpreadsheetCellSanitizer;
use RuntimeException;

final readonly class AiReportCsvExporter
{
    public function __construct(
        private AiInferenceReportTableBuilder $tables,
        private SpreadsheetCellSanitizer $sanitizer,
    ) {
    }

    public function export(AiInferenceReport $report): string
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('The AI report CSV stream could not be created.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Content type', 'Section', 'Metric', 'Value']);

        foreach ($this->tables->rows($report) as $row) {
            fputcsv(
                $stream,
                [
                    $this->sanitizer->sanitize($row['content_type']),
                    $this->sanitizer->sanitize($row['section']),
                    $this->sanitizer->sanitize($row['metric']),
                    $this->sanitizer->sanitize($row['value']),
                ],
            );
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false) {
            throw new RuntimeException('The AI report CSV could not be read.');
        }

        return $contents;
    }
}
