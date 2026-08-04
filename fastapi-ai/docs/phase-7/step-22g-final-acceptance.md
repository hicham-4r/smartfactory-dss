# Phase 7 - Step 22G: Final Acceptance

## Scope

Step 22G closes Phase 7 with final hallucination, grounding, authorization, failure-isolation, regression, and documentation checks. It does not change the trained models, download another Ollama model, add a new database migration, or begin the container deployment phase.

## Accepted request path

```text
Authenticated browser user
    -> Laravel RBAC and encrypted short-lived snapshot
    -> authenticated FastAPI explanation contract
    -> guarded prompt built from allowlisted verified facts
    -> private local Ollama model llama3:8b
    -> strict JSON, grounding, numeric, limitation, and policy validation
    -> Laravel display and optional exact-result report attachment
```

The browser never calls Ollama directly. FastAPI has no Laravel, MySQL, Sage ERP, operational-table, or user-directed arbitrary filesystem access; verified model artifacts remain the only model files it loads.

## Final safety cases

The acceptance suite verifies rejection of:

- the Operator role;
- prompt-injection text inside supplied limitation fields;
- invented numeric values and recalculated percentages;
- claimed Sage, ERP, database, or tool access;
- root-cause, certainty, and guaranteed-result claims;
- stop, restart, override, or automatic-control instructions;
- unsupported fact paths and hidden mandatory limitations;
- malformed, oversized, duplicated-key, fenced, or non-UTF-8 output;
- repeated unsafe model output after the one bounded correction attempt.

Rejected raw model output is not returned to Laravel or the browser.

## Failure isolation

Ollama timeout, unavailability, model absence, protocol failure, rate limiting, and rejected output affect only explanation generation. The verified numeric inference result remains unchanged and visible. Report export never performs a second inference or a second Ollama request.

## Classification and governance

Every current result remains `simulated_prototype`. Phase 7 is accepted only as a secure PFE decision-support prototype. It is not accepted as industrial forecasting, autonomous anomaly control, or reliable predictive maintenance.
