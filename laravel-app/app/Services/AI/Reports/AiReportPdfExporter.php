<?php

namespace App\Services\AI\Reports;

use App\DTOs\AI\Reports\AiInferenceReport;

final readonly class AiReportPdfExporter
{
    private const PAGE_WIDTH = 842;
    private const PAGE_HEIGHT = 595;
    private const LEFT = 36;
    private const RIGHT = 806;
    private const CONTENT_TOP = 492;
    private const CONTENT_BOTTOM = 58;

    public function __construct(
        private AiInferenceReportTableBuilder $tables,
    ) {
    }

    public function export(AiInferenceReport $report): string
    {
        $pages = $this->layout($report);
        $objects = [];
        $pageIds = [];
        $contentIds = [];
        $regularFontId = 3;
        $boldFontId = 4;
        $nextId = 5;

        foreach ($pages as $_) {
            $pageIds[] = $nextId++;
            $contentIds[] = $nextId++;
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = implode(' ', array_map(
            static fn (int $id): string => $id.' 0 R',
            $pageIds,
        ));
        $objects[2] = '<< /Type /Pages /Kids ['.$kids.'] /Count '
            .count($pageIds).' >>';
        $objects[$regularFontId] =
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[$boldFontId] =
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $pageCount = count($pages);
        foreach ($pages as $index => $page) {
            $stream = $this->pageHeader($report, $index + 1);
            $stream .= $page;
            $stream .= $this->pageFooter($index + 1, $pageCount);

            $contentId = $contentIds[$index];
            $pageId = $pageIds[$index];
            $objects[$contentId] = '<< /Length '.strlen($stream).">>\nstream\n"
                .$stream."endstream";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R '
                .'/MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] '
                .'/Resources << /Font << /F1 '.$regularFontId.' 0 R '
                .'/F2 '.$boldFontId.' 0 R >> >> '
                .'/Contents '.$contentId.' 0 R >>';
        }

        return $this->serialize($objects);
    }

    /**
     * @return list<string>
     */
    private function layout(AiInferenceReport $report): array
    {
        $pages = [];
        $stream = '';
        $y = self::CONTENT_TOP;
        $alternate = false;
        $lastBand = null;

        foreach ($this->tables->rows($report) as $row) {
            $bandKey = $row['content_type'].'|'.$row['section'];

            if ($bandKey !== $lastBand) {
                if ($y - 25 < self::CONTENT_BOTTOM) {
                    $pages[] = $stream;
                    $stream = '';
                    $y = self::CONTENT_TOP;
                    $alternate = false;
                }

                $stream .= $this->sectionBand(
                    $row['content_type'],
                    $row['section'],
                    $y,
                );
                $y -= 27;
                $lastBand = $bandKey;
            }

            $labelLines = $this->wrap($row['metric'], 35);
            $valueLines = $this->wrap($this->format($row['value']), 82);
            $lineCount = max(count($labelLines), count($valueLines));
            $height = max(22, 10 + ($lineCount * 10));

            if ($y - $height < self::CONTENT_BOTTOM) {
                $pages[] = $stream;
                $stream = '';
                $y = self::CONTENT_TOP;
                $alternate = false;
                $stream .= $this->sectionBand(
                    $row['content_type'],
                    $row['section'].' (continued)',
                    $y,
                );
                $y -= 27;
            }

            $stream .= $this->metricRow(
                labelLines: $labelLines,
                valueLines: $valueLines,
                top: $y,
                height: $height,
                alternate: $alternate,
            );
            $y -= $height;
            $alternate = ! $alternate;
        }

        if ($stream !== '' || $pages === []) {
            $pages[] = $stream;
        }

        return $pages;
    }

    private function pageHeader(AiInferenceReport $report, int $page): string
    {
        $title = $page === 1
            ? $report->title()
            : $report->title().' - continued';
        $generated = $report->generatedAt
            ->utc()
            ->format('Y-m-d H:i:s').' UTC';

        $stream = $this->rectangle(0, 535, self::PAGE_WIDTH, 60, '0.071 0.208 0.357');
        $stream .= $this->text($title, 36, 568, 20, 'F2', '1 1 1');
        $stream .= $this->text(
            'Verified AI analysis | simulated_prototype',
            36,
            548,
            10,
            'F1',
            '0.82 0.90 0.98',
        );
        $stream .= $this->text(
            'Generated '.$generated.' | '.$report->generatedByName,
            520,
            550,
            8,
            'F1',
            '0.82 0.90 0.98',
        );
        $stream .= $this->rectangle(36, 510, 770, 16, '1 0.949 0.80');
        $stream .= $this->text(
            'Verified facts remain authoritative; guarded AI narrative is separate decision support only.',
            44,
            515,
            8,
            'F2',
            '0.42 0.30 0.02',
        );

        return $stream;
    }

    private function pageFooter(int $page, int $pageCount): string
    {
        $stream = $this->line(36, 42, 806, 42, '0.78 0.81 0.84');
        $stream .= $this->text(
            'SmartFactory DSS | Simulated Sage ERP prototype',
            36,
            27,
            8,
            'F1',
            '0.34 0.39 0.45',
        );
        $stream .= $this->text(
            'Page '.$page.' / '.$pageCount,
            744,
            27,
            8,
            'F2',
            '0.34 0.39 0.45',
        );

        return $stream;
    }

    private function sectionBand(
        string $contentType,
        string $section,
        int $top,
    ): string {
        $label = match ($contentType) {
            AiInferenceReportTableBuilder::VERIFIED_FACT => 'VERIFIED FACT',
            AiInferenceReportTableBuilder::GUARDED_AI_METADATA =>
                'GUARDED AI METADATA',
            AiInferenceReportTableBuilder::GUARDED_AI_NARRATIVE =>
                'GUARDED AI NARRATIVE',
            default => 'REPORT CONTENT',
        };
        $background = $contentType
            === AiInferenceReportTableBuilder::VERIFIED_FACT
                ? '0.88 0.93 0.97'
                : '0.96 0.91 0.78';
        $textColor = $contentType
            === AiInferenceReportTableBuilder::VERIFIED_FACT
                ? '0.071 0.208 0.357'
                : '0.39 0.25 0.02';

        $stream = $this->rectangle(
            self::LEFT,
            $top - 19,
            self::RIGHT - self::LEFT,
            20,
            $background,
        );
        $stream .= $this->text(
            $label.' - '.strtoupper($section),
            self::LEFT + 9,
            $top - 13,
            9,
            'F2',
            $textColor,
        );

        return $stream;
    }

    /**
     * @param list<string> $labelLines
     * @param list<string> $valueLines
     */
    private function metricRow(
        array $labelLines,
        array $valueLines,
        int $top,
        int $height,
        bool $alternate,
    ): string {
        $background = $alternate
            ? '0.965 0.973 0.980'
            : '1 1 1';
        $stream = $this->rectangle(
            self::LEFT,
            $top - $height,
            self::RIGHT - self::LEFT,
            $height,
            $background,
        );
        $stream .= $this->line(
            self::LEFT,
            $top - $height,
            self::RIGHT,
            $top - $height,
            '0.88 0.89 0.91',
        );
        $stream .= $this->line(
            275,
            $top - $height,
            275,
            $top,
            '0.88 0.89 0.91',
        );

        foreach ($labelLines as $index => $line) {
            $stream .= $this->text(
                $line,
                self::LEFT + 9,
                $top - 15 - ($index * 10),
                8,
                'F2',
                '0.25 0.29 0.34',
            );
        }

        foreach ($valueLines as $index => $line) {
            $stream .= $this->text(
                $line,
                286,
                $top - 15 - ($index * 10),
                8,
                'F1',
                '0.08 0.10 0.12',
            );
        }

        return $stream;
    }

    /**
     * @param list<string> $objects
     */
    private function serialize(array $objects): string
    {
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$object."\nendobj\n";
        }

        $xref = strlen($pdf);
        $count = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 ".$count."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($id = 1; $id < $count; $id++) {
            $pdf .= isset($offsets[$id])
                ? sprintf("%010d 00000 n \n", $offsets[$id])
                : "0000000000 00000 f \n";
        }

        $pdf .= "trailer\n<< /Size ".$count." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function rectangle(
        int $x,
        int $y,
        int $width,
        int $height,
        string $color,
    ): string {
        return $color.' rg '.$x.' '.$y.' '.$width.' '.$height." re f\n";
    }

    private function line(
        int $x1,
        int $y1,
        int $x2,
        int $y2,
        string $color,
    ): string {
        return $color.' RG 0.5 w '.$x1.' '.$y1.' m '.$x2.' '.$y2." l S\n";
    }

    private function text(
        string $text,
        int $x,
        int $y,
        int $size,
        string $font,
        string $color,
    ): string {
        return "BT\n"
            .$color." rg\n"
            .'/'.$font.' '.$size." Tf\n"
            .'1 0 0 1 '.$x.' '.$y." Tm\n"
            .'('.$this->escape($text).") Tj\nET\n";
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, int $width): array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);
        $wrapped = wordwrap($normalized, $width, "\n", true);

        return explode("\n", $wrapped);
    }

    private function format(string|int|float $value): string
    {
        if (is_float($value)) {
            return number_format($value, 6, '.', '');
        }

        return (string) $value;
    }

    private function escape(string $text): string
    {
        $ascii = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        return str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\\(', '\\)', ' ', ' '],
            $ascii === false ? $text : $ascii,
        );
    }
}
