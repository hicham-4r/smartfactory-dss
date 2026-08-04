from __future__ import annotations

import re
from dataclasses import dataclass
from datetime import UTC, date, datetime
from decimal import Decimal, InvalidOperation
from typing import Literal

from app.datasets.schema import DATASET_COLUMNS
from app.preprocessing.schema import ColumnRule, dataset_rules

IssueSeverity = Literal["error", "warning"]
_NULL_TOKENS = {"", "null", "none", "n/a", "na"}
_INTEGER_PATTERN = re.compile(r"^[+-]?\d+$")
_DANGEROUS_PREFIXES = ("=", "+", "-", "@")


@dataclass(frozen=True, slots=True)
class NormalizationIssue:
    severity: IssueSeverity
    code: str
    field: str | None
    message: str

    def to_dict(self, *, row_number: int) -> dict[str, int | str | None]:
        return {
            "row_number": row_number,
            "severity": self.severity,
            "code": self.code,
            "field": self.field,
            "message": self.message,
        }


def normalize_row(
    dataset: str,
    row: dict[str, str | None],
) -> tuple[dict[str, str], list[NormalizationIssue]]:
    normalized: dict[str, str] = {}
    issues: list[NormalizationIssue] = []
    rules = dataset_rules(dataset)

    for column in DATASET_COLUMNS[dataset]:
        value, issue = _normalize_cell(row.get(column), rules[column])
        normalized[column] = value
        if issue is not None:
            issues.append(
                NormalizationIssue(
                    severity="error",
                    code=issue[0],
                    field=column,
                    message=issue[1],
                )
            )

    if not any(issue.severity == "error" for issue in issues):
        issues.extend(_logical_issues(dataset, normalized))

    return normalized, issues


def _normalize_cell(
    raw_value: str | None,
    rule: ColumnRule,
) -> tuple[str, tuple[str, str] | None]:
    raw = "" if raw_value is None else str(raw_value)
    collapsed = " ".join(raw.strip().split())

    if collapsed.lower() in _NULL_TOKENS:
        if rule.required:
            return "", ("missing_required_value", "A required value is missing.")
        return "", None

    if any(ord(character) < 32 or ord(character) == 127 for character in collapsed):
        return "", ("invalid_control_character", "The value contains a control character.")

    try:
        if rule.kind == "date":
            return date.fromisoformat(collapsed).isoformat(), None

        if rule.kind == "datetime":
            parsed = datetime.fromisoformat(collapsed.replace("Z", "+00:00"))
            if parsed.tzinfo is None or parsed.utcoffset() is None:
                return "", (
                    "timezone_required",
                    "A timestamp must include an explicit timezone offset.",
                )
            canonical = parsed.astimezone(UTC).isoformat().replace("+00:00", "Z")
            return canonical, None

        if rule.kind == "integer":
            if not _INTEGER_PATTERN.fullmatch(collapsed):
                return "", ("invalid_integer", "The value is not a valid integer.")
            parsed_integer = int(collapsed)
            if rule.non_negative and parsed_integer < 0:
                return "", ("negative_value", "The value must not be negative.")
            return str(parsed_integer), None

        if rule.kind == "decimal":
            parsed_decimal = Decimal(collapsed)
            if not parsed_decimal.is_finite():
                return "", ("non_finite_number", "The value must be finite.")
            if rule.non_negative and parsed_decimal < 0:
                return "", ("negative_value", "The value must not be negative.")
            return _canonical_decimal(parsed_decimal), None

        if rule.kind == "boolean":
            lowered = collapsed.lower()
            if lowered in {"1", "true", "yes"}:
                return "1", None
            if lowered in {"0", "false", "no"}:
                return "0", None
            return "", ("invalid_boolean", "The value is not a supported Boolean.")

        if rule.kind == "code":
            return _protect_csv_formula(collapsed.upper()), None

        if rule.kind in {"unit", "currency"}:
            return _protect_csv_formula(collapsed.upper()), None

        if rule.kind == "category":
            return _protect_csv_formula(collapsed.lower()), None

        return _protect_csv_formula(collapsed), None
    except (ValueError, InvalidOperation, OverflowError):
        if rule.kind == "date":
            return "", ("invalid_date", "The value is not a valid ISO date.")
        if rule.kind == "datetime":
            return "", ("invalid_datetime", "The value is not a valid ISO timestamp.")
        if rule.kind == "decimal":
            return "", ("invalid_decimal", "The value is not a valid decimal number.")
        return "", ("invalid_value", "The value does not satisfy its column rule.")


