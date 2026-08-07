# Windows browser HTTPS for the Ubuntu Docker deployment

The SmartFactory DSS browser endpoint is:

```text
https://localhost:8443
```

## Boundary

```text
Windows browser
    |
    | HTTPS on host loopback 127.0.0.1:8443
    v
VirtualBox NAT port-forwarding
    |
    | guest TCP 8443
    v
NGINX TLS endpoint
    |
    | private FastCGI
    v
Laravel
```

Laravel-to-ERP, Laravel-to-FastAPI, database, Redis, and Ollama boundaries are
unchanged. Only the browser-facing DSS endpoint receives TLS in this step.

## Certificate model

Ubuntu generates a private local CA and a localhost server certificate.

- The CA private key stays under the ignored `deploy/compose/tls` directory.
- The server private key stays under the same ignored directory.
- Only the public CA certificate is copied to `C:\VM_Share`.
- Windows imports the public CA certificate into the current user's trusted
  root store.
- The server certificate is valid for `localhost` and `127.0.0.1`.

## VirtualBox boundary

The VM must remain attached to NAT.

The Windows host forwards only:

```text
127.0.0.1:8443 -> Ubuntu guest port 8443
```

Binding the host side to `127.0.0.1` prevents access from other machines on the
LAN. Do not change the VM to bridged networking while the guest HTTPS
publication uses `0.0.0.0`.

## Security properties

- Browser credentials and session cookies are encrypted in transit.
- Laravel receives `HTTPS=on` directly through FastCGI.
- The container session cookie is marked `Secure`.
- TLS 1.0 and TLS 1.1 are disabled.
- Only TLS 1.2 and TLS 1.3 are enabled.
- The ERP simulator, FastAPI, MySQL, Redis, PHP-FPM, and Ollama are not exposed
  to the Windows browser.
- No certificate private key is committed or copied to Windows.

This is a local prototype certificate. A real production deployment must use a
certificate issued for its production hostname and must not use this local CA.
