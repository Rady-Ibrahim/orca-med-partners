# PHASE 8 — PARTICIPANT API

## 1. Scope

Phase 8 adds the read-only Participant dashboard API using the existing Sanctum participant authentication and `ensure.participant.context` middleware. No second authentication system was introduced, and no Phase 5 settlement or financial-engine semantics were changed.

Implemented dashboard collections:

- Profile
- Investments
- Capital snapshot allocations
- Approved participant profits
- Participant fund allocations supported by existing ownership data
- Participant depreciation notes
- Participant annual settlement items
- Participant notifications

All collection data is scoped from the authenticated Participant identity. No client-supplied `participant_id` controls the query scope.

## 2. Endpoints

| Method | Endpoint | Purpose | Auth | Authorization / Scope |
|---|---|---|---|---|
| GET | `/api/me` | Safe Participant profile | Participant Sanctum | Authenticated Participant only |
| GET | `/api/me/investment` | Own investment collection | Participant Sanctum | `participant_id` from auth context |
| GET | `/api/me/capital` | Own capital snapshot items | Participant Sanctum | Own `capital_snapshot_items` only |
| GET | `/api/me/profits` | Own approved profit allocations | Participant Sanctum | Own allocations; approved monthly profits only |
| GET | `/api/me/funds` | Own participant fund allocations | Participant Sanctum | Existing `participant_fund_allocations` ownership only |
| GET | `/api/me/depreciation` | Own depreciation notes | Participant Sanctum | Participant-owned notes only |
| GET | `/api/me/settlements` | Own settlement items | Participant Sanctum | Participant-owned settlement items only |
| GET | `/api/me/notifications` | Own notifications | Participant Sanctum | Participant-owned notifications only |

Supported filters are limited to existing data semantics: year, month, status, and notification read state. Collections are paginated at 20 records.

## 3. Security

- Existing Sanctum authentication and token rotation remain authoritative.
- Existing participant middleware remains in place.
- Participant collections query only the authenticated Participant ID.
- `participant_id` query parameters are ignored because the new endpoints do not use them for scope.
- Existing singular participant resource endpoints and IDOR policies remain intact.
- Added explicit tests for Participant A versus Participant B collection isolation.
- Profile and collection responses use allowlisted projections rather than raw Eloquent models.
- Passwords, password hashes, access tokens, refresh tokens, remember tokens, admin IDs, and internal model fields are not returned.
- No Participant mutation routes were added for investments, profits, settlements, funds, or depreciation.

## 4. Financial Safety

- No new financial formulas were introduced.
- Approved profit visibility is based on persisted `participant_profit_allocations` joined to `approved` monthly profits.
- Capital visibility is based on persisted `capital_snapshot_items`.
- Settlement visibility uses existing `settlement_items` and preserves Phase 5 principal/fund-share/amount-due decisions.
- Fund visibility uses only the existing participant allocation table; no fund ownership rule was invented.
- All money and rate values remain decimal strings in API projections.
- No `(float)`, `(double)`, `FLOAT`, or `DOUBLE` usage was found in the new Participant API paths.
- Existing financial immutability, revision, rounding, and fund ledger behavior was not modified.

## 5. Tests

### Participant API suite

- **4 passed / 34 assertions**
- Profile contract and secret exclusion
- All dashboard collection endpoints
- Participant A/B collection isolation
- Notification query-scope protection
- Financial mutation methods rejected
- Unauthenticated access rejected

### Unit tests

- **2 passed / 5 assertions**

### Feature tests

- **98 passed / 420 assertions**

### Full suite

- **100 passed / 425 assertions**
- **0 failures**

## 6. Regression

The full suite remains green across the existing Phase 1–7 coverage, including authentication, authorization, financial engine, funds, settlements, dashboard, reports, notifications, audit logs, IDOR protection, and Phase 7 remediation tests.

The unresolved Phase 7 items remain documented and were not invented or changed in Phase 8:

- Important admin notification recipient model
- Missing participant-level capital business metrics
- Missing fund-type semantics
- Large export streaming policy
- Browser QA availability

## 7. Routes

`php artisan route:list` passed. The Participant dashboard routes are controller-backed and protected by the existing participant context middleware. No route closures, queries, token parsing, authorization implementation, or financial formulas were added.

Final route audit includes:

- `GET /api/me`
- `GET /api/me/investment`
- `GET /api/me/capital`
- `GET /api/me/profits`
- `GET /api/me/funds`
- `GET /api/me/depreciation`
- `GET /api/me/settlements`
- `GET /api/me/notifications`

## 8. Remaining Decisions

No Phase 8 blocker remains.

The unresolved Phase 7 decisions listed above remain outside Phase 8 and do not affect the implemented read-only Participant API contract.

## Verification

- `php artisan migrate:fresh --seed`: **PASS**
- `php artisan test --testsuite=Unit`: **PASS**
- `php artisan test --testsuite=Feature`: **PASS**
- `php artisan test`: **PASS**
- `npm run build`: **PASS**
- `php artisan route:list`: **PASS**
- Participant API precision/scope audit: **PASS**

# PHASE 8 COMPLETE

Phase 9 — Investment Calculator was not started.
