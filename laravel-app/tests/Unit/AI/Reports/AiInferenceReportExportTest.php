<?php

namespace Tests\Unit\AI\Reports;

use App\DTOs\AI\Reports\AiInferenceReport;
use App\DTOs\AI\Reports\AiReportExplanation;
use App\Services\AI\Reports\AiInferenceReportTableBuilder;
use App\Services\AI\Reports\AiReportCsvExporter;
use App\Services\AI\Reports\AiReportFilename;
use App\Services\AI\Reports\AiReportPdfExporter;
use App\Services\AI\Reports\AiReportXlsxExporter;
use App\Services\Reports\SpreadsheetCellSanitizer;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

final class AiInferenceReportExportTest extends TestCase
{
    public function test_ai_report_exports_include_verified_metrics(): void
    {
        $report = $this->report();
        $tables = new AiInferenceReportTableBuilder();
        $sanitizer = new SpreadsheetCellSanitizer();

        $csv = (new AiReportCsvExporter($tables, $sanitizer))
            ->export($report);
        $xlsx = (new AiReportXlsxExporter($tables, $sanitizer))
            ->export($report);
        $pdf = (new AiReportPdfExporter($tables))
            ->export($report);

        $this->assertStringContainsString('Random Forest Regressor', $csv);
        $this->assertStringContainsString('Mse', $csv);
        $this->assertStringContainsString('verified_fact', $csv);
        $this->assertStringNotContainsString('guarded_ai_narrative', $csv);
        $this->assertStringStartsWith('PK', $xlsx);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString(
            'simulated_prototype',
            $csv,
        );
    }

    public function test_exports_separate_verified_facts_from_guarded_ai_narrative(): void
    {
        $report = $this->report(
            explanation: $this->explanation(),
        );
        $tables = new AiInferenceReportTableBuilder();
        $sanitizer = new SpreadsheetCellSanitizer();

        $csv = (new AiReportCsvExporter($tables, $sanitizer))
            ->export($report);
        $xlsx = (new AiReportXlsxExporter($tables, $sanitizer))
            ->export($report);
        $pdf = (new AiReportPdfExporter($tables))
            ->export($report);

        $csvWithoutBom = str_starts_with($csv, "\xEF\xBB\xBF")
            ? substr($csv, 3)
            : $csv;
        $headerLine = strtok($csvWithoutBom, "\r\n");

        $this->assertIsString($headerLine);
        $this->assertSame(
            ['Content type', 'Section', 'Metric', 'Value'],
            str_getcsv($headerLine),
        );
        $this->assertStringContainsString('verified_fact', $csv);
        $this->assertStringContainsString('guarded_ai_metadata', $csv);
        $this->assertStringContainsString('guarded_ai_narrative', $csv);
        $this->assertStringContainsString(
            'The verified forecast remains close to recent history.',
            $csv,
        );
        $this->assertStringContainsString(
            'laravel-ai-forecast-test',
            $csv,
        );

        $this->assertStringContainsString('guarded_ai_narrative', $xlsx);
        $this->assertStringContainsString(
            'The verified forecast remains close to recent history.',
            $xlsx,
        );
        $this->assertStringContainsString('GUARDED AI NARRATIVE', $pdf);
        $this->assertStringContainsString(
            'The verified forecast remains close to recent history.',
            $pdf,
        );
    }

    public function test_report_rejects_explanation_linked_to_another_inference(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->report(
            explanation: $this->explanation(
                inferenceRequestId: 'laravel-ai-another-inference',
            ),
        );
    }

    public function test_spreadsheet_formula_prefix_is_neutralized(): void
    {
        $report = $this->report([
            'product_code' => '=DANGEROUS()',
        ]);

        $csv = (new AiReportCsvExporter(
            new AiInferenceReportTableBuilder(),
            new SpreadsheetCellSanitizer(),
        ))->export($report);

        $this->assertStringContainsString("'=DANGEROUS()", $csv);
    }

    public function test_filename_is_sanitized(): void
    {
        $filename = (new AiReportFilename())->make(
            $this->report(),
            'pdf',
        );

        $this->assertMatchesRegularExpression(
            '/^smartfactory-ai-production-forecast-[0-9]{8}-[0-9]{6}\.pdf$/',
            $filename,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function report(
        array $context = [],
        ?AiReportExplanation $explanation = null,
    ): AiInferenceReport {
        return new AiInferenceReport(
            token: '11111111-1111-4111-8111-111111111111',
            operation: 'production_forecast',
            generatedAt: CarbonImmutable::parse('2026-08-03T20:00:00Z'),
            generatedByName: 'Test Administrator',
            requestId: 'laravel-ai-forecast-test',
            context: [
                'production_line_code' => 'LINE-01',
                'quantity_unit' => 'L',
                ...$context,
            ],
            result: [
                'predicted_good_quantity_next_day' => 108493.696,
                'prediction_date' => '2026-08-04',
                'metadata' => [
                    'model_run_id' =>
                        '11111111-1111-4111-8111-111111111111',
                    'source_feature_run_id' =>
                        '22222222-2222-4222-8222-222222222222',
                    'model_name' => 'Random Forest Regressor',
                    'data_classification' => 'simulated_prototype',
                    'limitations' => [
                        'Metrics are based only on simulated-prototype data.',
                    ],
                ],
            ],
            metrics: [
                'metrics' => [
                    'test_metrics' => [
                        'mae' => 10.0,
                        'mse' => 400.0,
                        'rmse' => 20.0,
                        'r2' => 0.8,
                    ],
                ],
                'metric_derivations' => [],
            ],
            explanation: $explanation,
        );
    }

    private function explanation(
        string $inferenceRequestId = 'laravel-ai-forecast-test',
    ): AiReportExplanation {
        return AiReportExplanation::fromGeneratedResult(
            payload: [
                'status' => 'generated',
                'contract_name' => 'smartfactory.llm.explanation',
                'contract_version' => 'v1',
                'explanation_id' =>
                    '33333333-3333-4333-8333-333333333333',
                'explanation_type' => 'production_forecast',
                'role' => 'administrator',
                'language' => 'en',
                'data_classification' => 'simulated_prototype',
                'narrative' => [
                    'summary' =>
                        'The verified forecast remains close to recent history.',
                    'observations' => [
                        'The verified forecast is 108493.696 L.',
                    ],
                    'suggested_human_checks' => [
                        'Review validated downtime records.',
                    ],
                    'limitations' => [
                        'Simulated-prototype data only.',
                    ],
                    'referenced_fact_keys' => [
                        'facts.result.predicted_good_quantity_next_day',
                    ],
                ],
                'request_id' => 'laravel-ai-explanation-test',
            ],
            operation: 'production_forecast',
            inferenceRequestId: $inferenceRequestId,
            attachedAt: CarbonImmutable::parse('2026-08-03T20:01:00Z'),
        );
    }
}
