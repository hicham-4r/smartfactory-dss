from __future__ import annotations

import json
from copy import deepcopy

from app.llm.output import parse_guarded_output
from app.schemas.explanation import ExplanationContractRequest
from tests.test_explanation_api import MODEL_LIMITATIONS, VALID_OUTPUT, VALID_REQUEST


def test_server_restores_required_limitations_and_references() -> None:
    request = ExplanationContractRequest.model_validate(VALID_REQUEST)
    output = deepcopy(VALID_OUTPUT)
    output["limitations"] = ["Model-edited text that must not be trusted."]
    output["referenced_fact_keys"] = []

    narrative = parse_guarded_output(
        request,
        json.dumps(output),
        enforce_server_metadata=True,
    )

    assert narrative.limitations == [
        (
            "This explanation uses only verified simulated-prototype facts and is not "
            "an industrial commitment."
        ),
        *MODEL_LIMITATIONS,
    ]
    assert "facts.result.predicted_good_quantity_next_day" in (narrative.referenced_fact_keys)
    assert "facts.model.data_classification" in narrative.referenced_fact_keys
    assert "facts.model.limitations" in narrative.referenced_fact_keys


def test_server_metadata_does_not_hide_unsafe_narrative() -> None:
    request = ExplanationContractRequest.model_validate(VALID_REQUEST)
    output = deepcopy(VALID_OUTPUT)
    output["summary"] = "The root cause is a filling machine problem."
    output["limitations"] = []
    output["referenced_fact_keys"] = []

    try:
        parse_guarded_output(
            request,
            json.dumps(output),
            enforce_server_metadata=True,
        )
    except Exception as exception:
        assert getattr(exception, "code", None) == "forbidden_claim"
    else:
        raise AssertionError("Unsafe narrative must remain rejected")
