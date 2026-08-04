# Phase 7 Security and Governance

## Trust boundaries

- Browser users authenticate only through Laravel.
- Laravel is the authorization, database, audit, session, and reporting boundary.
- FastAPI accepts internal requests only with the shared bearer token.
- Ollama remains local/private and is never exposed in browser markup or routes.
- FastAPI does not query Laravel, MySQL, Sage ERP, operational tables, or user sessions.

## Input controls

The explanation contract rejects unknown fields, invalid roles, invalid explanation-type combinations, naive timestamps, NaN, infinity, unsafe codes, and any classification other than `simulated_prototype`.

Only allowlisted facts are copied into the prompt. Tokens, URLs, database configuration, unrestricted maps, raw ERP payloads, user identity, and free-form operational notes are excluded. Instruction-like text in supplied limitations is rejected before generation.

## Prompt and output controls

The system prompt requires fact-only output, preserves model limitations, forbids recalculation, and forbids root-cause, certainty, external-access, and control claims.

The response must be one bounded UTF-8 JSON object. Validation rejects:

- Markdown fences or trailing commentary;
- duplicate keys or unknown sections;
- unsupported fact references;
- missing mandatory result references or limitations;
- invented numbers, percentages, dates, lines, products, or machines;
- claimed access to Sage, ERP, databases, files, tools, PLCs, or SCADA;
- stop, restart, override, or automatic-control instructions.

Only one bounded correction attempt is allowed. Raw rejected output is not copied into the retry prompt, logs, Laravel response, or browser response.

## Availability controls

- finite connection and generation timeouts;
- request, prompt, upstream response, and generated-text size limits;
- deterministic generation settings;
- process-local request and concurrency limits for native development;
- safe `429`, `502`, and `503` errors with request IDs;
- explanation failure does not invalidate the numeric inference result.

A distributed limiter should replace the process-local limiter during container deployment.

## Laravel snapshot and report controls

- explanation and report tokens are UUIDs;
- snapshots are encrypted by Laravel;
- snapshots are bound to the authenticated user and stable session secret;
- retention and per-session counts are bounded;
- report attachment requires exact operation and inference-request linkage;
- no-store headers protect AI pages and downloads;
- spreadsheet formula prefixes are neutralized;
- audit records contain safe metadata, not prompts or raw model output.

## Secret handling

`AI_INTERNAL_TOKEN`, `AI_EXPLANATION_TOKEN`, and related secrets belong only in local or production environment configuration. They must not be committed, placed in screenshots, embedded in reports, or written to documentation evidence.

## Governance decision

All current outputs remain `simulated_prototype`. Removing that label, presenting explanations as facts, or using them for autonomous production or maintenance action is prohibited.
