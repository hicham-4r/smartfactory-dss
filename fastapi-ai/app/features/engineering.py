from __future__ import annotations

import csv
from collections import defaultdict
from collections.abc import Iterable
from datetime import date, datetime, timedelta
from decimal import Decimal
from pathlib import Path

from app.features.schema import FeatureTaskName

_FAILURE_TOKENS = ("fault", "failure", "breakdown", "panne", "unplanned")
_FAULT_STATUSES = {"fault", "failed", "failure", "breakdown", "down"}
_RUNNING_STATUSES = {"running", "active", "operational", "producing"}
_PREVENTIVE_TYPES = {"preventive", "préventive", "preventif", "préventif"}
_CORRECTIVE_TYPES = {"corrective", "correctif", "curative", "repair"}


def load_preprocessed_datasets(
    run_root: Path,
    datasets: Iterable[dict[str, object]],
) -> dict[str, list[dict[str, str]]]:
    loaded: dict[str, list[dict[str, str]]] = {}
    for dataset in datasets:
        name = str(dataset["name"])
        path = run_root / str(dataset["file"])
        with path.open("r", encoding="utf-8", newline="") as handle:
            loaded[name] = list(csv.DictReader(handle, strict=True))
    return loaded


def build_feature_rows(
    task: FeatureTaskName,
    datasets: dict[str, list[dict[str, str]]],
    *,
    period_start: date,
    period_end: date,
) -> list[dict[str, str]]:
    if task == "production_forecasting":
        return production_forecasting_rows(datasets.get("production_records", []))
    if task == "production_anomaly":
        return production_anomaly_rows(datasets.get("production_records", []))
    if task == "maintenance_risk":
        return maintenance_risk_rows(
            downtime_rows=datasets.get("downtime_events", []),
            status_rows=datasets.get("machine_status_events", []),
            maintenance_rows=datasets.get("maintenance_history", []),
            period_start=period_start,
            period_end=period_end,
        )
    raise KeyError(f"Unknown feature task: {task}")


def production_forecasting_rows(rows: list[dict[str, str]]) -> list[dict[str, str]]:
    aggregates: dict[tuple[str, str, date], dict[str, Decimal | int]] = {}

    for row in rows:
        day = date.fromisoformat(row["production_date"])
        key = (row["production_line_code"], row["quantity_unit"], day)
        aggregate = aggregates.setdefault(
            key,
            {
                "good": Decimal("0"),
                "produced": Decimal("0"),
                "target": Decimal("0"),
                "rejected": Decimal("0"),
                "runtime": 0,
                "downtime": 0,
            },
        )
        aggregate["good"] += _decimal(row["good_quantity"])
        aggregate["produced"] += _decimal(row["produced_quantity"])
        aggregate["target"] += _decimal(row["target_quantity"])
        aggregate["rejected"] += _decimal(row["rejected_quantity"])
        aggregate["runtime"] += _integer(row["runtime_minutes"])
        aggregate["downtime"] += _integer(row["downtime_minutes"])

    grouped: dict[tuple[str, str], dict[date, dict[str, Decimal | int]]] = defaultdict(dict)
    for (line, unit, day), aggregate in aggregates.items():
        grouped[(line, unit)][day] = aggregate

    output: list[dict[str, str]] = []
    for (line, unit), by_day in sorted(grouped.items()):
        dates = sorted(by_day)
        for index, feature_day in enumerate(dates):
            target_day = feature_day + timedelta(days=1)
            if target_day not in by_day:
                continue

            current = by_day[feature_day]
            window_start = feature_day - timedelta(days=6)
            window_days = [day for day in dates if window_start <= day <= feature_day]
            if len(window_days) < 2:
                continue

            good_window = [_as_decimal(by_day[day]["good"]) for day in window_days]
            lag_7_day = feature_day - timedelta(days=7)
            lag_7 = by_day.get(lag_7_day)
            produced = _as_decimal(current["produced"])
            target = _as_decimal(current["target"])
            rejected = _as_decimal(current["rejected"])
            runtime = int(current["runtime"])
            downtime = int(current["downtime"])

            output.append(
                {
                    "feature_date": feature_day.isoformat(),
                    "target_date": target_day.isoformat(),
                    "target_window_end_date": (target_day + timedelta(days=1)).isoformat(),
                    "production_line_code": line,
                    "quantity_unit": unit,
                    "days_of_history": str(index + 1),
                    "rolling_observation_count_7d": str(len(window_days)),
                    "day_of_week": str(feature_day.weekday()),
                    "month": str(feature_day.month),
                    "good_quantity_lag_1d": _decimal_text(_as_decimal(current["good"])),
                    "good_quantity_lag_7d": (
                        _decimal_text(_as_decimal(lag_7["good"])) if lag_7 else ""
                    ),
                    "good_quantity_mean_7d": _decimal_text(
                        sum(good_window, Decimal("0")) / Decimal(len(good_window))
                    ),
                    "good_quantity_min_7d": _decimal_text(min(good_window)),
                    "good_quantity_max_7d": _decimal_text(max(good_window)),
                    "produced_quantity_lag_1d": _decimal_text(produced),
                    "target_quantity_lag_1d": _decimal_text(target),
                    "runtime_minutes_lag_1d": str(runtime),
                    "downtime_minutes_lag_1d": str(downtime),
                    "rejection_rate_lag_1d": _ratio(rejected, produced),
                    "achievement_rate_lag_1d": _ratio(produced, target),
                    "target_good_quantity_next_day": _decimal_text(
                        _as_decimal(by_day[target_day]["good"])
                    ),
                }
            )

    return sorted(output, key=lambda row: tuple(row.values()))


