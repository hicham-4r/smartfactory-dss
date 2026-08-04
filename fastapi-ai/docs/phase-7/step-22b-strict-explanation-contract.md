# Phase 7 — Step 22B: Strict Explanation Contract

## Scope

This step defines the versioned, strict, role-aware contract that later Ollama prompts and endpoints must use. It does **not** generate explanations and does not add an HTTP explanation endpoint.

## Security boundary

- Only validated Pydantic models are accepted.
- Unknown fields are rejected.
- No database URL, token, prompt text, user identity, or unrestricted payload map is accepted.
- Only `simulated_prototype` is permitted as the data classification.
- Production explanations are limited to Production Supervisor, Production Manager, and Administrator roles.
- Maintenance explanations are limited to Maintenance Manager and Administrator roles.
- Timestamps must include timezone information.
- Numeric values reject NaN and infinity.
- Narrative sections and list lengths are bounded.
- Every narrative must declare the exact allowlisted fact paths it referenced.
- A grounding validator rejects any referenced path outside the explanation-type allowlist.

## Supported explanation types

- `production_forecast`
- `production_anomaly`
- `maintenance_risk`

## Narrative structure

- `summary`
- `observations`
- `suggested_human_checks`
- `limitations`
- `referenced_fact_keys`

## Important interpretation rules preserved

- Anomaly scores are finite model scores, not percentages or probabilities.
- Maintenance risk remains an AI-assisted prioritization prototype, not reliable predictive maintenance.
- Forecasts remain prototype decision-support values, not industrial commitments.
- No LLM output may alter verified numeric facts.

## Next step

Step 22C will create guarded prompts and grounding rules that consume this contract. Step 22D will later expose the authenticated internal explanation endpoint.
