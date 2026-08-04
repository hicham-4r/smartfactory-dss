# Phase 5 — Step 20K: Administrator operations dashboard

## Purpose

This step replaces the static `/admin` landing page with a deterministic,
Administrator-only operations dashboard.

## Displayed areas

### User and access readiness

- total, active and inactive account counts;
- accounts that must change a temporary password;
- currently locked accounts;
- active Operator-role accounts not linked to an ERP Operator.

### Operator readiness

- active and inactive ERP Operators;
- active Operators without a linked DSS account;
- active Operators without a current line-and-shift assignment.

### Application health

- database read availability and measured latency;
- cache write/read/delete availability and measured latency;
- explicit `not implemented` status for the future FastAPI, ML and Ollama
  services.

### Queue health

When the selected Laravel queue connection is `database`, the dashboard shows:

- total backlog;
- ready jobs;
- reserved jobs;
- delayed jobs;
- failed-job count.

The dashboard never displays queue payloads or stored exception traces.

### ERP synchronization

The existing `ErpSyncHealthService` remains the source of truth for:

- synchronization health;
- recent run counts;
- failed runs;
- stale resource states;
- recent sanitized failures;
- deterministic health reasons.

### Audit activity

The latest audit entries display only:

- time;
- actor name and email;
- action identifier;
- subject class and identifier.

Old values, new values, metadata, IP addresses and user agents are deliberately
excluded.

## Security

The route preserves:

- authenticated access;
- mandatory-password-change enforcement;
- Administrator 2FA enforcement;
- `dashboard.administrator.view` authorization;
- private `no-store` response headers.

## Deferred capabilities

The dashboard explicitly marks FastAPI, predictive models and local LLM
explanations as not implemented. No fabricated AI status is produced.
