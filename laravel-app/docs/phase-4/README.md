# SmartFactory DSS — Phase 4

## Status

Phase 4 is complete for local development and acceptance.

Persistent Windows background execution is intentionally deferred. The local project uses on-demand synchronization and on-demand queue processing so that no scheduler or queue worker consumes resources continuously.

## Implemented scope

- Secure HTTPS connector to the simulated Sage ERP.
- Token-based ERP authentication.
- Connector abstraction and disabled-connector mode.
- Dependency-safe synchronization groups.
- Catalog, factory master, production, maintenance, and quality synchronization.
- Nested payload mapping and external-ID relationship resolution.
- Incremental checkpoints and safe replay.
- Source checksums and idempotent persistence.
- Synchronization runs, per-resource counters, and sanitized failures.
- Redis overlap protection.
- Scheduled-cycle command.
- Queued manual synchronization.
- Administrator monitoring dashboard.
- CLI health monitoring for 16 external ERP resources.
- Authorization, administrator 2FA, password confirmation, and rate limiting.
- Automated tests for connector, synchronization, monitoring, and manual queue execution.

## External ERP resources

1. product_families
2. products
3. production_lines
4. machines
5. shifts
6. operators
7. operator_assignments
8. work_orders
9. batches
10. machine_runs
11. machine_status_events
12. downtime_events
13. maintenance_history
14. finished_lots
15. inspections
16. nonconformities

Local run_logs telemetry is excluded from ERP health calculations.

## Local operating mode

Run synchronization only when needed:

```powershell
php artisan erp:sync:cycle --force --per-page=100 --max-pages=100
```

Process queued dashboard jobs only when needed:

```powershell
php artisan queue:work database --queue=erp-sync,default --tries=20 --timeout=7200 --stop-when-empty
```

Both commands terminate when their work is complete.
