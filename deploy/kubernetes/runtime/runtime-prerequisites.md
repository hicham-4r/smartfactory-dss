# Kubernetes runtime prerequisites

- Ingress controller: ingress-nginx
- Metrics source: Metrics Server
- Pod Ollama URL: `http://host.minikube.internal:11435`
- Ubuntu bind: `192.168.49.1:11435`
- Windows upstream: `10.0.2.2:11434`

The Minikube node probe sends the complete curl expression as a single remote
command so its URL and redirection remain intact.

No credential, prompt, model list, or generated response is stored here.
