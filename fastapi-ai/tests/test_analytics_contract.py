from copy import deepcopy

from fastapi.testclient import TestClient

VALID_PAYLOAD = {
    "metadata": {
        "snapshot_id": "11111111-1111-4111-8111-111111111111",
        "contract_name": "smartfactory.analytics.snapshot",
        "contract_version": "v1",
        "source_application": "smartfactory-dss-laravel",
        "source_system": "simulated_sage",
        "data_classification": "simulated_prototype",
        "generated_at": "2026-08-02T21:45:00Z",
        "timezone": "Africa/Casablanca",
    },
    "payload": {
        "production_kpis": {
            "filter": {
                "start_date": "2026-08-01",
                "end_date": "2026-08-02",
                "timezone": "Africa/Casablanca",
                "production_line_id": None,
                "product_id": None,
                "product_family_id": None,
                "shift_id": None,
                "machine_id": None,
                "production_order_id": None,
                "status": None,
            },
            "generated_at": "2026-08-02T21:45:00Z",
            "data_basis": "Validated and provisional production "
            "records remain explicitly separated.",
            "unit_count": 1,
            "has_mixed_units": False,
            "is_provisional": False,
            "record_count": 1,
            "validated_record_count": 1,
            "provisional_record_count": 0,
            "target_order_count": 1,
            "runtime_minutes": 420,
            "downtime_minutes": 20,
            "units": [
                {
                    "quantity_unit": "L",
                    "target_order_count": 1,
                    "record_count": 1,
                    "validated_record_count": 1,
                    "provisional_record_count": 0,
                    "is_provisional": False,
                    "target_quantity": "1000.000",
                    "actual_quantity": "980.000",
                    "good_quantity": "970.000",
                    "rejected_quantity": "10.000",
                    "runtime_minutes": 420,
                    "downtime_minutes": 20,
                    "achievement_percentage": 98.0,
                    "rejection_percentage": 1.02,
                    "average_production_rate_per_hour": "140.000",
                    "observed_utilization_percentage": 95.45,
                }
            ],
        }
    },
}


