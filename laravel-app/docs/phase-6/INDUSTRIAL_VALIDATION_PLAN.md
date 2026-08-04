# Industrial Validation Plan

## Current boundary

The existing implementation proves architecture and workflow using simulated Sage data.
It does not prove predictive performance in a real factory.

## Required data program

Before industrial use:

- obtain authorized real production and maintenance history;
- define ownership and retention;
- document Sage field mappings;
- measure missingness and reliability;
- remove personal or unnecessary fields;
- record product, line, shift, and machine changes;
- create expert-reviewed failure and anomaly labels;
- preserve chronological event time.

## Forecast validation

- compare against current planning methods;
- evaluate by line, product family, unit, season, and shift;
- use rolling-origin backtesting;
- define acceptable MAE, MAPE, and service-level impact;
- measure performance during demand and product-mix changes;
- validate prediction intervals or uncertainty estimates.

## Anomaly validation

- create confirmed anomaly and normal examples;
- review alerts with production and quality experts;
- report precision, recall, false-alert rate, and missed-anomaly rate;
- calibrate thresholds by operating context;
- define investigation and closure workflow.

## Maintenance validation

- verify failure definitions and timestamps;
- include realistic condition-monitoring and usage signals;
- evaluate class balance;
- measure alert lead time;
- evaluate false-positive and false-negative operational cost;
- calibrate probabilities;
- validate downtime predictions;
- compare against preventive-maintenance rules.

## Operational rollout

1. Offline retrospective evaluation.
2. Shadow mode with no operational effect.
3. Expert review of every result.
4. Limited pilot on selected lines.
5. Acceptance thresholds approved by stakeholders.
6. Monitored deployment with rollback.
7. Scheduled drift and performance review.

## Governance requirements

- named model owner;
- model version and data lineage;
- documented approval;
- incident process;
- periodic bias and performance review;
- change management;
- human override;
- audit retention;
- rollback to a known accepted run.

Industrial acceptance must be a separate signed decision.
