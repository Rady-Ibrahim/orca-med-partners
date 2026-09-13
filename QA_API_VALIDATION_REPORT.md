# QA & API Validation Report — ORCA MED Investor Mobile Application

**Status:** ✅ VERIFIED · 🟡 PARTIAL · ❌ GAP
**Evidence base:** `routes/api.php`, `EnsureParticipantContext` middleware, `GetParticipantDashboardDataAction`, all participant controllers, `FinancialRoundingService`/`SettlementAmountDueService`, full test suite run (**105 passed / 445 assertions**), `route:list` output, and a runtime paginator-shape probe (`php artisan tinker`).

---

## 1. Constraint Compliance

### C1 — Authorization & Scoping: ✅ PASS

| Requirement | Evidence | Verdict |
|---|---|---|
| `ensure.participant.context` on all investor routes | `routes/api.php:61-85` wraps all `/me`, `/participant/*` routes; middleware `app/Http/Middleware/EnsureParticipantContext.php` rejects no-token (`401`), non-participant token (`403`) | ✅ |
| Every DB query bound to `participant_id` of token owner | `GetParticipantDashboardDataAction` — every collection starts `where('participant_id', $participant->id)` from the resolved token user | ✅ |
| Cross-participant access → 403 + logged security event | `IdorProtectionTest` (7 scenarios: investment, capital, profit, fund, depreciation, settlement, notification all assert 403); `FinancialResourceController::authorizeOwner` calls `SecurityAuditService::log('authorization_denied', ...)`; `SecurityAuditTest` asserts denial is persisted and secrets redacted | ✅ |
| Mass-assignment / state-injection blocked | `MassAssignmentAndStateTest` — role/permission/ownership/status injection ignored, record stays bound to auth user | ✅ |

### C2 — Monetary & Numeric Precision: ✅ PASS

| Requirement | Evidence | Verdict |
|---|---|---|
| Currency returned as formatted decimal strings, float forbidden | Every money field in the dashboard action is returned via `(string)` cast: `'amount' => (string) $investment->amount`, `'participant_capital' => (string) ...`, `'amount_due' => (string) ...` | ✅ |
| `remaining = amount_due - paid_amount` | Computed live with `bcsub(..., 2)` at read-time (`GetParticipantDashboardDataAction` lines 55 & 78); collection returns `'remaining'` derived, detail returns it derived | ✅ |
| `amount_due = profit_share + fund_share - previous_withdrawals` | `SettlementAmountDueServiceTest`: `'120000' + '30000' - '20000' = '130000'`; also `'0.00'` for full payment. Produces immutable `amount_due` at settlement build | ✅ |
| Exponent/half-even safety | `FinancialRoundingTest`: `money('12.345')→'12.34'`, `money('99.995')→'100.00'`, `rate('0.025')→'0.0250'` | ✅ |
| No float drift across the pool | `ExcelReconciliationTest` and `AnnualSettlementTest` ("amount due does not invent prior deductions or include principal") pass | ✅ |

> Note: some derived money values are computed with `bcsub` while **negative** `remaining` (overpayment) can legitimately produce `"-0.00"`/negative string; ensure mobile formatters render `"-"` markers — confirmed allowed by `^-?\d+\.\d{2}$`.

### C3 — Mobile Usability & Performance: 🟡 PASS WITH FINDINGS

| Requirement | Evidence | Verdict |
|---|---|---|
| Paginated standard metadata `{data, links, meta}` | ⚠️ **FINDING M1.** Live paginator `toArray()` = `{current_page, data, first_page_url, from, last_page, links, next_page_url, path, per_page, prev_page_url, to, total}` — it has `links` but **no `meta`**. The tests assert `data.data...` (confirmed nested). This is the raw Laravel paginator, not the `{data, links, meta}` resource envelope. | 🟡 Deviation |
| Collections paginate (not unbounded) | `paginate(20)` in every collection + `withQueryString()` | ✅ |
| Auth rate limiting | `throttle:10,1` verified by `RateLimitingTest` (login, reset, refresh throttled) | ✅ |
| Binary downloads via signed URLs / streamed headers | ❌ **FINDING M2.** No participant download endpoints exist at all (reports/statements/receipts). PDF/Excel libs (`barryvdh/laravel-dompdf`, `maatwebsite/excel`) are **admin-web only**. `settlement_payments` has no receipt column. Requirement unmet until ❌ GAP G3/G4 implemented. | ❌ |
| Eager loading & column restriction | `with('fund','monthlyProfit')` etc. used; primary-key latest ordering | ✅ |

---

## 2. UI Component → Endpoint Coverage Matrix

