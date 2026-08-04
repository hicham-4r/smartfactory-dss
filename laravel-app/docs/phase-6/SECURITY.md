# Phase 6 Security and Governance

## Authentication and authorization

Browser users authenticate through Laravel. AI access is role-aware and enforced in the
controller, not only hidden in the interface.

FastAPI internal endpoints require a bearer token. The token belongs only in private
environment files and must never appear in source control, screenshots, reports, or logs.

## Administrator controls

Administrator requests remain subject to mandatory password-change handling and configured
two-factor authentication.

## Network boundary

FastAPI binds to `127.0.0.1` in native local development. In production it must remain on
a private interface or private service network.

## Data minimization

The Laravel snapshot exporter excludes user identities, operator contact information,
credentials, tokens, free-text notes, and raw ERP payloads. FastAPI receives only the
fields required by the analytics contract.

## Integrity controls

- dataset manifests and files use SHA-256;
- preprocessing and feature runs are versioned and atomically published;
- model manifests, metrics, and artifacts are checksummed;
- safe-path validation prevents directory escape;
- content fingerprints link each model run to its source feature run;
- Joblib is loaded only after registry validation.

Joblib is executable serialization and must never be accepted from users or external
systems.

## Inference protections

- strict schemas reject unknown fields;
- request and response size limits;
- connection and request timeouts;
- safe error messages;
- request IDs;
- no feature or prediction payload logging;
- no direct database connection from FastAPI.

## Report protections

- report snapshots exist only after successful inference;
- token is a UUID;
- snapshot is bound to the current user;
- retention is short;
- only a limited number of reports remain in the session;
- spreadsheet formula prefixes are neutralized;
- downloads use no-store behavior;
- model metrics are read-only.

## Audit and accountability

Laravel records authorized security and reporting events without storing secrets. Reports
include generating user, generation time, request ID, model run, source feature run, and
data classification.

## Governance statement

All results remain `simulated_prototype`. Removing or hiding that label would create a
misleading claim and is prohibited.
