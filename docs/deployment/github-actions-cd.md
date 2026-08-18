# GitHub Actions Continuous Delivery — GHCR

## Scope

This workflow implements the delivery part of Phase 12 for the personal
`hicham-4r/smartfactory-dss` repository.

It deliberately does **not** deploy automatically to the local Minikube
demonstration cluster. The laptop/VirtualBox environment remains an explicit,
human-controlled deployment target.

The delivery flow is:

```text
green main
   |
version tag vMAJOR.MINOR.PATCH
   |
SmartFactory Release CD
   |
build four production Dockerfiles
   |
GitHub Container Registry (GHCR)
   |
versioned deployable images + delivery metadata
```

## Images

A successful tagged release publishes:

- `ghcr.io/hicham-4r/smartfactory-dss-laravel:<version>`
- `ghcr.io/hicham-4r/smartfactory-dss-fastapi:<version>`
- `ghcr.io/hicham-4r/smartfactory-dss-erp-simulator:<version>`
- `ghcr.io/hicham-4r/smartfactory-dss-nginx:<version>`

Each component also receives:

- `sha-<12-character-source-sha>`;
- `latest`.

No Kubernetes manifest is rewritten automatically. The accepted local
Minikube configuration is therefore not disturbed by CD.

## Authentication

Tagged publishing uses the repository-scoped `GITHUB_TOKEN`.

Workflow permissions are intentionally minimal:

- `contents: read`;
- `packages: write` only for the delivery matrix.

No personal access token is required.

## Dry-run acceptance

Before the first publish, run `SmartFactory Release CD` manually from `main`.

A manual run:

- validates the release workflow path;
- builds all four production images with Buildx;
- generates per-component delivery metadata;
- uploads the metadata as GitHub Actions artifacts;
- does **not** log in to GHCR;
- does **not** push images.

The dry-run must be fully green before creating the first release tag.

## Tagged publish

Only tags matching exactly:

```text
vMAJOR.MINOR.PATCH
```

are accepted.

The workflow also verifies that the tag resolves to the current `main` commit.
A tag pointing to another commit is rejected.

## Delivery metadata

Every component produces a JSON artifact containing:

- component name;
- GHCR image name;
- version, SHA and latest references;
- Buildx digest;
- source Git revision;
- release tag/version;
- architecture;
- whether the image was actually published.

## Package visibility

GitHub Container Registry packages may initially be private. Visibility is a
GitHub package setting and is intentionally not changed by the workflow.

## Industrial boundary

The release artifacts remain prototype delivery artifacts.

They do not represent:

- a real Sage ERP integration;
- autonomous industrial control;
- validated plant-performance guarantees;
- automatic production deployment.
