<?php

namespace Tests\Unit\Reports;

use App\Services\Reports\SimplePdfWriter;
use App\Services\Reports\SpreadsheetCellSanitizer;
use App\Services\Reports\StoredZipWriter;
use PHPUnit\Framework\TestCase;

final class NativeReportWriterTest extends TestCase
{
    public function test_spreadsheet_formula_prefixes_are_neutralized(): void
    {
        $sanitizer = new SpreadsheetCellSanitizer();

        $this->assertSame(
            "'=SUM(A1:A2)",
            $sanitizer->sanitize('=SUM(A1:A2)')
        );

        $this->assertSame(
            "'@danger",
            $sanitizer->sanitize('@danger')
        );

        $this->assertSame(
            12.5,
            $sanitizer->sanitize(12.5)
        );
    }

    public function test_native_zip_writer_creates_openxml_compatible_container(): void
    {
        $writer = new StoredZipWriter();

        $writer->add(
            'xl/workbook.xml',
            '<workbook/>'
        );

        $contents = $writer->finish();

        $this->assertStringStartsWith(
            "PK\x03\x04",
            $contents
        );

        $this->assertStringContainsString(
            'xl/workbook.xml',
            $contents
        );

        $this->assertStringEndsWith(
            "\x00\x00",
            $contents
        );
    }

    public function test_native_pdf_writer_creates_styled_pdf_document(): void
    {
        $contents = (new SimplePdfWriter())->write([
            'SmartFactory DSS',
            'Production report',
            'Target versus actual production',
        ]);

        $this->assertStringStartsWith(
            '%PDF-1.4',
            $contents
        );

        $this->assertStringContainsString(
            'SMARTFACTORY DSS',
            $contents
        );

        $this->assertStringContainsString(
            'Page 1 of 1',
            $contents
        );

        $this->assertStringContainsString(
            'Helvetica-Bold',
            $contents
        );

        $this->assertStringEndsWith(
            '%%EOF',
            $contents
        );
    }
}
