# Phase 5 — Step 20J: Operator account and assignment administration

## Purpose

This step closes the administrative gap between the synchronized ERP Operator
master data and the authenticated Operator dashboard.

An ERP Operator record and a DSS login account are separate objects:

- `operators` contains the synchronized employee identity;
- `users` contains the authenticated DSS account;
- `operators.user_id` links the two;
- `operator_assignments` allocates the Operator to a production line and shift.

## Added interface

The Administrator can open:

```text
/admin/operator-administration
```

From this interface, the Administrator can:

- search Operators;
- view linked DSS accounts;
- link or unlink an Operator account;
- create a manual line-and-shift assignment;
- edit active manual assignments;
- end and deactivate active manual assignments;
- review manual and ERP assignment history.

## Assignment rules

A new manual assignment requires:

- an active Operator;
- an active production line;
- an active shift;
- a starting date;
- an optional ending date;
- a primary/secondary designation.

The service rejects:

- duplicate overlapping assignments for the same Operator, line, and shift;
- overlapping primary assignments;
- invalid periods;
- inactive lines or shifts;
- modifications to ERP-synchronized assignments.

Manual assignments are stored with:

```text
source_system = manual_dss
```

## Account-link rules

Only an active DSS account with the `operator` role can be linked.

The service rejects:

- an account linked to another Operator;
- a non-Operator account;
- an inactive account;
- linking an inactive ERP Operator;
- replacing an existing link without unlinking first.

## Audit actions

The following actions are recorded:

- `administration.operator.account-linked`;
- `administration.operator.account-unlinked`;
- `administration.operator-assignment.created`;
- `administration.operator-assignment.updated`;
- `administration.operator-assignment.ended`.

## Giving production work

An assignment makes the Operator eligible for work; it does not create the work.

The Production Supervisor must create an order using the same line and shift,
release or start it, create a production batch, and move that batch to
`in_progress`. The Operator dashboard then matches that active work against the
Operator's current assignment.
