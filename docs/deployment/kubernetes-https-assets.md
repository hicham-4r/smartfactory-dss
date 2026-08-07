# Kubernetes HTTPS asset origin

## Problem

The local browser bridge exposes the application at
`https://localhost:8443`, while TLS terminates at the Kubernetes Ingress and the
backend request reaches Laravel over the internal HTTP network. Before this
correction, Laravel rendered Vite asset URLs using
`http://localhost:8443/build/assets/...`. Port 8443 accepts HTTPS, so these asset
requests returned `400 Bad Request` and the browser displayed unstyled HTML.

## Implemented correction

The `laravel-config` ConfigMap defines:

```text
APP_URL=https://localhost:8443
ASSET_URL=https://localhost:8443
```

`APP_URL` identifies the application URL. `ASSET_URL` explicitly fixes the
origin used for generated CSS and JavaScript asset URLs in this local Kubernetes
demonstration topology.

## Runtime application

After changing the ConfigMap, restart only the Laravel Deployment so its Pods
receive the new environment variable. Do not restart databases, Redis, the ERP
simulator, AI service, or monitoring workloads for this change.

## Validation

The login page must:

- return HTTP 200 through `https://localhost:8443/login`;
- contain no `http://localhost:8443/build/` references;
- reference build assets through HTTPS or a same-origin relative URL;
- return HTTP 200 for every referenced CSS and JavaScript asset.
