# Phase 7 Final Acceptance Checklist

## Architecture

- [x] Browser traffic terminates at Laravel.
- [x] Laravel remains the authentication, authorization, database, session, audit, and reporting boundary.
- [x] FastAPI requires an internal bearer token and has no database or Sage ERP access.
- [x] Ollama is private/local and not exposed directly to browsers.
- [x] Ollama explains verified outputs but does not calculate or alter inference values.

## Contracts and grounding

- [x] Strict versioned contracts exist for forecast, anomaly, and maintenance explanations.
- [x] Role/type authorization is enforced in Laravel and FastAPI.
- [x] The Operator is excluded.
- [x] Prompt facts are selected by an explanation-type allowlist.
- [x] Numeric hallucinations, unsupported percentages, root-cause claims, certainty claims, external-access claims, and control commands are rejected.
- [x] Mandatory model and prototype limitations cannot be hidden.
- [x] Rejected raw model output is not exposed.

## Reliability

- [x] Request, prompt, response, and generated-text sizes are bounded.
- [x] Connection and generation timeouts exist.
- [x] One bounded correction attempt exists.
- [x] Rate and concurrency limits protect the local model.
- [x] Ollama failures do not invalidate the verified numeric inference result.
- [x] Existing dashboards, inference, and reporting remain independent of Ollama.

## Laravel integration

- [x] Generate-explanation action is user initiated and POST-only.
- [x] Encrypted snapshots are short-lived, user-bound, and session-bound.
- [x] Successful and failed attempts are audited safely.
- [x] No-store headers are applied.
- [x] English and French contracts are supported.

## Reporting

- [x] Explanations attach only to the exact matching inference report.
- [x] PDF, XLSX, and CSV separate verified facts, guarded metadata, and guarded narrative.
- [x] Export does not execute a second inference or Ollama request.
- [x] `simulated_prototype` remains visible.

## Tests and documentation

- [x] Focused hallucination, grounding, authorization, failure-isolation, client, UI, and exporter tests exist.
- [x] Complete Laravel and FastAPI regression suites are run by the final verifier.
- [x] Architecture, security, user, troubleshooting, acceptance, and runtime-evidence documentation exists.

## Deferred industrial acceptance

- [ ] Real Sage ERP integration validated.
- [ ] Real factory data approved, governed, and monitored.
- [ ] Expert anomaly labels available.
- [ ] Forecast and maintenance thresholds approved by stakeholders.
- [ ] Maintenance model meets agreed operational performance requirements.
- [ ] Prompt and model behavior evaluated on approved industrial cases.
- [ ] Shadow pilot and operational sign-off completed.
- [ ] Container deployment, centralized secrets, distributed rate limiting, monitoring, and backup validation completed.

## Decision

**Accepted:** Phase 7 as a secure, guarded, role-aware explanation layer for the SmartFactory DSS simulated PFE prototype.

**Not accepted:** industrial forecasting, autonomous anomaly decisions, automatic equipment control, or reliable predictive maintenance.
