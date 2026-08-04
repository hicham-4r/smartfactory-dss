# Step 21N automatic inference feature preparation

## Purpose

Step 21N replaces the normal large feature-entry forms with three simplified
workflows:

- next-day production forecasting;
- production anomaly scoring;
- maintenance-risk prioritization.

The detailed Step 21M forms remain available under **Advanced manual feature
testing** for troubleshooting and contract verification.

## Trust boundary

Laravel is the only component that reads the DSS database. It:

1. authorizes the signed-in user;
2. selects eligible simulated-Sage records;
3. calculates deterministic model features;
4. sends one strict feature row to FastAPI.

FastAPI still has no Laravel, MySQL, or simulated Sage ERP database access.

Every result remains `simulated_prototype` decision support.

## Automatic production forecast

The user selects:

- production line;
- quantity unit;
- prediction date.

The selected date must immediately follow a validated production day. Laravel
groups validated production records by line, unit, and calendar day and
calculates the same Step 21E inputs:

- history-day count;
- observed dates in the preceding seven-day window;
- one-day and exact seven-day good-quantity lags;
- seven-day good-quantity mean, minimum, and maximum;
- previous-day produced, target, runtime, and downtime;
- rejection and achievement ratios;
- forecast-origin weekday and month.

The prediction target is not read or sent.

## Automatic anomaly detection

The user selects one recent validated production record. Laravel joins the
record to its batch, order, product, product family, production line, and shift.
It calculates:

- achievement ratio;
- rejection ratio;
- good-yield ratio;
- throughput per hour;
- downtime ratio.

Only that prepared row is sent to the anomaly endpoint.

## Automatic maintenance risk

The user selects:

- active machine;
- prediction date.

Laravel uses only events strictly before the prediction date. It calculates:

- seven-day machine-status and fault counts/minutes;
- seven-day total and recognized unplanned downtime;
- thirty-day preventive/corrective maintenance history;
- days observed;
- days since the latest recognized failure;
- days since the latest maintenance event.

The failure-recognition terms and state categories mirror the documented Step
21E prototype rules. At least 30 observed days are required by default.

## Data eligibility

The default source system is `simulated_sage`. Rows with import status
`not_applicable`, `imported`, or `skipped` are eligible. Forecast and anomaly
workflows require `validation_status = validated`.

## Security and failure behavior

- Existing production and maintenance role checks remain enforced.
- Automatic routes use authentication and password-change middleware.
- Unknown or unavailable selections are rejected safely.
- Missing history produces a user-facing validation error.
- No feature row, prediction, token, or model artifact is written to logs.
- Model-run selection remains optional and defaults to `MODELS_LATEST`.
