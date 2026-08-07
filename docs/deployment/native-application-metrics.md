# Native application metrics

## Scope

Phase 12 exposes Prometheus text-format metrics from the Laravel DSS, FastAPI AI
service, and simulated Sage ERP without exposing a new public endpoint.

## Security boundary

- Browser-facing NGINX ports return `404` for `/api/metrics`.
- Dedicated NGINX metrics ports `8084` and `8085` are ClusterIP-only and accept
  traffic only from the Prometheus Pod through NetworkPolicy.
- FastAPI `/metrics` is internal-only and accepts traffic from Laravel and
  Prometheus through the existing ClusterIP Service and NetworkPolicy.
- No metric label contains user IDs, emails, product identifiers, request IDs,
  query strings, raw URLs, prompts, ERP row values, or Secret values.
- Route labels use framework route templates or names and are capped to a
  bounded series count.

## Metrics

Each service exports:

- `smartfactory_application_info`;
- `smartfactory_http_requests_total`;
- `smartfactory_http_request_duration_seconds` histogram;
- `smartfactory_metrics_state_started_timestamp_seconds`.

FastAPI also exports in-flight request count and whether guarded Ollama
explanations are enabled. PHP services aggregate counters in the existing
private Redis service, with a fail-open Pod-local file fallback under `/tmp`.
The fallback never blocks application traffic.

## Runtime behavior

Counters may reset when a FastAPI Pod restarts. PHP counters are aggregated by
service in Redis and survive application Pod rollouts while the Redis PVC is
preserved. Metrics are operational telemetry only and are separate from DSS and
simulated ERP business records.