def _canonical_decimal(value: Decimal) -> str:
    text = format(value, "f")
    if "." in text:
        text = text.rstrip("0").rstrip(".")
    return text or "0"


def _protect_csv_formula(value: str) -> str:
    if value.startswith(_DANGEROUS_PREFIXES):
        return "'" + value
    return value


def _logical_issues(
    dataset: str,
    row: dict[str, str],
) -> list[NormalizationIssue]:
    issues: list[NormalizationIssue] = []

    def add(
        severity: IssueSeverity,
        code: str,
        field: str | None,
        message: str,
    ) -> None:
        issues.append(
            NormalizationIssue(
                severity=severity,
                code=code,
                field=field,
                message=message,
            )
        )

    def dt(column: str) -> datetime | None:
        value = row.get(column, "")
        if not value:
            return None
        return datetime.fromisoformat(value.replace("Z", "+00:00"))

    def day(column: str) -> date | None:
        value = row.get(column, "")
        if not value:
            return None
        return date.fromisoformat(value)

    def number(column: str) -> Decimal | None:
        value = row.get(column, "")
        if not value:
            return None
        return Decimal(value)

    def require_order(start_column: str, end_column: str) -> None:
        start = dt(start_column)
        end = dt(end_column)
        if start is not None and end is not None and end < start:
            add(
                "error",
                "invalid_time_order",
                end_column,
                f"{end_column} must not precede {start_column}.",
            )

    if dataset == "production_records":
        require_order("started_at_utc", "ended_at_utc")
        produced = number("produced_quantity")
        good = number("good_quantity")
        rejected = number("rejected_quantity")
        if produced is not None and good is not None and rejected is not None:
            if good + rejected > produced:
                add(
                    "error",
                    "quantity_balance_invalid",
                    "produced_quantity",
                    "Good plus rejected quantity exceeds produced quantity.",
                )

    elif dataset == "downtime_events":
        require_order("started_at_utc", "ended_at_utc")
        resolved = row.get("is_resolved") == "1"
        if resolved and not row.get("ended_at_utc"):
            add(
                "error",
                "resolved_event_missing_end",
                "ended_at_utc",
                "A resolved downtime event requires an end timestamp.",
            )

    elif dataset == "machine_status_events":
        require_order("occurred_at_utc", "ended_at_utc")

    elif dataset == "maintenance_history":
        require_order("started_at_utc", "completed_at_utc")
        if row.get("completed_at_utc") and not row.get("started_at_utc"):
            add(
                "error",
                "completion_without_start",
                "started_at_utc",
                "A completed maintenance record requires a start timestamp.",
            )
        if row.get("cost") and not row.get("currency"):
            add(
                "warning",
                "cost_without_currency",
                "currency",
                "A maintenance cost is present without a currency code.",
            )

    elif dataset == "quality_inspections":
        sample_size = number("sample_size")
        passed = number("passed_quantity")
        failed = number("failed_quantity")
        if sample_size is not None and passed is not None and failed is not None:
            if passed + failed > sample_size:
                add(
                    "error",
                    "sample_balance_invalid",
                    "sample_size",
                    "Passed plus failed quantity exceeds sample size.",
                )

    elif dataset == "finished_lots":
        produced = number("produced_quantity")
        released = number("released_quantity")
        rejected = number("rejected_quantity")
        if produced is not None and released is not None and rejected is not None:
            if released + rejected > produced:
                add(
                    "error",
                    "lot_quantity_balance_invalid",
                    "produced_quantity",
                    "Released plus rejected quantity exceeds produced quantity.",
                )
        produced_at = dt("produced_at_utc")
        expiry = day("expiry_date")
        if produced_at is not None and expiry is not None and expiry < produced_at.date():
            add(
                "error",
                "expiry_before_production",
                "expiry_date",
                "The expiry date precedes the production date.",
            )
        require_order("produced_at_utc", "released_at_utc")

    elif dataset == "nonconformities":
        require_order("detected_at_utc", "corrected_at_utc")

    return issues
