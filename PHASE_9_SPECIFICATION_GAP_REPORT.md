# PHASE 9 — SPECIFICATION GAP REPORT

## Status

# BLOCKED — SPECIFICATION REQUIRED

Implementation was **not allowed**. No application code, routes, models, migrations, financial services, UI, or tests were changed for Phase 9.

The supplied Phase 9 prompt correctly requires a specification gate, but it does not provide the actual Investment Calculator business contract needed to pass that gate.

## Scope

The only verified Phase 9 reference in the repository is:

> Phase 9 — Investment Calculator was not started.

The supplied execution prompt defines a discovery and safety process. It does not define a calculator that can be implemented without inventing financial behavior.

## Confirmed Requirements

The repository and supplied prompt confirm the following constraints:

- Do not invent a financial formula.
- Do not change Phase 3, Phase 4, or Phase 5 financial behavior.
- Preserve DECIMAL-based financial values and arbitrary-precision operations.
- Preserve existing Half-Even rounding behavior.
- Preserve capital snapshot semantics.
- Preserve approved-profit allocation rules.
- Preserve settlement immutability and revision semantics.
- Do not automatically include principal in settlements.
- Do not automatically include participant fund share in settlements.
- Do not invent prior payout/deduction sources.
- Do not automatically deduct settlement payments from Growth/Depreciation funds.
- Do not introduce partial settlement payment semantics.
- Do not allow a calculator request to mutate financial ledgers unless explicitly specified.
- Preserve Admin/Participant separation and IDOR protections.
- Keep routes thin, controllers thin, and financial calculations in backend/domain layers.

These are safety constraints, not a calculator specification.

## Missing Requirements

The following critical items remain undefined and block implementation:

### 1. Business Meaning

It is not defined what the calculator calculates. The product owner must select and document one or more exact meanings, for example:

- historical participant profit based on approved records
- projected profit
- annual return
- capital growth
- investment maturity value
- withdrawal value
- participant share
- a non-financial scenario estimate

The existing financial engine calculates monthly profit allocation from a supplied gross profit, capital snapshot, and distribution-rule snapshot. That does not establish a calculator formula or projection policy.

### 2. Inputs

The following are unspecified:

- exact field names
- principal/investment amount
- period or dates
- participant identity
- capital snapshot selection
- approved profit selection
- distribution rule selection
- rate input
- whether inputs are user-entered or backend-derived
- required versus optional fields
- minimum and maximum values
- handling of zero values
- handling of negative values
- handling of missing historical records

### 3. Formula

No formula is defined.

There is no approved formula for any of the following possibilities:

- simple return
- compound return
- annualized return
- projected profit
- capital growth
- participant allocation
- maturity value
- withdrawal value
- fees or deductions

The existing `FinancialCalculationService` formula is for monthly profit allocation and cannot be repurposed as an Investment Calculator without an explicit product decision.

### 4. Rate Source

It is not defined whether the calculator rate comes from:

- `distribution_rules`
- an approved monthly-profit record
- a historical rule snapshot
- a manually entered scenario rate
- a fixed product rate
- an external source

Effective-date behavior, historical behavior, fallback behavior, and who can modify the rate are also unspecified.

### 5. Precision and Rounding

The project has an existing `FinancialRoundingService`, but the calculator contract does not say:

- money scale for calculator output
- rate scale
- whether intermediate values are rounded
- whether only final values are rounded
- how Half-Even rounding applies to the calculator
- how values are displayed versus persisted
- whether calculator results are persisted at all

No implementation can safely choose these rules.

### 6. Authorization

It is not defined who may use the calculator:

- Admin only
- Participant only
- both
- specific admin permission
- participant only for self-owned records
- anonymous public scenario calculator

Participant isolation requirements are known generally, but the calculator-specific resource scope is not defined.

### 7. API Contract

No exact API contract exists. Missing details include:

- HTTP method
- endpoint path
- request payload
- validation response
- success response shape
- error response shape
- whether results are paginated
- whether results are transient or persisted
- idempotency expectations

### 8. UI Contract

No UI requirement exists for:

- page location
- admin versus participant visibility
- fields and controls
- result presentation
- loading state
- empty state
- validation errors
- unauthorized behavior
- mobile/responsive behavior

### 9. Audit Behavior

It is not specified whether a calculator read/request/result must be audited. If audit is required, the following are undefined:

- event name
- actor
- entity
- request/result data to retain
- sensitive-data handling
- retention
- immutability expectations

The existing audit architecture must not be used to create meaningless read events without this decision.

### 10. Notifications

No calculator notification requirement exists. No recipient, channel, trigger, timing, or after-commit behavior is defined.

## Repository Evidence

Relevant existing implementation inspected:

