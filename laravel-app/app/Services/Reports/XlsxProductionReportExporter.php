<?php

namespace App\Services\Reports;

use App\DTOs\Reports\ProductionReport;

final class XlsxProductionReportExporter
{
    public function __construct(
        private readonly ProductionReportTableBuilder $tables,
        private readonly SpreadsheetCellSanitizer $sanitizer,
    ) {
    }

    public function export(
        ProductionReport $report
    ): string {
        $rows = $this->tables->rows(
            $report
        );

        $archive = new StoredZipWriter();

        $archive->add(
            '[Content_Types].xml',
            $this->contentTypesXml()
        );

        $archive->add(
            '_rels/.rels',
            $this->rootRelationshipsXml()
        );

        $archive->add(
            'docProps/core.xml',
            $this->corePropertiesXml($report)
        );

        $archive->add(
            'docProps/app.xml',
            $this->applicationPropertiesXml()
        );

        $archive->add(
            'xl/workbook.xml',
            $this->workbookXml()
        );

        $archive->add(
            'xl/_rels/workbook.xml.rels',
            $this->workbookRelationshipsXml()
        );

        $archive->add(
            'xl/styles.xml',
            $this->stylesXml()
        );

        $archive->add(
            'xl/worksheets/sheet1.xml',
            $this->worksheetXml($rows)
        );

        return $archive->finish();
    }

    /**
     * @param list<list<string|int|float>> $rows
     */
    private function worksheetXml(
        array $rows
    ): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<cols>'
            .'<col min="1" max="1" width="28" customWidth="1"/>'
            .'<col min="2" max="2" width="34" customWidth="1"/>'
            .'<col min="3" max="20" width="16" customWidth="1"/>'
            .'</cols>'
            .'<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $style = $this->rowStyle($row);

            $xml .= '<row r="'.$excelRow.'">';

            foreach ($row as $columnIndex => $value) {
                $reference = $this->columnName(
                    $columnIndex + 1
                ).$excelRow;

                $safeValue = $this->sanitizer
                    ->sanitize($value);

                $cellStyle = $style;

                if (
                    is_int($safeValue)
                    || is_float($safeValue)
                ) {
                    $xml .= '<c r="'.$reference.'" s="'
                        .$cellStyle
                        .'" t="n"><v>'
                        .$this->xml(
                            (string) $safeValue
                        )
                        .'</v></c>';

                    continue;
                }

                $xml .= '<c r="'.$reference.'" s="'
                    .$cellStyle
                    .'" t="inlineStr"><is><t xml:space="preserve">'
                    .$this->xml($safeValue)
                    .'</t></is></c>';
            }

            $xml .= '</row>';
        }

        return $xml
            .'</sheetData>'
            .'<pageMargins left="0.5" right="0.5" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            .'</worksheet>';
    }

    /**
     * @param list<string|int|float> $row
     */
    private function rowStyle(
        array $row
    ): int {
        $first = isset($row[0])
            ? (string) $row[0]
            : '';

        if (
            in_array(
                $first,
                [
                    'SmartFactory DSS production report',
                    'Applied filters',
                    'Production KPI summary',
                    'Primary breakdown',
                    'Data basis',
                ],
                true
            )
        ) {
            return 2;
        }

        if (
            in_array(
                $first,
                [
                    'Quantity unit',
                    'Production date',
                    'Week',
                    'Month',
                    'Production line',
                    'Product',
                    'Shift',
                ],
                true
            )
            && count($row) > 2
        ) {
            return 1;
        }

        return 0;
    }

    private function columnName(
        int $column
    ): string {
        $name = '';

        while ($column > 0) {
            $remainder = ($column - 1) % 26;

            $name = chr(
                65 + $remainder
            ).$name;

            $column = intdiv(
                $column - 1,
                26
            );
        }

        return $name;
    }

    private function contentTypesXml(): string
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

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Production report" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3">'
            .'<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><color rgb="FFFFFFFF"/><sz val="12"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF164E63"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right><top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function corePropertiesXml(
        ProductionReport $report
    ): string {
        $created = $report->generatedAt
            ->utc()
            ->format('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties '
            .'xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            .'xmlns:dc="http://purl.org/dc/elements/1.1/" '
            .'xmlns:dcterms="http://purl.org/dc/terms/" '
            .'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.$this->xml($report->title).'</dc:title>'
            .'<dc:creator>'.$this->xml($report->generatedByName).'</dc:creator>'
            .'<cp:lastModifiedBy>'.$this->xml($report->generatedByName).'</cp:lastModifiedBy>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    private function applicationPropertiesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            .'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>SmartFactory DSS</Application>'
            .'</Properties>';
    }

    private function xml(
        string $value
    ): string {
        return htmlspecialchars(
            $value,
            ENT_XML1 | ENT_QUOTES,
            'UTF-8'
        );
    }
}
