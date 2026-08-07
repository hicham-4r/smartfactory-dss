# Runtime Secret contract

Secret values are never stored in Git.

The workloads expect these runtime Secret objects in namespace
`smartfactory-dss`:

- `laravel-runtime`
- `fastapi-runtime`
- `erp-simulator-runtime`
- `mysql-dss-runtime`
- `mysql-erp-runtime`
- `smartfactory-local-tls`

Create them only through `../scripts/create-runtime-secrets.sh` or an
equivalent deployment-time secret manager.

The helper reads existing ignored Compose environment files. It sends values
directly to `kubectl create secret ... --dry-run=client | kubectl apply`; it
does not print values or write generated Secret YAML to the repository.

The TLS certificate and key paths must be supplied explicitly:

```bash
TLS_CERT_PATH=/absolute/path/server.crt \
TLS_KEY_PATH=/absolute/path/server.key \
./deploy/kubernetes/scripts/create-runtime-secrets.sh
```

Do not commit `.env`, certificate private keys, tokens, passwords, Laravel
`APP_KEY`, internal bearer tokens, or generated Secret manifests.
