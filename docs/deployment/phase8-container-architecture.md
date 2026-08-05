# Phase 8 container architecture

## Scope of Step 23D

This step adds production-minded image foundations only. It does **not** add
Docker Compose, initialize databases, run migrations, expose FastAPI publicly,
or start queue and scheduler processes.

## Images

| Image | Base | Purpose |
|---|---|---|
| SmartFactory Laravel | PHP 8.3 FPM | Main authenticated DSS application |
| Sage ERP simulator | PHP 8.3 FPM | Separate simulated ERP application |
| FastAPI AI | Python 3.12 slim | Internal deterministic ML and guarded LLM service |
| NGINX edge | NGINX stable Alpine | Static assets and FastCGI routing |

The Laravel image uses a deterministic Node build stage based on the existing
`package-lock.json`. The ERP simulator remains API-focused and does not invent
a missing frontend lock file.

## Planned Compose services

The next step will define:

- `nginx`
- `laravel-app`
- `laravel-queue`
- `laravel-scheduler`
- `fastapi-ai`
- `sage-erp-simulator`
- `mysql-dss`
- `mysql-erp`
- `redis`

Queue and scheduler services will reuse the Laravel image. They will not be
required as persistent Windows-native background processes.

## Network boundaries

- Only NGINX will publish application ports.
- PHP-FPM and FastAPI stay on internal Docker networks.
- FastAPI never becomes a browser-facing API.
- Ollama stays on Windows.
- In the current VirtualBox NAT topology, Ubuntu can reach Windows Ollama at
  `10.0.2.2:11434`.
- The Compose Redis service will not publish host port `6379`, preserving the
  existing native Ubuntu Redis service used by Windows development.
- DSS and simulated ERP MySQL databases remain separate.

## Health model

- NGINX process: `/nginx-health`
- Laravel framework: `/up`
- ERP framework: `/up`
- ERP API health: `/api/health`
- FastAPI liveness: `/health/live`
- FastAPI readiness: authenticated `/health/ready`
- PHP-FPM process: internal FPM ping

Ollama is optional for deterministic inference readiness. Its failure must not
make verified ML inference unavailable.

## Startup safety

The images intentionally do not:

- copy `.env` files;
- generate secrets;
- run `migrate:fresh`;
- run migrations automatically;
- seed automatically;
- train models at startup;
- expose Docker's daemon API;
- publish FastAPI or Ollama;
- start queue workers or the scheduler inside the web container.

First-run migration and seeding commands will remain explicit deployment
operations after database health is confirmed.

## Runtime data

Future Compose volumes will cover:

- DSS MySQL data;
- ERP MySQL data;
- Redis data;
- Laravel writable storage;
- simulator writable storage;
- FastAPI datasets, preprocessing output, features, and model artifacts.

Generated ML data and model artifacts remain outside Git and outside image
layers.
