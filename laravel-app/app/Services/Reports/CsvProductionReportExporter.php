<?php

namespace App\Services\Reports;

use App\DTOs\Reports\ProductionReport;
use RuntimeException;

final class CsvProductionReportExporter
{
    public function __construct(
        private readonly ProductionReportTableBuilder $tables,
        private readonly SpreadsheetCellSanitizer $sanitizer,
    ) {
    }

    public function export(
        ProductionReport $report
    ): string {
        $stream = fopen(
            'php://temp',
            'w+b'
        );

        if ($stream === false) {
            throw new RuntimeException(
                'The CSV export stream could not be opened.'
            );
        }

        fwrite(
            $stream,
            "\xEF\xBB\xBF"
        );

        foreach (
            $this->tables->rows($report)
            as $row
        ) {
            $safeRow = array_map(
                fn (mixed $value): string|int|float =>
                    $this->sanitizer
                        ->sanitize($value),
                $row
            );

            fputcsv(
                $stream,
                $safeRow,
                ',',
                '"',
                '\\',
                "\r\n"
            );
        }

        rewind($stream);

        $contents = stream_get_contents(
            $stream
        );

        fclose($stream);

        if ($contents === false) {
            throw new RuntimeException(
                'The CSV export could not be read.'
            );
        }

        return $contents;
    }
}
