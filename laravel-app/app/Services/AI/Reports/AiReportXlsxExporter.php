<?php

namespace App\Services\AI\Reports;

use App\DTOs\AI\Reports\AiInferenceReport;
use App\Services\Reports\SpreadsheetCellSanitizer;
use App\Services\Reports\StoredZipWriter;

final readonly class AiReportXlsxExporter
{
    public function __construct(
        private AiInferenceReportTableBuilder $tables,
        private SpreadsheetCellSanitizer $sanitizer,
    ) {
    }

    public function export(AiInferenceReport $report): string
    {
        $rows = [
            ['Content type', 'Section', 'Metric', 'Value'],
        ];

        foreach ($this->tables->rows($report) as $row) {
            $rows[] = [
                $this->sanitizer->sanitize($row['content_type']),
                $this->sanitizer->sanitize($row['section']),
                $this->sanitizer->sanitize($row['metric']),
                $this->sanitizer->sanitize($row['value']),
            ];
        }

        $archive = new StoredZipWriter();
        $archive->add('[Content_Types].xml', $this->contentTypes());
        $archive->add('_rels/.rels', $this->rootRelationships());
        $archive->add('docProps/app.xml', $this->applicationProperties());
        $archive->add('docProps/core.xml', $this->coreProperties($report));
        $archive->add('xl/workbook.xml', $this->workbook());
        $archive->add('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $archive->add('xl/styles.xml', $this->styles());
        $archive->add('xl/worksheets/sheet1.xml', $this->sheet($rows));

        return $archive->finish();
    }

    /**
     * @param list<list<string|int|float>> $rows
     */
    private function sheet(array $rows): string
    {
        $xmlRows = '';

        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $cells = '';

            foreach ($row as $columnIndex => $value) {
                $reference = chr(65 + $columnIndex).$number;
                $style = $rowIndex === 0 ? '1' : '0';

                if (is_int($value) || is_float($value)) {
                    $cells .= '<c r="'.$reference.'" s="'.$style.'" t="n"><v>'
                        .$this->xml((string) $value)
                        .'</v></c>';
                } else {
                    $cells .= '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t>'
                        .$this->xml((string) $value)
                        .'</t></is></c>';
                }
            }

            $xmlRows .= '<row r="'.$number.'">'.$cells.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<cols><col min="1" max="1" width="24" customWidth="1"/>'
            .'<col min="2" max="2" width="28" customWidth="1"/>'
            .'<col min="3" max="3" width="52" customWidth="1"/>'
            .'<col min="4" max="4" width="60" customWidth="1"/></cols>'
            .'<sheetData>'.$xmlRows.'</sheetData>'
            .'<autoFilter ref="A1:D'.count($rows).'"/>'
            .'</worksheet>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="AI report" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF12355B"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            .'</styleSheet>';
    }

    private function coreProperties(AiInferenceReport $report): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            .'xmlns:dc="http://purl.org/dc/elements/1.1/" '
            .'xmlns:dcterms="http://purl.org/dc/terms/" '
            .'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.$this->xml($report->title()).'</dc:title>'
            .'<dc:creator>'.$this->xml($report->generatedByName).'</dc:creator>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'
            .$this->xml($report->generatedAt->utc()->toIso8601String())
            .'</dcterms:created></cp:coreProperties>';
    }

    private function applicationProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
            .'<Application>SmartFactory DSS</Application></Properties>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
