# Step 20M PDF style enhancement

## Purpose

This focused update improves only the visual presentation of production PDF
reports. It does not change production KPI calculations, report filters,
authorization, audit behavior, CSV output, Excel output, or download security.

## Design

The native dependency-free PDF generator now produces landscape A4 reports
with:

- a repeated SmartFactory DSS header;
- a report-type badge;
- report metadata cards;
- applied-filter panels;
- one KPI card per quantity unit;
- provisional and validated state badges;
- achievement progress bars;
- styled multi-page breakdown tables;
- alternating table rows;
- highlighted rejected quantities;
- repeated table headers after page breaks;
- a data-basis interpretation panel;
- page numbering and a simulated-data footer.

## Compatibility

The implementation remains native PHP and uses the built-in PDF Type 1 fonts:
Helvetica, Helvetica Bold and Helvetica Oblique. No external font, CDN,
Composer library, browser renderer, or network request is required.

## Data rules

Quantities with different units remain separate. The PDF continues to reuse the
validated production report DTO, KPI summary and breakdown rows. No new query is
executed by the PDF writer.
