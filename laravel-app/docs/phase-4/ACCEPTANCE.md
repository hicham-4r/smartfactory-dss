# Phase 4 Acceptance Checklist

## Connector

- [x] HTTPS simulator connection works.
- [x] Token authentication works.
- [x] Protected handshake succeeds.
- [x] Pagination and rate limiting are handled.
- [x] Connector failures are sanitized.

## Synchronization groups

- [x] Catalog completes.
- [x] Factory master completes.
- [x] Production execution completes.
- [x] Maintenance completes.
- [x] Quality completes.
- [x] Dependency order is enforced.
- [x] Second execution is idempotent.
- [x] Partial imports can be replayed safely.

## State and monitoring

- [x] All 16 external ERP checkpoints exist.
- [x] Local run_logs is excluded.
- [x] Run history is recorded.
- [x] Per-resource counters are recorded.
- [x] Sanitized failures are recorded.
- [x] Health command works.
- [x] Administrator monitoring dashboard works.
- [x] Stale checkpoints produce DEGRADED health.
- [x] Successful synchronization restores HEALTHY health.

## Manual synchronization

- [x] Guest access is rejected.
- [x] Unauthorized roles are rejected.
- [x] Password confirmation is required.
- [x] Input validation works.
- [x] Repeated requests are rate-limited.
- [x] Job is queued on erp-sync.
- [x] On-demand worker processes the job.
- [x] Initiating administrator is recorded.

## Deferred local item

- [x] Persistent Windows background tasks intentionally skipped.
- [x] Local mode uses on-demand commands.
- [x] Production implementation is documented.
