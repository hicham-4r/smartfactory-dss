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
