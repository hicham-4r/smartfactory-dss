# Phase 7 — Step 22D: Authenticated FastAPI Explanation Endpoint

## Scope

This step exposes one authenticated internal endpoint:

`POST /internal/v1/explanations/generate`

The endpoint accepts only the strict Step 22B contract, builds the guarded Step 22C
prompt, calls the exact configured private Ollama model, validates the response, and
returns a versioned `simulated_prototype` explanation.

## Request path

```text
Laravel (future Step 22E)
    -> authenticated FastAPI endpoint
    -> guarded prompt and fact allowlist
    -> private local Ollama llama3:8b
    -> strict JSON / grounding / policy validation
    -> FastAPI response
```

The browser never calls Ollama and FastAPI still has no database access.

## Security and reliability controls

- Internal bearer-token authentication is mandatory.
- The request contract rejects unknown fields and unauthorized role/type combinations.
- Incoming contract bytes, prompt bytes, upstream response bytes, and generated text are bounded.
- The local model call uses a connection timeout and a separate finite generation timeout.
- The model tag remains exactly `llama3:8b`; no download or substitution occurs.
- Structured JSON schema is sent to Ollama with deterministic generation options.
- Output is rejected if it invents values, hides limitations, uses unsupported fact paths,
  claims a root cause, or recommends machine/line control.
- One bounded correction attempt is allowed. The rejected raw model output is never
  copied into the retry prompt or error response.
- Process-local rate and concurrency limits protect the native-development model.
- Standard request IDs and no-store security headers remain enabled.
- Prompts and operational facts are not written to logs.
- Ollama timeout, unavailable, malformed, oversized, and unsafe-output failures return
  safe errors. Existing verified ML inference remains independent and available.

## Safe failure contract

Laravel will later keep displaying the verified numeric inference result when explanation
generation fails. FastAPI returns a safe error and request ID, for example:

- `503 ollama_timeout`
- `503 ollama_unavailable`
- `503 ollama_model_missing`
- `502 explanation_output_rejected`
- `429 explanation_rate_limited`

No raw model output, prompt, token, URL, or internal exception detail is returned.

## Testing

The normal test suite uses mocked Ollama responses. A separate optional live generation
test verifies the local `/api/chat` path without making the regular regression suite
depend on a running model.

## Next step

Step 22E will connect Laravel AI Insights to this endpoint with role authorization,
auditing, exact-result snapshots, and a user-triggered **Generate explanation** action.
