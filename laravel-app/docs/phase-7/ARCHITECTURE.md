# Phase 7 Architecture - Guarded Local LLM Explanations

## Purpose

Phase 7 adds role-aware natural-language explanations around already verified machine-learning outputs. Ollama is an explanatory component only. It does not calculate KPIs, prepare features, select models, run the authoritative prediction, update production data, create maintenance work, or control equipment.

## Request flow

```text
Browser
  -> Laravel HTTPS application
     -> authentication, password policy, administrator 2FA, RBAC
     -> verified inference result and encrypted session-bound snapshot
     -> internal bearer-authenticated request
        -> FastAPI on the private local boundary
           -> strict Pydantic explanation contract
           -> allowlisted fact bundle and deterministic guarded prompt
           -> Ollama llama3:8b on the private local host
           -> strict output parsing and grounding validation
     <- bounded explanation response or safe error
  <- numeric result remains authoritative; narrative is displayed separately
```

There is no browser-to-Ollama path. FastAPI has no direct database or simulated Sage ERP access.

## Supported explanation types

| Explanation type | Authorized roles |
| --- | --- |
| Production forecast | Production Supervisor, Production Manager, Administrator |
| Production anomaly | Production Supervisor, Production Manager, Administrator |
| Maintenance risk | Maintenance Manager, Administrator |

The Operator is excluded from the AI Insights workspace and all explanation contracts.

## Exact-result binding

Laravel creates a short-lived encrypted explanation snapshot only after successful inference. The snapshot is bound to the authenticated user and a stable random session secret. A successful explanation can be attached only to the report snapshot carrying the same inference request ID and operation.

PDF, XLSX, and CSV exports use three explicit boundaries:

- `verified_fact` for authoritative numeric facts and model metadata;
- `guarded_ai_metadata` for explanation identity, role, language, and linkage;
- `guarded_ai_narrative` for summary, observations, human checks, and limitations.

Exporting never executes a second prediction or a second Ollama generation.

## Failure behavior

Explanation failures are isolated. The UI keeps the exact verified inference result visible and presents a safe explanation error with a request ID. Existing dashboards, inference, analytics, and reporting continue to operate when Ollama is disabled or unavailable.
