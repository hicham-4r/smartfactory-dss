# GitHub Actions CI — SmartFactory DSS

This workflow completes the missing GitHub Actions validation layer for the
personal `hicham-4r/smartfactory-dss` repository.

It is validation-only CI. It never deploys the application, never pushes
container images, never mutates Kubernetes, never contacts a real Sage ERP,
and never requires the local Windows Ollama service.

## Jobs

- repository secret/private-key safety scan;
- Laravel DSS regression tests and frontend production build;
- simulated Sage ERP regression tests;
- FastAPI Ruff linting and pytest coverage;
- offline Kubernetes/Kustomize source validation;
- Grafana dashboard JSON validation;
- Prometheus configuration and alert-rule validation;
- production Dockerfile builds for Laravel, FastAPI, simulated ERP and NGINX.

Laravel and the ERP simulator use SQLite in-memory test configuration.
FastAPI uses Python 3.12 and keeps its configured coverage threshold.
Local live-Ollama tests are excluded from hosted CI.

## Acceptance

Do not merge `phase-12-github-actions` into `main` until all GitHub Actions
jobs are green.