def test_valid_production_contract_is_accepted_without_echoing_metrics(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    payload = deepcopy(VALID_PAYLOAD)
    response = client.post(
        "/internal/v1/contracts/analytics/validate",
        headers={
            **auth_headers,
            "Idempotency-Key": payload["metadata"]["snapshot_id"],
            "X-Analytics-Contract-Version": "v1",
        },
        json=payload,
    )

    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "accepted"
    assert body["contract_name"] == "smartfactory.analytics.snapshot"
    assert body["contract_version"] == "v1"
    assert body["snapshot_id"] == payload["metadata"]["snapshot_id"]
    assert body["accepted_sections"] == ["production_kpis"]
    assert body["request_id"] == response.headers["X-Request-ID"]
    assert "units" not in body
    assert "target_quantity" not in response.text


def test_contract_requires_internal_authentication(client: TestClient) -> None:
    payload = deepcopy(VALID_PAYLOAD)
    response = client.post(
        "/internal/v1/contracts/analytics/validate",
        headers={
            "Idempotency-Key": payload["metadata"]["snapshot_id"],
            "X-Analytics-Contract-Version": "v1",
        },
        json=payload,
    )

    assert response.status_code == 401
    assert response.json()["error"]["code"] == "unauthenticated"


def test_unknown_payload_fields_are_rejected(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    payload = deepcopy(VALID_PAYLOAD)
    payload["payload"]["production_kpis"]["secret_note"] = "must not be accepted"

    response = client.post(
        "/internal/v1/contracts/analytics/validate",
        headers={
            **auth_headers,
            "Idempotency-Key": payload["metadata"]["snapshot_id"],
            "X-Analytics-Contract-Version": "v1",
        },
        json=payload,
    )

    assert response.status_code == 422
    assert response.json()["error"]["code"] == "validation_error"
    assert "secret_note" in str(response.json()["error"]["details"])


def test_empty_analytics_payload_is_rejected(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    payload = deepcopy(VALID_PAYLOAD)
    payload["payload"] = {}

    response = client.post(
        "/internal/v1/contracts/analytics/validate",
        headers={
            **auth_headers,
            "Idempotency-Key": payload["metadata"]["snapshot_id"],
            "X-Analytics-Contract-Version": "v1",
        },
        json=payload,
    )

    assert response.status_code == 422
    assert response.json()["error"]["code"] == "validation_error"


def test_idempotency_key_must_match_snapshot(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    payload = deepcopy(VALID_PAYLOAD)

    response = client.post(
        "/internal/v1/contracts/analytics/validate",
        headers={
            **auth_headers,
            "Idempotency-Key": "22222222-2222-4222-8222-222222222222",
            "X-Analytics-Contract-Version": "v1",
        },
        json=payload,
    )

    assert response.status_code == 409
    assert response.json()["error"]["code"] == "idempotency_key_mismatch"


def test_contract_version_header_must_match_payload(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    payload = deepcopy(VALID_PAYLOAD)

    response = client.post(
        "/internal/v1/contracts/analytics/validate",
        headers={
            **auth_headers,
            "Idempotency-Key": payload["metadata"]["snapshot_id"],
            "X-Analytics-Contract-Version": "v2",
        },
        json=payload,
    )

    assert response.status_code == 409
    assert response.json()["error"]["code"] == "contract_version_mismatch"


def test_section_timezone_must_match_metadata(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    payload = deepcopy(VALID_PAYLOAD)
    payload["payload"]["production_kpis"]["filter"]["timezone"] = "UTC"

    response = client.post(
        "/internal/v1/contracts/analytics/validate",
        headers={
            **auth_headers,
            "Idempotency-Key": payload["metadata"]["snapshot_id"],
            "X-Analytics-Contract-Version": "v1",
        },
        json=payload,
    )

    assert response.status_code == 422
    assert response.json()["error"]["code"] == "validation_error"


def test_unit_count_must_match_unit_rows(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    payload = deepcopy(VALID_PAYLOAD)
    payload["payload"]["production_kpis"]["unit_count"] = 2

    response = client.post(
        "/internal/v1/contracts/analytics/validate",
        headers={
            **auth_headers,
            "Idempotency-Key": payload["metadata"]["snapshot_id"],
            "X-Analytics-Contract-Version": "v1",
        },
        json=payload,
    )

    assert response.status_code == 422
    assert response.json()["error"]["code"] == "validation_error"


def test_all_supported_aggregate_sections_match_the_v1_schema(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    payload = deepcopy(VALID_PAYLOAD)
    common_filter = {
        "start_date": "2026-08-01",
        "end_date": "2026-08-02",
        "timezone": "Africa/Casablanca",
    }

    payload["payload"]["production_breakdowns"] = {
        "filter": {
            **common_filter,
            "production_line_id": None,
            "product_id": None,
            "product_family_id": None,
            "shift_id": None,
            "machine_id": None,
            "production_order_id": None,
            "status": None,
        },
        "generated_at": "2026-08-02T21:45:00Z",
        "is_empty": True,
        "has_mixed_units": False,
        "line_ranking_basis": "Lines are ranked only inside the same quantity unit.",
        "shift_target_caution": "Shift targets use the verified Laravel aggregation basis.",
        "daily_trend": [],
        "weekly_trend": [],
        "monthly_trend": [],
        "by_production_line": [],
        "by_shift": [],
        "by_product": [],
        "by_product_family": [],
        "best_lines_by_unit": [],
        "lowest_lines_by_unit": [],
    }

    payload["payload"]["maintenance_kpis"] = {
        "filter": {
            **common_filter,
            "production_line_id": None,
            "machine_id": None,
            "maintenance_type": None,
            "downtime_category": None,
        },
        "generated_at": "2026-08-02T21:45:00Z",
        "data_basis": "Observed prototype maintenance metrics only.",
        "downtime_event_count": 0,
        "open_downtime_event_count": 0,
        "total_downtime_minutes": 0,
        "planned_downtime_minutes": 0,
        "unplanned_downtime_minutes": 0,
        "unclassified_downtime_minutes": 0,
        "observed_status_minutes": 0,
        "running_minutes": 0,
        "fault_event_count": 0,
        "maintenance_intervention_count": 0,
        "preventive_intervention_count": 0,
        "corrective_intervention_count": 0,
        "completed_corrective_count": 0,
        "corrective_repair_minutes": 0,
        "repeated_failure_machine_count": 0,
        "availability_percentage": None,
        "mttr_minutes": None,
        "mtbf_minutes": None,
        "failures_per_100_running_hours": None,
        "machines": [],
        "maintenance_types": [],
    }

    payload["payload"]["quality_kpis"] = {
        "filter": {
            **common_filter,
            "production_line_id": None,
            "product_id": None,
            "product_family_id": None,
            "inspection_result": None,
            "lot_status": None,
            "nonconformity_severity": None,
            "nonconformity_status": None,
            "lot_number": None,
        },
        "generated_at": "2026-08-02T21:45:00Z",
        "data_basis": "Verified quality aggregates with quantity units separated.",
        "is_empty": True,
        "inspection_count": 0,
        "passed_inspection_count": 0,
        "failed_inspection_count": 0,
        "conditional_inspection_count": 0,
        "pending_inspection_count": 0,
        "sample_size": 0,
        "passed_sample_quantity": 0,
        "failed_sample_quantity": 0,
        "lot_count": 0,
        "released_lot_count": 0,
        "blocked_lot_count": 0,
        "rejected_lot_count": 0,
        "pending_lot_count": 0,
        "nonconformity_count": 0,
        "open_nonconformity_count": 0,
        "resolved_nonconformity_count": 0,
        "minor_nonconformity_count": 0,
        "major_nonconformity_count": 0,
        "critical_nonconformity_count": 0,
        "inspection_pass_percentage": None,
        "sample_failure_percentage": None,
        "released_lot_percentage": None,
        "held_rejected_lot_percentage": None,
        "nonconformities_per_100_inspections": None,
        "quantity_units": [],
        "by_production_line": [],
        "by_product": [],
        "by_product_family": [],
        "nonconformity_categories": [],
    }

    response = client.post(
        "/internal/v1/contracts/analytics/validate",
        headers={
            **auth_headers,
            "Idempotency-Key": payload["metadata"]["snapshot_id"],
            "X-Analytics-Contract-Version": "v1",
        },
        json=payload,
    )

    assert response.status_code == 200
    assert response.json()["accepted_sections"] == [
        "production_kpis",
        "production_breakdowns",
        "maintenance_kpis",
        "quality_kpis",
    ]


def test_invalid_idempotency_key_format_is_rejected_safely(
    client: TestClient,
    auth_headers: dict[str, str],
) -> None:
    payload = deepcopy(VALID_PAYLOAD)

    response = client.post(
        "/internal/v1/contracts/analytics/validate",
        headers={
            **auth_headers,
            "Idempotency-Key": "x" * 36,
            "X-Analytics-Contract-Version": "v1",
        },
        json=payload,
    )

    assert response.status_code == 409
    assert response.json()["error"]["code"] == "invalid_idempotency_key"
