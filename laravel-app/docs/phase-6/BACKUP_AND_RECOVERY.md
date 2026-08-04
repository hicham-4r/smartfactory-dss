# Backup and Recovery

## Backup scope

Back up these components separately:

1. Laravel application source.
2. FastAPI application source.
3. Private `.env` files through a secure secrets backup process.
4. MySQL database.
5. Redis only when persistent queue/session recovery is required.
6. Dataset roots on disk D.
7. Feature and preprocessing roots.
8. Model registry, including `MODELS_LATEST`.
9. Generated project documentation.
10. Web-server and process-manager configuration.

Do not place environment secrets in normal source archives.

## Recommended consistency

A recoverable AI state requires the complete chain:

```text
raw snapshot
-> preprocessed run
-> feature run
-> model run
```

Keep manifests, checksum files, and content fingerprints with every run.

## Restoration order

1. Restore application code.
2. Restore environment and secrets.
3. Restore the database.
4. Restore datasets and model registry to their configured absolute roots.
5. Confirm filesystem permissions.
6. clear Laravel caches;
7. validate the model registry;
8. run both test suites;
9. start FastAPI;
10. execute browser acceptance tests.

## Model-registry recovery

Never point `MODELS_LATEST` at an incomplete or unvalidated directory. Restore the entire
run first, validate it, then restore the pointer.

## Database safety

Do not use `migrate:fresh` as a recovery method. Use tested backups and normal migrations.

## Recovery acceptance

A recovery is complete only when:

- authentication works;
- role dashboards load;
- AI health is available;
- all three AI operations work;
- PDF, XLSX, and CSV export;
- audit events are recorded;
- exact model and feature run IDs match the restored evidence.
