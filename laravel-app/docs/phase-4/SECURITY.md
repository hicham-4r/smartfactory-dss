# Phase 4 Security Controls

## Access control

ERP monitoring requires:

```text
administration.synchronization-logs.view
administration.system-health.view
```

Manual synchronization additionally requires:

```text
administration.synchronization.run
```

Manual execution also requires:

- authenticated account;
- active account;
- changed mandatory password;
- administrator 2FA;
- password confirmation;
- per-user cooldown.

## Secret handling

Never display or commit:

- ERP API tokens;
- Authorization headers;
- Redis credentials;
- complete ERP payloads;
- encrypted cursors;
- safe_context;
- two-factor secrets;
- recovery codes.

## Integrity

The synchronization layer preserves:

- source system;
- external ID;
- source version;
- source update timestamp;
- checksum;
- last synchronization time;
- import status;
- run and failure history.

## Concurrency

Scheduled and manual synchronization share one Redis lock. This prevents overlapping cycles and duplicate concurrent imports.

## Monitoring safety

Monitoring pages are read-only and use no-store response headers. Failure displays are sanitized and do not load `safe_context`.