def production_anomaly_rows(rows: list[dict[str, str]]) -> list[dict[str, str]]:
    output: list[dict[str, str]] = []

    for row in rows:
        target = _decimal(row["target_quantity"])
        produced = _decimal(row["produced_quantity"])
        good = _decimal(row["good_quantity"])
        rejected = _decimal(row["rejected_quantity"])
        runtime = _integer(row["runtime_minutes"])
        downtime = _integer(row["downtime_minutes"])

        output.append(
            {
                "event_time_utc": row["started_at_utc"],
                "production_date": row["production_date"],
                "production_line_code": row["production_line_code"],
                "product_family_code": row["product_family_code"],
                "product_code": row["product_code"],
                "shift_code": row["shift_code"],
                "quantity_unit": row["quantity_unit"],
                "production_order_priority": row["production_order_priority"],
                "target_quantity": _decimal_text(target),
                "produced_quantity": _decimal_text(produced),
                "good_quantity": _decimal_text(good),
                "rejected_quantity": _decimal_text(rejected),
                "runtime_minutes": str(runtime),
                "downtime_minutes": str(downtime),
                "achievement_ratio": _ratio(produced, target),
                "rejection_ratio": _ratio(rejected, produced),
                "good_yield_ratio": _ratio(good, produced),
                "throughput_per_hour": (
                    _decimal_text(produced * Decimal("60") / Decimal(runtime))
                    if runtime > 0
                    else ""
                ),
                "downtime_ratio": (
                    _ratio(Decimal(downtime), Decimal(runtime + downtime))
                    if runtime + downtime > 0
                    else ""
                ),
                "is_validated": row["is_validated"],
            }
        )

    return sorted(
        output,
        key=lambda row: (
            row["event_time_utc"],
            row["production_line_code"],
            row["product_code"],
            row["shift_code"],
        ),
    )


