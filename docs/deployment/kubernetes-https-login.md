# Kubernetes HTTPS login and proxy handling

## Problem

TLS terminates at the Kubernetes Ingress. The internal NGINX hop communicates
with PHP-FPM over HTTP. Before this repair, NGINX replaced the Ingress
`X-Forwarded-Proto: https` value with its own `$scheme` value (`http`), and
Laravel did not trust the controlled proxy path. Route helpers therefore
generated plain-HTTP login forms and redirects for the HTTPS browser endpoint.

## Repair

- NGINX preserves the incoming `X-Forwarded-Proto` value and falls back to its
  local scheme only when the header is absent.
- Laravel 12 configures trusted proxies in `bootstrap/app.php`.
- `ASSET_URL=https://localhost:8443` remains configured for deterministic Vite
  asset URLs in the local browser demonstration.
- Regression coverage verifies login form and root redirect URL generation.

## Security boundary

The wildcard trusted-proxy setting is acceptable only for this deployment
because NetworkPolicies restrict the application path: browser traffic reaches
the Ingress controller, then the internal NGINX service, then Laravel PHP-FPM.
Laravel is not exposed directly. A production platform should replace the
wildcard with the managed ingress/load-balancer CIDR ranges when those ranges
are stable and known.

## Acceptance checks

The runtime acceptance must verify:

1. `GET https://localhost:8443/login` returns 200.
2. Login form actions and application redirects use HTTPS.
3. CSS and JavaScript assets return 200 with expected content types.
4. Secure session and XSRF cookie attributes remain present.
5. A credential-free POST without a CSRF token reaches Laravel and returns 419,
   rather than the NGINX plain-HTTP-to-HTTPS-port 400 response.
6. Nine application Pods, four monitoring Pods, and both HPAs remain healthy.
