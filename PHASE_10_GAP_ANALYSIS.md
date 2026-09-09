# PHASE 10 — GAP ANALYSIS

## Existing Functionality

### Settlement calculation

- Annual settlements are created from approved participant profit allocations.
- The Phase 9 User Journey change is implemented: approved participant fund allocations are included in settlement due.
- Principal remains excluded.
- Current settlement calculation is:

```text
Amount Due = Approved Profit + Approved Participant Fund Share
```

- Previous recorded payments are now represented by immutable `settlement_payments` rows and settlement paid totals.
- Existing settlement revisions preserve parent/version lineage before payment.

### Payment lifecycle

- `settlement_payments` table and model exist.
- Admin payment endpoint exists:

```text
POST /api/admin/settlements/{settlement}/payments
```

- Legacy full-payment endpoint remains:

```text
POST /api/admin/settlements/{settlement}/paid
```

- Payments are DECIMAL and immutable.
- Partial payment and final payment states are supported.
- Overpayment is rejected.
- Ordinary settlement payment does not automatically deduct a Fund balance.

### Participant API

Existing endpoints include:

- `GET /api/me`
- `GET /api/me/investment`
- `GET /api/me/capital`
- `GET /api/me/profits`
- `GET /api/me/funds`
- `GET /api/me/depreciation`
- `GET /api/me/settlements`
- `GET /api/me/notifications`

Participant queries use authenticated identity and safe projections. Existing IDOR coverage is present.

### Audit

Existing audit infrastructure records settlement creation, approval, payment, revision, cancellation, security events, and other financial lifecycle events. Audit records are immutable.

### Calculator

The Investment Calculator remains blocked because no authoritative formula exists. This is intentional and must remain so.

## Missing Functionality

### 1. Central settlement due service

The amount-due formula is currently assembled in settlement creation and payment state logic. Phase 10 should centralize the authoritative calculation in a domain/application service that explicitly accepts:

- approved profit
- approved participant fund share
- authoritative recorded previous payments

No controller, resource, Blade view, or frontend code should calculate it.

### 2. Explicit payment source

`settlement_payments` currently stores amount, date, method, reference, description, and admin actor, but it has no explicit actual payment-source field.

Required gap:

- distinguish `fund`, `bank`, `cash`, or other approved source values
- ensure only an explicitly Fund-sourced payment can invoke existing Fund ledger behavior
- ensure normal settlement payment never deducts a Fund implicitly
- add audit context for payment source

Business/data-model risk: the repository has Fund entities but no Bank/Cash source model. The smallest safe implementation may use a constrained source type/reference field, but the exact allowed source taxonomy and accounting effect need approval before mutation behavior is added.

### 3. Post-payment adjustment/reversal

No settlement adjustment or reversal model/table/action/API exists.

Required:

- immutable adjustment/reversal record
- original settlement reference
- original payment/reference relationship
- signed or otherwise explicitly represented correction amount
- reason
- actor and timestamp
- audit trail
- derived correction/remaining state without mutating original paid records

This is a major financial lifecycle change and must be implemented with a dedicated domain contract, not a direct update shortcut.

### 4. Participant settlement detail/payment route

Participant list responses include payment history, but the required dedicated endpoints are not all present:

- `GET /api/me/settlements/{settlement}`
- `GET /api/me/settlements/{settlement}/payments`

The existing `/api/participant/settlements/{settlement}` route exists, but the `/api/me` contract should be completed consistently.

### 5. Participant capital growth endpoint

No dedicated `/api/me/capital/growth` endpoint exists. Historical capital snapshots are available, so a read-only backend projection can likely be added without inventing a formula, provided movement semantics are explicitly limited to snapshot-to-snapshot differences.

### 6. Participant singular detail endpoints

The `/api/me` collection contract lacks dedicated `/api/me/.../{id}` routes for all requested resources. Any additions must preserve authenticated ownership scope and safe projections.

### 7. Profile/password endpoint contract

Existing authentication includes password-change behavior under the authentication routes, but there is no dedicated `/api/me/change-password` contract. The required final contract must be reconciled with the existing authentication architecture before adding a duplicate route.

### 8. Notification read model/preferences

Participant notifications are readable and scoped. Notification preference persistence and update behavior do not exist. The Phase 10 specification allows preferences only if persistence already exists; adding them requires a narrowly defined schema and policy contract.

### 9. Participant reports

Admin report infrastructure exists, but participant-scoped report endpoints such as `/api/me/reports` and `/api/me/reports/{type}` do not exist. Any report additions must consume existing authoritative participant data and avoid duplicated financial calculations.