def maintenance_risk_rows(
    *,
    downtime_rows: list[dict[str, str]],
    status_rows: list[dict[str, str]],
    maintenance_rows: list[dict[str, str]],
    period_start: date,
    period_end: date,
) -> list[dict[str, str]]:
    machine_rows: dict[str, dict[str, list[dict[str, str]]]] = defaultdict(
        lambda: {"downtime": [], "status": [], "maintenance": []}
    )
    catalog: dict[str, tuple[str, str]] = {}

    for row in downtime_rows:
        machine = row["machine_code"]
        machine_rows[machine]["downtime"].append(row)
        catalog[machine] = (row["production_line_code"], row["machine_type"])

    for row in status_rows:
        machine = row["machine_code"]
        machine_rows[machine]["status"].append(row)
        catalog[machine] = (row["production_line_code"], row["machine_type"])

    for row in maintenance_rows:
        machine = row["machine_code"]
        machine_rows[machine]["maintenance"].append(row)
        catalog[machine] = (row["production_line_code"], row["machine_type"])

    output: list[dict[str, str]] = []
    for machine, streams in sorted(machine_rows.items()):
        observed_dates = _machine_observed_dates(streams)
        if not observed_dates:
            continue

        first_prediction = max(
            period_start + timedelta(days=30),
            min(observed_dates) + timedelta(days=1),
        )
        final_prediction = period_end - timedelta(days=7)
        if first_prediction > final_prediction:
            continue

        prediction_day = first_prediction
        while prediction_day <= final_prediction:
            history_7_start = prediction_day - timedelta(days=7)
            history_30_start = prediction_day - timedelta(days=30)
            target_end = prediction_day + timedelta(days=7)

            status_history = [
                row
                for row in streams["status"]
                if history_7_start <= _event_date(row, "occurred_at_utc") < prediction_day
            ]
            downtime_history = [
                row
                for row in streams["downtime"]
                if history_7_start <= _event_date(row, "started_at_utc") < prediction_day
            ]
            maintenance_history = [
                row
                for row in streams["maintenance"]
                if history_30_start <= _event_date(row, "scheduled_at_utc") < prediction_day
            ]
            failure_target = [
                row
                for row in streams["downtime"]
                if prediction_day <= _event_date(row, "started_at_utc") < target_end
                and _is_failure_downtime(row)
            ]
            fault_status_target = [
                row
                for row in streams["status"]
                if prediction_day <= _event_date(row, "occurred_at_utc") < target_end
                and _is_fault_status(row)
            ]

            previous_failures = [
                _event_date(row, "started_at_utc")
                for row in streams["downtime"]
                if _event_date(row, "started_at_utc") < prediction_day and _is_failure_downtime(row)
            ]
            previous_failures.extend(
                _event_date(row, "occurred_at_utc")
                for row in streams["status"]
                if _event_date(row, "occurred_at_utc") < prediction_day and _is_fault_status(row)
            )
            previous_maintenance = [
                _event_date(row, "scheduled_at_utc")
                for row in streams["maintenance"]
                if _event_date(row, "scheduled_at_utc") < prediction_day
            ]

            line, machine_type = catalog[machine]
            output.append(
                {
                    "prediction_date": prediction_day.isoformat(),
                    "target_window_end_date": target_end.isoformat(),
                    "production_line_code": line,
                    "machine_code": machine,
                    "machine_type": machine_type,
                    "is_critical": _latest_critical_value(streams["status"], prediction_day),
                    "days_observed": str((prediction_day - min(observed_dates)).days),
                    "status_event_count_7d": str(len(status_history)),
                    "fault_status_event_count_7d": str(
                        sum(1 for row in status_history if _is_fault_status(row))
                    ),
                    "running_minutes_7d": str(
                        sum(
                            _integer(row["duration_minutes"])
                            for row in status_history
                            if _is_running_status(row)
                        )
                    ),
                    "fault_minutes_7d": str(
                        sum(
                            _integer(row["duration_minutes"])
                            for row in status_history
                            if _is_fault_status(row)
                        )
                    ),
                    "downtime_event_count_7d": str(len(downtime_history)),
                    "unplanned_downtime_event_count_7d": str(
                        sum(1 for row in downtime_history if _is_failure_downtime(row))
                    ),
                    "total_downtime_minutes_7d": str(
                        sum(_integer(row["duration_minutes"]) for row in downtime_history)
                    ),
                    "unplanned_downtime_minutes_7d": str(
                        sum(
                            _integer(row["duration_minutes"])
                            for row in downtime_history
                            if _is_failure_downtime(row)
                        )
                    ),
                    "maintenance_event_count_30d": str(len(maintenance_history)),
                    "preventive_maintenance_count_30d": str(
                        sum(1 for row in maintenance_history if _is_preventive(row))
                    ),
                    "corrective_maintenance_count_30d": str(
                        sum(1 for row in maintenance_history if _is_corrective(row))
                    ),
                    "maintenance_downtime_minutes_30d": str(
                        sum(_integer(row["downtime_minutes"]) for row in maintenance_history)
                    ),
                    "days_since_last_failure": _days_since(previous_failures, prediction_day),
                    "days_since_last_maintenance": _days_since(
                        previous_maintenance,
                        prediction_day,
                    ),
                    "target_failure_next_7d": (
                        "1" if failure_target or fault_status_target else "0"
                    ),
                    "target_unplanned_downtime_minutes_next_7d": str(
                        sum(_integer(row["duration_minutes"]) for row in failure_target)
                    ),
                }
            )
            prediction_day += timedelta(days=1)

    return sorted(
        output,
        key=lambda row: (
            row["prediction_date"],
            row["production_line_code"],
            row["machine_code"],
        ),
    )


