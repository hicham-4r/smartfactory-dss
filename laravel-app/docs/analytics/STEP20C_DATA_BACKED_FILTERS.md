# Step 20C — Data-backed maintenance filters

## Problem

The maintenance line and machine dropdowns were catalog-backed. They listed active
master-data rows even when those lines or machines had no downtime, machine-status,
or maintenance records in the selected period.

## Corrected rule

A production line is listed only when at least one active machine on that line has
one of the following in the selected period:

- a supported downtime event;
- a machine-status interval or transition relevant to the period;
- a maintenance-history record.

A machine is listed under the same rule.

The solution is data-driven. It does not hard-code `SIM_` names or the
`simulated_sage` source. This means future manually entered or real-Sage data will
also appear when it genuinely supports a maintenance KPI.

The machine dropdown receives all period-backed machines. Browser-side filtering
hides machines that do not belong to the currently selected line and clears an
incompatible selection.

No database migration is required.