- [PHASE_9_DISCOVERY_REPORT.md](PHASE_9_DISCOVERY_REPORT.md)
- [PHASE_8_COMPLETION_REPORT.md](PHASE_8_COMPLETION_REPORT.md)
- [app/Models/Investment.php](app/Models/Investment.php)
- [app/Domain/Financial/Services/FinancialCalculationService.php](app/Domain/Financial/Services/FinancialCalculationService.php)
- [app/Domain/Financial/ValueObjects/FinancialRoundingService.php](app/Domain/Financial/ValueObjects/FinancialRoundingService.php)
- [app/Models/CapitalSnapshot.php](app/Models/CapitalSnapshot.php)
- [app/Models/MonthlyProfit.php](app/Models/MonthlyProfit.php)
- [app/Models/DistributionRule.php](app/Models/DistributionRule.php)
- [app/Models/Settlement.php](app/Models/Settlement.php)
- [routes/api.php](routes/api.php)
- [routes/web.php](routes/web.php)
- existing financial, security, IDOR, and Participant API tests

The repository contains no standalone PRD, roadmap file, calculator action, calculator service, calculator route, calculator controller, calculator request, calculator view, or calculator-specific tests.

## Existing Related Implementation

The existing financial engine has a defined purpose: allocate a supplied gross profit across management, depreciation, growth, incentive, and participant distribution pools using a distribution-rule snapshot and capital snapshot. It uses decimal strings, BCMath, and the existing rounding service.

That service is not an Investment Calculator specification. Reusing it would require explicit decisions about what the calculator input represents and whether the output is historical or projected.

Existing settlement logic also intentionally excludes principal and participant fund share from annual settlement calculations and does not create an independent prior-payout source. Those decisions must remain unchanged unless a later approved specification explicitly supersedes them.

## Financial Risks

Implementing without the missing specification could:

- invent a return or projection formula
- treat principal as profit or payable value
- expose projected values as approved financial results
- use current rates for historical periods
- ignore distribution-rule snapshots
- create duplicate allocations or deductions
- alter settlement or fund semantics
- introduce incorrect rounding
- imply guaranteed returns
- mutate financial records from a read-only calculator request

## Security Risks

An underspecified calculator could:

- allow a participant to calculate against another participant's investment
- trust client-supplied participant IDs
- expose internal capital snapshots or distribution configuration
- leak admin-only financial data
- create unaudited financial mutations
- produce responses containing sensitive internal fields

## Exact Decisions Required From Product / Business Owner

Before implementation, provide one approved answer for each item:

1. What exact business concept does the calculator calculate?
2. Is the result historical, current, projected, or scenario-only?
3. What are the exact inputs and their types/ranges?
4. What is the exact formula, with named variables and calculation order?
5. What is the authoritative rate source?
6. How are effective dates and historical rate snapshots selected?
7. What are the money and rate scales?
8. When is rounding applied, and must Half-Even be used at intermediate steps?
9. Who is authorized to use it?
10. Can Participants calculate only for themselves?
11. What exact API endpoint and request/response schema are required?
12. Is a UI required, and where should it appear?
13. Are calculator results transient or persisted?
14. Does any calculator operation mutate investments, capital, profits, funds, or settlements?
15. Is audit logging required for requests/results/configuration changes?
16. Are notifications required?
17. What are the exact validation and error responses?
18. What acceptance tests define completion?

## Recommended Acceptance Criteria

Once the business decisions are supplied, the approved specification should require at minimum:

- formula unit tests with normal, zero, boundary, invalid, and precision cases
- effective-date and historical-rate tests
- Half-Even rounding tests
- authorized Admin/Participant access tests
- Participant self-scope and IDOR tests
- secret/internal-field exclusion tests
- API validation and response-contract tests
- proof that calculator calls do not mutate financial ledgers unless explicitly required
- audit tests if audit is specified
- notification after-commit tests if notifications are specified
- route and architecture audits
- full Phase 1–8 regression
- migration verification
- precision scan for float/double usage
- frontend build and browser QA if UI is specified

## Unrelated Existing Regression

The repository currently has an unrelated dashboard markup regression:

`Tests\\Feature\\AdminDashboardTest::test_admin_can_login_and_view_database_backed_dashboard`

The expected Arabic phrase `إجمالي رأس المال` is split by whitespace/newline in the current dashboard markup.

This is not caused by Phase 9 and was not changed during this specification-gate task.

## Verification Evidence

The previous discovery verification recorded:

- Unit: 2 passed / 5 assertions
- Feature: 97 passed / 1 failed / 418 assertions
- Full suite: failed due to the same dashboard test
- Route audit: PASS, 76 routes
- Frontend build: PASS
- Application code changes during discovery: none

## Final Decision

# BLOCKED — SPECIFICATION REQUIRED

No Phase 9 implementation is authorized.

Do not create an implementation plan or calculator code until the exact business and technical decisions above are approved. Phase 9 must remain stopped, and Phase 10 or later work must not begin as a substitute.
