# Phase 7 Troubleshooting

## AI Insights opens but explanation generation is unavailable

Check that FastAPI is running on `127.0.0.1:8001`, Laravel and FastAPI use the same internal token, and the Laravel explanation driver is `fastapi`.

Do not expose FastAPI or Ollama publicly to solve a local connection problem.

## `ollama_model_missing`

Run `ollama list` and confirm the exact tag `llama3:8b`. Phase 7 does not silently substitute or download another model.

## `ollama_timeout` or `ollama_unavailable`

Confirm the Ollama service is running. The first generation after model unload can take longer. The verified numeric result remains valid and can still be exported without an explanation.

## `explanation_rate_limited`

Wait for the `Retry-After` interval. Native development intentionally permits only a small number of requests and one concurrent generation.

## `explanation_output_rejected`

The local model failed the strict JSON, grounding, limitation, numeric, or safety checks twice. The raw rejected text is intentionally unavailable. Retry once with the same verified facts or continue using the numeric result only.

## Snapshot invalid, expired, or belongs to another session

Rerun the inference. Explanation snapshots are encrypted, short-lived, user-bound, and session-bound. Copying a token to another account or browser session is expected to fail.

## Explanation does not appear in a report

Only a successful explanation linked to the exact report operation and inference request ID can be attached. Generate the explanation from the same displayed result, then use that result's report buttons.

## Report still works when Ollama is down

This is expected. Report export reuses the stored inference result and attached explanation, if one already exists. It never calls inference or Ollama during export.

## Routine verification

Run the Step 22G package verifier. It performs static boundary checks, focused safety tests, and both complete regression suites without requiring live Ollama generation.
