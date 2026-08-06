# SmartFactory DSS Kubernetes foundation

This directory defines the source-controlled Kubernetes foundation for the
SmartFactory DSS local demonstration cluster.

## Demonstration topology

The baseline contains nine application Pods on one Minikube node:

| Workload | Controller | Baseline | Demo |
|---|---|---:|---:|
| Laravel | Deployment | 2 | 3 |
| FastAPI | Deployment | 2 | 3 |
| Sage ERP simulator | Deployment | 1 | 1 |
| MySQL DSS | StatefulSet | 1 | 1 |
| MySQL ERP | StatefulSet | 1 | 1 |
| Redis | StatefulSet | 1 | 1 |
| NGINX | Deployment | 1 | 1 |

Scaling Laravel and FastAPI from two replicas to three increases the total from
nine to eleven application Pods. This demonstrates horizontal Pod scaling on a
single node; it is not a multi-node high-availability demonstration.

## Source layout

- `base/`: namespace, ConfigMaps, Services, PVCs, controllers, policies,
  Ingress, and PodDisruptionBudgets.
- `overlays/demo/`: baseline plus HPAs with minimum two and maximum three
  replicas for Laravel and FastAPI.
- `scripts/create-runtime-secrets.sh`: creates runtime Secrets from existing
  ignored Compose environment files and explicit TLS paths.
- `scripts/validate-manifests.sh`: renders and client-side validates both
  Kustomize layers without creating cluster resources.
- `secrets/README.md`: secret-handling contract.

## Important state rule

No Secret object or secret value is committed. Workloads reference Secrets that
must be created at deployment time.

The existing Compose containers, Docker volumes, databases, datasets, model
artifacts, and source history remain preserved. This foundation does not import
Compose data into Kubernetes. Storage initialization and migration are separate
controlled steps.

## Private Ollama dependency

FastAPI is committed with `AI_OLLAMA_ENABLED=false`. Numeric inference can be
validated without guarded narrative generation. The setting must not be enabled
until the Minikube-to-Windows private Ollama route is verified.

## Validation

Run:

```bash
./deploy/kubernetes/scripts/validate-manifests.sh
```

This renders both Kustomize trees, performs Kubernetes client-side schema
validation, verifies the nine-Pod baseline, verifies HPA bounds, checks that no
Secret is committed, and confirms the current cluster remains untouched.

## Step 24D runtime prerequisites

Minikube v1.38.1 uses the compatible built-in `ingress` addon with
ingress-nginx. Metrics Server supplies HPA resource metrics.

The Ubuntu host bridge is resolved dynamically through
`host.minikube.internal`.

Private Windows Ollama access uses:

- Pod URL: `http://host.minikube.internal:11435`
- Ubuntu bind address: `192.168.49.1:11435`
- Windows upstream: `10.0.2.2:11434`

The private proxy uses systemd socket activation and binds only to the resolved
host bridge. The node probe passes its complete curl expression as one
Minikube SSH remote command. `AI_OLLAMA_ENABLED` remains `false`.
