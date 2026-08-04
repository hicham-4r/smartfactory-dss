from app.preprocessing.schema import dataset_rules


def test_nullable_source_fields_match_the_export_contract() -> None:
    assert dataset_rules("production_records")["source_version"].required is False
    assert dataset_rules("downtime_events")["source_version"].required is False

    quality_rules = dataset_rules("quality_inspections")
    for column in (
        "sample_size",
        "passed_quantity",
        "failed_quantity",
        "source_version",
    ):
        assert quality_rules[column].required is False