def _machine_observed_dates(streams: dict[str, list[dict[str, str]]]) -> list[date]:
    dates: list[date] = []
    dates.extend(_event_date(row, "started_at_utc") for row in streams["downtime"])
    dates.extend(_event_date(row, "occurred_at_utc") for row in streams["status"])
    dates.extend(_event_date(row, "scheduled_at_utc") for row in streams["maintenance"])
    return dates


def _latest_critical_value(status_rows: list[dict[str, str]], prediction_day: date) -> str:
    candidates = [
        row for row in status_rows if _event_date(row, "occurred_at_utc") < prediction_day
    ]
    if not candidates:
        return "0"
    latest = max(candidates, key=lambda row: row["occurred_at_utc"])
    return "1" if latest["is_critical"] == "1" else "0"


def _days_since(events: list[date], prediction_day: date) -> str:
    if not events:
        return ""
    return str((prediction_day - max(events)).days)


def _is_failure_downtime(row: dict[str, str]) -> bool:
    category = row.get("category", "").lower()
    downtime_type = row.get("downtime_type", "").lower()
    severity = row.get("severity", "").lower()
    return (
        category == "unplanned"
        or severity == "critical"
        or any(token in downtime_type for token in _FAILURE_TOKENS)
    )


def _is_fault_status(row: dict[str, str]) -> bool:
    status = row.get("status", "").lower()
    return status in _FAULT_STATUSES or any(token in status for token in _FAILURE_TOKENS)


def _is_running_status(row: dict[str, str]) -> bool:
    status = row.get("status", "").lower()
    return status in _RUNNING_STATUSES


def _is_preventive(row: dict[str, str]) -> bool:
    value = row.get("maintenance_type", "").lower()
    return value in _PREVENTIVE_TYPES or "prevent" in value


def _is_corrective(row: dict[str, str]) -> bool:
    value = row.get("maintenance_type", "").lower()
    return value in _CORRECTIVE_TYPES or "correct" in value


def _event_date(row: dict[str, str], column: str) -> date:
    value = row[column]
    return datetime.fromisoformat(value.replace("Z", "+00:00")).date()


def _decimal(value: str) -> Decimal:
    if not value:
        return Decimal("0")
    return Decimal(value)


def _integer(value: str) -> int:
    if not value:
        return 0
    return int(value)


def _as_decimal(value: Decimal | int) -> Decimal:
    return value if isinstance(value, Decimal) else Decimal(value)


def _ratio(numerator: Decimal, denominator: Decimal) -> str:
    if denominator == 0:
        return ""
    return _decimal_text(numerator / denominator)


def _decimal_text(value: Decimal) -> str:
    quantized = value.quantize(Decimal("0.000001"))
    text = format(quantized, "f").rstrip("0").rstrip(".")
    return text or "0"