### 10. Participant frontend

No Participant SPA/component surface was found. Phase 10 should provide frontend-ready API contracts; it must not invent a frontend framework or rebuild the admin dashboard.

## Conflicting Functionality

1. Existing Phase 5 settlement behavior excluded participant Fund Share. The Phase 9 User Journey explicitly superseded that rule, and the current code now includes approved participant fund allocations.
2. Existing settlement payment behavior was settlement-level only. Phase 9 added immutable payment records and partial statuses.
3. Existing full-payment action previously wrote settlement paid fields directly. It now delegates to payment-record creation, but payment-source effects are not implemented.
4. The specification requires post-payment adjustment/reversal, while the current schema has no adjustment/reversal entity.
5. The specification requires payment-source-driven Fund effects, while the current payment schema has no source field and no Bank/Cash domain.

## Required Changes

### Safe, well-defined changes

- Add a centralized settlement amount-due calculator/action using existing decimal conventions.
- Add `/api/me` settlement detail and payment-history read endpoints.
- Add `/api/me/capital/growth` based only on persisted capital snapshots and explicitly documented movement projection.
- Add safe singular participant API projections where existing relationships support them.
- Add focused IDOR, pagination, response-contract, and no-secret tests.
- Preserve the calculator as `BLOCKED — FORMULA REQUIRED`.

### Changes requiring a domain contract before implementation

- Payment source taxonomy and actual Fund/Bank/Cash effects.
- Post-payment adjustment/reversal amount semantics and effect on payable state.
- Participant notification preferences schema and mandatory notification policy.
- Participant report endpoint/export scope.

## Database Changes

Already added in Phase 9:

- `settlement_payments`

Potential Phase 10 additions, only after the domain contract is confirmed:

- payment source columns or source reference structure
- settlement adjustment/reversal table
- notification preferences table

No destructive migration is justified. Existing settlement/payment history must remain intact.

## API Changes

Required API additions or reconciliation:

- `GET /api/me/settlements/{settlement}`
- `GET /api/me/settlements/{settlement}/payments`
- `GET /api/me/capital/growth`
- safe singular `/api/me` detail endpoints where required
- existing password-change route reconciliation
- participant report endpoints only for authoritative data

Potential future mutation API:

- admin payment source input
- admin post-payment adjustment/reversal

These must remain blocked until their domain contracts are explicit.

## Tests Required

### Settlement/payment

- exact 120,000 profit + 30,000 Fund Share - 20,000 recorded payments = 130,000 due
- principal excluded
- multiple payments
- partial and final state
- overpayment rejection
- concurrent payment safety
- payment immutability
- explicit source behavior
- no automatic Fund deduction
- pre-payment revision lineage
- post-payment adjustment/reversal immutability and audit

### Participant API

- authenticated self scope
- direct ID manipulation
- settlement detail isolation
- payment-history isolation
- capital growth output from snapshots
- approved profit filtering
- pagination and filters
- safe response fields
- password-change behavior

### Architecture/security

- thin routes/controllers
- no financial formulas in resources/frontend
- no float/double financial paths
- no secret leakage
- audit completeness
- migration rollback/fresh database

## Risks

1. Adding payment-source effects without a Bank/Cash model could misstate financial ledgers.
2. Implementing adjustment/reversal without a defined accounting effect could create false balances.
3. A signed adjustment amount requires a clear convention for positive/negative corrections.
4. Participant report exports could leak internal/admin fields if projections are not allowlisted.
5. Directly deriving participant Fund Share from all allocations could double-count if future allocation types are added without an approved inclusion policy.
6. Payment status and item-level paid amounts are currently settlement-level authoritative; allocating partial payments across multiple participants is not defined.
7. Calculator remains intentionally blocked and must not be implemented speculatively.

## Current Verification Baseline

Before Phase 10 implementation changes, Phase 9 verification recorded:

- Unit: 2 passed / 5 assertions
- Feature: 99 passed / 428 assertions
- Full: 101 passed / 433 assertions
- Clean migration: PASS
- Route audit: PASS, 77 routes
- Frontend build: PASS
- Precision audit: PASS
- `git diff --check`: PASS

## Recommendation

Proceed only with the safe read-model/API contract additions and centralized calculation refactor after reviewing this gap analysis. Stop before payment-source ledger effects, adjustment/reversal mutations, notification preferences, or calculator implementation until their exact domain contracts are approved.