| UI Component | Endpoint (method + path) | Contract status | Wiring |
|---|---|---|---|
| Profile | `GET /api/me` | ✅ | `MeController@show` |
| Dashboard — investment metrics | `GET /api/me/investment` | ✅ | `DashboardController@investment` |
| Dashboard — capital & ratio | `GET /api/me/capital` | ✅ | `DashboardController@capital` |
| Dashboard — capital growth (company) | `GET /api/me/capital/growth` | ✅ | `DashboardController@capitalGrowth` |
| Portfolio — profit allocations | `GET /api/me/profits` | ✅ | `DashboardController@profits` |
| Funds — allocations by fund | `GET /api/me/funds` | ✅ | `DashboardController@funds` |
| Funds — depreciation/movements | `GET /api/me/depreciation` | ✅ | `DashboardController@depreciation` |
| Settlements — breakdown | `GET /api/me/settlements` | ✅ | `DashboardController@settlements` |
| Settlements — detail ledger | `GET /api/me/settlements/{id}` | ✅ | `DashboardController@settlement` |
| Settlements — payout history | `GET /api/me/settlements/{id}/payments` | ✅ | `DashboardController@settlementPayments` |
| Settlements — alias list/detail | `GET /api/participant/settlements[/{id}]` | ✅ | `SettlementController` |
| Notifications — feed & filter | `GET /api/me/notifications` | ✅ (read-only) | `DashboardController@notifications` |
| Notifications — badge/unread count | — | ❌ **GAP G1** | — |
| Notifications — mark read / all | — | ❌ **GAP G2** | — |
| Reports — participant statements (PDF) | — | ❌ **GAP G3** | — |
| Reports — Excel export (participant) | — | ❌ **GAP G3** | — |
| Receipts — upload / download / signed URL | — | ❌ **GAP G4** | — |
| Tools — ROI calculator | — | ❌ **GAP G5** (blocked: no approved formula) | — |
| Settings — profile edit / 2FA / prefs / legal / support | — | ❌ **GAP G6** | — |
| Single-resource details (owner-scoped) | `GET /api/participant/{investments\|capital\|profits\|funds\|depreciation\|monthly-profits\|notifications}/{id}` | ✅ 7 endpoints, 403 on cross-owner | `FinancialResourceController` / `MonthlyProfitController@showForParticipant` |

**Runtime verification via suite:** ParticipantDashboardApiTest (contract shape + no-leak + 405 on mutation + 401 unauth), IdorProtectionTest (7 × 403), SecurityAuditTest, RateLimitingTest, SettlementAmountDueServiceTest, FinancialRoundingTest.

---

## 3. Findings Summary & Prioritized Remediation

| ID | Severity | Finding | Action |
|---|---|---|---|
| M1 | Low | Pagination returns raw Laravel shape (has `links`, lacks `meta`); constraint asked for `{data, links, meta}` | Wrap paginators with `JsonResource::collection(...)` or append `meta` (page/per_page/total) for a stable mobile contract |
| M2 | High | No participant file/download/report endpoints; `settlement_payments` lacks receipt column | Implement G3 (reports) + G4 (signed-URL receipt upload/download) |
| G1/G2 | High | Notification screen can't show unread badge or mark read | Add `GET /me/notifications/unread-count`, `PATCH .../read`, `PATCH .../read-all` |
| M1/G3 | Medium | Participant cannot download signed official statements / monthly-yearly Excel | Reuse existing PDF/Excel services in a participant-scoped endpoint |
| G5 | Medium | ROI calculator blocked (formula TBD) | Expose pure server-side `POST /me/tools/roi-simulation` once formula approved |
| G6 | Medium | Settings/2FA/legal/support absent | Add profile edit, 2FA lifecycle, `GET /legal/*`, `POST /support/contact` |

---

## 4. Deliverables

- **Automated evidence:** `php artisan test` → **105 passed / 445 assertions** (0 failures).
- **Postman Collection:** `postman/ORCA_MED_Partners_API.postman_collection.json` (v2.1, 5 groups / 25 requests) — env vars `baseUrl`, `accessToken`, `refreshToken`; Login auto-captures tokens; each request asserts its JSON contract (money as strings, envelope, ownership errors).
- **API spec + gap analysis:** `MOBILE_PARTNER_API_SPEC.md` (full contracts for existing + proposed endpoints).

**Overall verdict:** The published investor API is secure (authentication, ownership, rate-limits, audit) and money-exact; pagination envelope differs slightly from the stated `{data,links,meta}` norm; and the reports/notifications-actions/tools/settings screens require the six gaps above before a 100%-coverage mobile release.