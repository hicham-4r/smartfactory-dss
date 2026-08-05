# Docker Compose deployment foundation

## Scope

This Compose foundation defines the service graph and safe local environment
templates. It does not start containers, migrate databases, seed data, or copy
model artifacts.

## Services

- `nginx`: the only service that publishes host ports.
- `laravel-app`: main SmartFactory DSS PHP-FPM application.
- `laravel-queue`: opt-in database queue worker under the `workers` profile.
- `laravel-scheduler`: opt-in scheduler under the `workers` profile.
- `fastapi-ai`: private deterministic AI and guarded explanation service.
- `sage-erp-simulator`: separate simulated ERP PHP-FPM application.
- `mysql-dss`: isolated DSS database.
- `mysql-erp`: isolated simulator database.
- `redis`: internal Laravel cache and coordination service.

Ollama remains on Windows. In the current VirtualBox NAT topology, FastAPI uses
`http://10.0.2.2:11434`. The example also installs the portable
`host.docker.internal:host-gateway` mapping, but the Ubuntu VM deployment does
not depend on that hostname.

## Network boundaries

- `edge`: NGINX, Laravel, and the simulator.
- `ai-link`: Laravel and FastAPI; this network permits FastAPI egress to the
  Windows-hosted Ollama endpoint.
- `dss-data`: internal-only network for Laravel, Redis, and DSS MySQL.
- `erp-data`: internal-only network for the simulator and ERP MySQL.

MySQL, Redis, FastAPI, PHP-FPM, the queue worker, and the scheduler publish no
host ports. NGINX binds the DSS and simulator ports to `127.0.0.1` by default.

## Persistent data

Named volumes preserve:

- DSS MySQL data;
- simulated ERP MySQL data;
- Redis append-only data;
- Laravel writable storage;
- simulator writable storage;
- FastAPI datasets, preprocessing output, features, and model registry data.

Do not use `docker compose down --volumes` unless permanent deletion of all
Compose-managed data is explicitly intended.

## Local environment generation

Tracked files contain placeholders only. Generate ignored local environment
files on the deployment host with:

```bash
python3 deploy/compose/scripts/generate-local-env.py
```

The generator creates the project-level `.env` plus one ignored `.env` file for
each service. It generates matching DSS database credentials, matching ERP
credentials and API token, a matching Laravel/FastAPI internal token, and
separate Laravel application keys. Secret values are never printed.

The generator refuses to overwrite existing files unless `--force` is provided.

## Startup policy

The first startup will be performed incrementally in a later step:

1. validate `docker compose config`;
2. start MySQL and Redis;
3. verify infrastructure health;
4. run non-destructive migrations;
5. seed only the approved simulated/bootstrap data;
6. start FastAPI, simulator, Laravel, and NGINX;
7. run integration acceptance;
8. enable the `workers` profile only after queue tables and application health
   are accepted.

Never use `migrate:fresh`.

## Local HTTP note

The initial Ubuntu VM deployment uses loopback HTTP, so the generated Laravel
and simulator environment templates set secure cookies to `false`. A TLS
deployment must set secure cookies back to `true` and use HTTPS application
URLs.

## Shared AI dataset volume

Docker Compose uses the dedicated named volume `ai-datasets` as the
file-based trust boundary between Laravel and FastAPI.

- Laravel mounts `/var/lib/smartfactory-ai/datasets` read-write and is the
  only service allowed to publish sanitized snapshots from the DSS database.
- FastAPI mounts the same path read-only and verifies published manifests,
  checksums, schemas, file sizes, and row counts.
- FastAPI does not receive DSS or simulated ERP database connectivity.
- The `ai-runtime-data` volume remains separate for preprocessing, feature,
  model, and other AI runtime artifacts.
- Queue and scheduler profiles declare the same Laravel dataset mount for
  configuration consistency, but remain opt-in and stopped during local
  development.
- Dataset files, manifests, model artifacts, and real environment files are
  runtime data and must never be committed to Git.
