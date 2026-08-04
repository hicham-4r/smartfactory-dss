# Step 21L inference API v1

All endpoints are internal, require the existing bearer token, and return
`data_classification: simulated_prototype`.

- `GET /internal/v1/inference/models`
- `POST /internal/v1/inference/production/forecast`
- `POST /internal/v1/inference/production/anomaly`
- `POST /internal/v1/inference/maintenance/risk`

The service loads only the model run referenced by `AI_MODEL_ROOT/MODELS_LATEST`
unless a valid explicit model-run UUID is requested. Before any artifact is loaded,
the existing `ModelRunValidator` verifies the manifest, checksums, paths, metrics,
artifacts, and content fingerprint.

Inference requests reject unknown fields. Request bodies, feature values, model
artifacts, and predictions are not written to application logs. Responses include
the model run, source feature run, selected model, limitations, and request ID.

These outputs are prototype decision-support results based only on simulated data.
They are not industrial commitments or reliable predictive-maintenance guarantees.
