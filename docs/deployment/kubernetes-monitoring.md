# SmartFactory DSS Kubernetes Monitoring

## Scope

This Phase 12 monitoring layer is an internal, Kubernetes-only observability
foundation. It does not expose Prometheus or Grafana through Ingress, NodePort,
or LoadBalancer resources.

The first source package adds and validates the manifests only. It does not
apply them to the accepted Phase 11 cluster.

## Components

- Prometheus 3.5.2 with seven-day and one-gigabyte retention limits
- Grafana 13.1.1
- kube-state-metrics 2.19.0, restricted to the `smartfactory-dss` namespace
- Blackbox Exporter 0.28.0
- Kubernetes cAdvisor scraping through the API server proxy
- pre-provisioned Grafana datasource and SmartFactory overview dashboard
- initial Prometheus alert rules
- persistent Prometheus and Grafana storage

## Security contract

- all images use pinned tags;
- all Services are `ClusterIP`;
- no monitoring Ingress is created;
- no Secret value is committed;
- the Grafana admin password is supplied through the runtime-only
  `grafana-runtime` Secret;
- service account token automounting remains disabled;
- Prometheus and kube-state-metrics receive explicit projected short-lived
  service account tokens only;
- monitoring containers drop Linux capabilities and use read-only root
  filesystems;
- existing default-deny NetworkPolicy remains active;
- egress is limited to internal application endpoints and private Kubernetes
  API/kubelet address ranges;
- no external Prometheus remote-write endpoint is configured.

## Validation

Run:

```bash
./deploy/kubernetes/scripts/validate-monitoring-manifests.sh
```

The validator renders the accepted demo overlay plus monitoring, performs a
Kubernetes client-side dry run, checks pinned images and internal-only access,
validates the dashboard JSON, and confirms the alert and scrape contracts.

## Runtime secret

Before the monitoring overlay is applied, create the Grafana runtime Secret
locally:

```bash
./deploy/kubernetes/scripts/create-grafana-runtime-secret.sh
```

The script prompts silently for the password and never prints it.

## Local access after runtime deployment

Start private local port-forwards:

```bash
./deploy/kubernetes/scripts/start-monitoring-proxies.sh
```

Then use:

- Prometheus: `http://localhost:9090`
- Grafana: `http://localhost:3000`

## Planned next monitoring work

The monitoring foundation now includes source-controlled native Prometheus-format
application metrics for Laravel, FastAPI, and the Sage ERP Simulator. The next
controlled steps deploy and accept these targets, expand dashboards and alerts,
and then add CI/CD workflows.
