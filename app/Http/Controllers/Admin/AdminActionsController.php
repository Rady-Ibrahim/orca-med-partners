<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitRevisionAction;
use App\Actions\Funds\CreateFundAction;
use App\Actions\Funds\CreateFundTransactionAction;
use App\Actions\Funds\UpdateFundAction;
use App\Actions\Investment\ApproveInvestmentAction;
use App\Actions\Settlements\ApproveSettlementAction;
use App\Actions\Settlements\CreateAnnualSettlementAction;
use App\Actions\Settlements\CreateSettlementRevisionAction;
use App\Actions\Settlements\RecordSettlementPaymentAction;
use App\Domain\Financial\Exceptions\FundBalanceDriftException;
use App\Domain\Financial\Exceptions\InvalidAnnualSettlementException;
use App\Domain\Financial\Exceptions\InvalidCapitalSnapshotException;
use App\Domain\Financial\Exceptions\InvalidGrossProfitException;
use App\Domain\Financial\Rules\DistributionRuleValidator;
use App\Domain\Financial\Services\CapitalCalculatorService;
use App\Domain\Financial\Services\FundBalanceService;
use App\Http\Requests\StoreFundRequest;
use App\Http\Requests\StoreFundTransactionRequest;
use App\Http\Requests\StoreSettlementPaymentRequest;
use App\Http\Requests\UpdateFundRequest;
use App\Models\AppSetting;
use App\Models\CapitalSnapshot;
use App\Models\DepreciationNote;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\Investment;
use App\Models\MonthlyProfit;
use App\Models\Settlement;
use App\Services\SecurityAuditService;
use App\Support\AppSettingBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Throwable;

final class AdminActionsController
{
    public function __construct(
        private FundBalanceService $funds,
        private SecurityAuditService $audit,
        private CapitalCalculatorService $capital,
    ) {}

    public function storeMonthlyProfit(Request $request, CreateMonthlyProfitAction $action): JsonResponse|RedirectResponse
    {
        abort_if($request->user()->cannot('create', MonthlyProfit::class), 403, 'Forbidden.');

        $data = $request->validate([
            'capital_snapshot_id' => ['required', 'integer', 'exists:capital_snapshots,id'],
            'distribution_rule_id' => ['required', 'integer', 'exists:distribution_rules,id'],
            'gross_profit' => ['required', 'numeric', 'min:0'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        try {
            $profit = $action->execute(
                $request->user(),
                CapitalSnapshot::query()->findOrFail($data['capital_snapshot_id']),
                DistributionRule::query()->findOrFail($data['distribution_rule_id']),
                (string) $data['gross_profit'],
                (int) $data['year'],
                (int) $data['month'],
            );
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر إنشاء فترة الأرباح الشهرية.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم إنشاء فترة الأرباح الشهرية كمسودة.', 'data' => ['id' => $profit->id]], 201);
        }

        return redirect()->route('admin.monthly-profits')->with('success', 'تم إنشاء فترة الأرباح الشهرية كمسودة.');
    }

    public function approveMonthlyProfit(Request $request, MonthlyProfit $monthlyProfit, ApproveMonthlyProfitAction $action): JsonResponse|RedirectResponse
    {
        abort_if($request->user()->cannot('approve', $monthlyProfit), 403, 'Forbidden.');

        try {
            $profit = $action->execute($request->user(), $monthlyProfit);
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر اعتماد فترة الأرباح.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم اعتماد فترة الأرباح الشهرية.', 'data' => ['id' => $profit->id]]);
        }

        return redirect()->route('admin.monthly-profits')->with('success', 'تم اعتماد فترة الأرباح الشهرية.');
    }

    public function reviseMonthlyProfit(Request $request, MonthlyProfit $monthlyProfit, CreateMonthlyProfitRevisionAction $action): JsonResponse|RedirectResponse
    {
        abort_if($request->user()->cannot('approve', $monthlyProfit), 403, 'Forbidden.');

        $data = $request->validate(['gross_profit' => ['required', 'numeric', 'min:0']]);

        try {
            $revision = $action->execute($request->user(), $monthlyProfit, (string) $data['gross_profit']);
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر إنشاء مراجعة الأرباح.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم إنشاء مراجعة الأرباح الشهرية.', 'data' => ['id' => $revision->id]], 201);
        }

        return redirect()->route('admin.monthly-profits')->with('success', 'تم إنشاء مراجعة الأرباح الشهرية.');
    }

    public function destroyMonthlyProfit(Request $request, MonthlyProfit $monthlyProfit): JsonResponse|RedirectResponse
    {
        abort_if($request->user()->cannot('approve', $monthlyProfit), 403, 'Forbidden.');

        $profitId = (int) $monthlyProfit->getKey();
        $period = sprintf('%04d / %02d', $monthlyProfit->year, $monthlyProfit->month);

        try {
            $monthlyProfit->delete();
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر حذف فترة الأرباح.');
        }

        $this->audit->log('monthly_profit_deleted', $request->user(), 'monthly_profit', $profitId, ['period' => $period]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم حذف فترة الأرباح.']);
        }

        return redirect()->route('admin.monthly-profits')->with('success', 'تم حذف فترة الأرباح.');
    }

    public function storeFund(StoreFundRequest $request, CreateFundAction $action): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manage', Fund::class);

        try {
            $fund = $action->execute($request->user(), $request->validated());
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر إنشاء الصندوق.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم إنشاء الصندوق بنجاح.', 'data' => ['id' => $fund->id]], 201);
        }

        return redirect()->route('admin.funds')->with('success', 'تم إنشاء الصندوق بنجاح.');
    }

    public function updateFund(UpdateFundRequest $request, Fund $fund, UpdateFundAction $action): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manage', $fund);

        try {
            $fund = $action->execute($request->user(), $fund, $request->validated());
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر تحديث الصندوق.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم تحديث الصندوق.', 'data' => ['id' => $fund->id]]);
        }

        return redirect()->route('admin.funds')->with('success', 'تم تحديث الصندوق.');
    }

    public function destroyFund(Request $request, Fund $fund): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manage', $fund);

        try {
            if (Fund::canonicalCodeOf($fund) !== null) {
                throw new ImmutableFinancialRecordException('لا يمكن حذف الصناديق البرمجية الأساسية.');
            }

            $fund->delete();
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر حذف الصندوق.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم حذف الصندوق.']);
        }

        return redirect()->route('admin.funds')->with('success', 'تم حذف الصندوق.');
    }

    public function storeFundTransaction(StoreFundTransactionRequest $request, Fund $fund, CreateFundTransactionAction $action): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manage', $fund);

        try {
            $transaction = $action->execute($request->user(), $fund, $request->validated());
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر تسجيل الحركة المالية.');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الحركة المالية.',
                'data' => ['id' => $transaction->id, 'balance' => $transaction->fund?->current_balance],
            ], 201);
        }

        return redirect()->route('admin.funds')->with('success', 'تم تسجيل الحركة المالية.');
    }

    public function updateFundTransaction(Request $request, Fund $fund, FundTransaction $fundTransaction): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manage', $fund);

        $this->ensureTransactionBelongsToFund($fundTransaction, $fund);

        $data = $request->validate([
            'transaction_type' => ['sometimes', 'in:deposit,withdrawal,adjustment'],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'transaction_date' => ['sometimes', 'date'],
        ]);

        $old = $fundTransaction->only(['transaction_type', 'amount', 'reference', 'description', 'notes', 'transaction_date']);

        try {
            DB::transaction(function () use ($data, $fundTransaction, $request): void {
                if (array_key_exists('transaction_type', $data)) {
                    $fundTransaction->transaction_type = $data['transaction_type'];
                }
                if (array_key_exists('amount', $data)) {
                    $fundTransaction->amount = (string) $data['amount'];
                }
                if (array_key_exists('transaction_date', $data)) {
                    $fundTransaction->transaction_date = $data['transaction_date'];
                }
                $fundTransaction->fill([
                    'reference' => $data['reference'] ?? $fundTransaction->reference,
                    'description' => $data['description'] ?? $fundTransaction->description,
                    'notes' => $data['notes'] ?? $fundTransaction->notes,
                ])->save();

                if (array_key_exists('transaction_type', $data) || array_key_exists('amount', $data)) {
                    $this->funds->recalculateRunningBalances($fundTransaction->fund, $request->user());
                }
            });
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر تحديث الحركة المالية.');
        }

        $this->audit->log('fund_transaction_updated', $request->user(), 'fund_transaction', $fundTransaction->id, [
            'fund_id' => $fund->id,
            'old' => $old,
            'new' => $fundTransaction->only(['transaction_type', 'amount', 'reference', 'description', 'notes', 'transaction_date']),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم تحديث الحركة المالية.', 'data' => ['id' => $fundTransaction->id, 'balance' => $fundTransaction->fund?->current_balance]]);
        }

        return redirect()->route('admin.funds')->with('success', 'تم تحديث الحركة المالية.');
    }

    public function destroyFundTransaction(Request $request, Fund $fund, FundTransaction $fundTransaction): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('manage', $fund);

        $this->ensureTransactionBelongsToFund($fundTransaction, $fund);

        $old = $fundTransaction->only(['transaction_type', 'amount', 'resulting_balance', 'transaction_date']);
        $fundId = (int) $fund->getKey();

        try {
            DB::transaction(function () use ($fundTransaction, $fundId, $request): void {
                $fundTransaction->delete();
                $this->funds->recalculateRunningBalances(Fund::query()->findOrFail($fundId), $request->user());
            });
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر حذف الحركة المالية.');
        }

        $this->audit->log('fund_transaction_deleted', $request->user(), 'fund_transaction', $fundTransaction->id, ['fund_id' => $fundId, 'old' => $old]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم حذف الحركة المالية.', 'data' => ['id' => $fundId, 'balance' => Fund::query()->findOrFail($fundId)->current_balance]]);
        }

        return redirect()->route('admin.funds')->with('success', 'تم حذف الحركة المالية.');
    }

    private function ensureTransactionBelongsToFund(FundTransaction $fundTransaction, Fund $fund): void
    {
        if ((int) $fundTransaction->fund_id !== (int) $fund->getKey()) {
            abort(404, 'Transaction not found in this fund.');
        }
    }

    public function storeInvestment(Request $request): JsonResponse|RedirectResponse
    {
        abort_if($request->user()->cannot('create', Investment::class), 403, 'Forbidden.');

        $data = $request->validate([
            'participant_id' => ['required', 'integer', 'exists:participants,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'invested_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $investment = Investment::query()->create([
                'participant_id' => (int) $data['participant_id'],
                'amount' => (string) $data['amount'],
                'invested_at' => $data['invested_at'] ?? now()->toDateString(),
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'created_by_admin_id' => $request->user()->id,
            ]);
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر إنشاء الاستثمار.');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الاستثمار بانتظار الاعتماد.',
                'data' => ['id' => $investment->id],
            ], 201);
        }

        return redirect()->route('admin.investments')->with('success', 'تم إنشاء الاستثمار بانتظار الاعتماد.');
    }

    public function approveInvestment(Request $request, Investment $investment, ApproveInvestmentAction $action): JsonResponse|RedirectResponse
    {
        abort_if($request->user()->cannot('approve', $investment), 403, 'Forbidden.');

        try {
            $action->execute($request->user(), $investment);
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر اعتماد الاستثمار.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم اعتماد الاستثمار.']);
        }

        return redirect()->route('admin.investments')->with('success', 'تم اعتماد الاستثمار.');
    }

    public function storeSettlement(Request $request, CreateAnnualSettlementAction $action): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('create', Settlement::class);

        $data = $request->validate(['year' => ['required', 'integer', 'min:2000', 'max:2100']]);

        try {
            $settlement = $action->execute($request->user(), (int) $data['year']);
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر إنشاء التسوية السنوية.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم إنشاء التسوية السنوية.', 'data' => ['id' => $settlement->id]], 201);
        }

        return redirect()->route('admin.settlements')->with('success', 'تم إنشاء التسوية السنوية.');
    }

    public function approveSettlement(Request $request, Settlement $settlement, ApproveSettlementAction $action): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('approve', $settlement);

        try {
            $settlement = $action->execute($request->user(), $settlement);
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر اعتماد التسوية.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم اعتماد التسوية السنوية.', 'data' => ['id' => $settlement->id]]);
        }

        return redirect()->route('admin.settlements')->with('success', 'تم اعتماد التسوية السنوية.');
    }

    public function recordSettlementPayment(StoreSettlementPaymentRequest $request, Settlement $settlement, RecordSettlementPaymentAction $action): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('pay', $settlement);

        try {
            $settlement = $action->execute($request->user(), $settlement, $request->validated());
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر تسجيل دفعة التسوية.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم تسجيل دفعة التسوية.', 'data' => ['id' => $settlement->id]]);
        }

        return redirect()->route('admin.settlements')->with('success', 'تم تسجيل دفعة التسوية.');
    }

    public function reviseSettlement(Request $request, Settlement $settlement, CreateSettlementRevisionAction $action): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('revise', $settlement);

        try {
            $revision = $action->execute($request->user(), $settlement);
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر إنشاء تسوية معدلة.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم إنشاء تسوية معدلة.', 'data' => ['id' => $revision->id]], 201);
        }

        return redirect()->route('admin.settlements')->with('success', 'تم إنشاء تسوية معدلة.');
    }

    public function destroySettlement(Request $request, Settlement $settlement): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('revise', $settlement);

        $settlementId = (int) $settlement->getKey();
        $year = (int) $settlement->year;

        try {
            DB::transaction(function () use ($settlement): void {
                foreach ($settlement->payments as $payment) {
                    $payment->receipt?->delete();
                }
                $settlement->payments()->delete();
                $settlement->adjustments()->delete();
                $settlement->delete();
            });
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر حذف التسوية السنوية.');
        }

        $this->audit->log('settlement_deleted', $request->user(), 'settlement', $settlementId, ['year' => $year]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم حذف التسوية السنوية.']);
        }

        return redirect()->route('admin.settlements')->with('success', 'تم حذف التسوية السنوية.');
    }

    public function storeCapitalSnapshot(Request $request): JsonResponse|RedirectResponse
    {
        abort_if($request->user()->cannot('capital.manage'), 403, 'Forbidden.');

        $data = $request->validate([
            'snapshot_date' => ['required', 'date'],
            'total_capital' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.participant_id' => ['required', 'integer', 'exists:participants,id'],
            'items.*.capital' => ['required', 'numeric', 'min:0'],
        ]);

        $snapshotDate = Carbon::parse($data['snapshot_date']);

        $snapshot = DB::transaction(function () use ($data, $snapshotDate, $request): CapitalSnapshot {
            $normalized = $this->capital->normalizeItems($data['items']);

            $snapshot = CapitalSnapshot::query()->create([
                'snapshot_date' => $data['snapshot_date'],
                'year' => $snapshotDate->year,
                'month' => $snapshotDate->month,
                'total_capital' => $normalized['total_capital'],
                'status' => 'final',
                'created_by_admin_id' => $request->user()->id,
            ]);

            $snapshot->syncItems($normalized['items'], $request->user());

            return $snapshot;
        });

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم إنشاء لقطة رأس المال.', 'data' => ['id' => $snapshot->id]], 201);
        }

        return redirect()->route('admin.capital')->with('success', 'تم إنشاء لقطة رأس المال.');
    }

    public function updateCapitalSnapshot(Request $request, CapitalSnapshot $capitalSnapshot): JsonResponse|RedirectResponse
    {
        abort_if($request->user()->cannot('capital.manage'), 403, 'Forbidden.');

        $data = $request->validate([
            'snapshot_date' => ['sometimes', 'date'],
            'total_capital' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.participant_id' => ['sometimes', 'integer', 'exists:participants,id'],
            'items.*.capital' => ['sometimes', 'numeric', 'min:0'],
        ]);

        try {
            DB::transaction(function () use ($data, $capitalSnapshot, $request): void {
                if (array_key_exists('snapshot_date', $data)) {
                    $capitalSnapshot->snapshot_date = $data['snapshot_date'];
                    $parsed = Carbon::parse($data['snapshot_date']);
                    $capitalSnapshot->year = $parsed->year;
                    $capitalSnapshot->month = $parsed->month;
                }
                if (array_key_exists('total_capital', $data) && $data['total_capital'] !== null && ! array_key_exists('items', $data)) {
                    $capitalSnapshot->total_capital = (string) $data['total_capital'];
                }
                $capitalSnapshot->save();

                if (array_key_exists('items', $data) && $data['items'] !== null) {
                    $capitalSnapshot->syncItems($data['items'], $request->user());
                }
            });
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر تحديث لقطة رأس المال.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم تحديث لقطة رأس المال.', 'data' => ['id' => $capitalSnapshot->id]]);
        }

        return redirect()->route('admin.capital')->with('success', 'تم تحديث لقطة رأس المال.');
    }

    public function destroyCapitalSnapshot(Request $request, CapitalSnapshot $capitalSnapshot): JsonResponse|RedirectResponse
    {
        abort_if($request->user()->cannot('capital.manage'), 403, 'Forbidden.');

        try {
            $capitalSnapshot->delete();
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر حذف لقطة رأس المال.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم حذف لقطة رأس المال.']);
        }

        return redirect()->route('admin.capital')->with('success', 'تم حذف لقطة رأس المال.');
    }

    public function storeDistributionRule(Request $request): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('create', DistributionRule::class);

        $data = $request->validate(
            [
                'effective_from' => ['required', 'date'],
                'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
                'management_fee_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'depreciation_fund_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'growth_fund_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'incentive_fund_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'distributed_share_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'status' => ['required', 'in:draft,active,locked'],
                'is_default' => ['sometimes', 'boolean'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            $this->distributionRuleMessages(),
        );

        $data = $this->percentRatesToRatio($data);

        try {
            $this->validateDistributionRates($data);
            $rule = DistributionRule::query()->create([
                ...$data,
                'is_default' => $data['is_default'] ?? false,
                'created_by_admin_id' => $request->user()->id,
            ]);
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'قيم قاعدة التوزيع غير صالحة.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم إنشاء قاعدة التوزيع.', 'data' => ['id' => $rule->id]], 201);
        }

        return redirect()->route('admin.distribution-rules')->with('success', 'تم إنشاء قاعدة التوزيع.');
    }

    public function updateDistributionRule(Request $request, DistributionRule $distributionRule): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('update', $distributionRule);

        $data = $request->validate(
            [
                'effective_from' => ['sometimes', 'date'],
                'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
                'management_fee_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
                'depreciation_fund_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
                'growth_fund_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
                'incentive_fund_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
                'distributed_share_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
                'status' => ['sometimes', 'in:draft,active,locked'],
                'is_default' => ['sometimes', 'boolean'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            $this->distributionRuleMessages(),
        );

        $data = $this->percentRatesToRatio($data);

        try {
            $this->validateDistributionRates(array_replace(
                $distributionRule->only(['management_fee_rate', 'depreciation_fund_rate', 'growth_fund_rate', 'incentive_fund_rate', 'distributed_share_rate']),
                $data,
            ));
            $distributionRule->fill($data)->save();
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'قيم قاعدة التوزيع غير صالحة.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم تحديث قاعدة التوزيع.', 'data' => ['id' => $distributionRule->id]]);
        }

        return redirect()->route('admin.distribution-rules')->with('success', 'تم تحديث قاعدة التوزيع.');
    }

    public function destroyDistributionRule(Request $request, DistributionRule $distributionRule): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('update', $distributionRule);

        $ruleId = (int) $distributionRule->getKey();

        try {
            DB::transaction(function () use ($distributionRule): void {
                $distributionRule->monthlyProfits()->update(['distribution_rule_id' => null]);
                $distributionRule->delete();
            });
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر حذف قاعدة التوزيع.');
        }

        $this->audit->log('distribution_rule_deleted', $request->user(), 'distribution_rule', $ruleId, []);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم حذف قاعدة التوزيع.']);
        }

        return redirect()->route('admin.distribution-rules')->with('success', 'تم حذف قاعدة التوزيع.');
    }

    public function storeDepreciation(Request $request): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('depreciation.create');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'transaction_date' => ['required', 'date'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'description' => ['nullable', 'string', 'max:500'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'participant_id' => ['nullable', 'integer', 'exists:participants,id'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
        ]);

        try {
            $note = DepreciationNote::query()->create([
                'participant_id' => isset($data['participant_id']) && $data['participant_id'] !== '' && $data['participant_id'] !== null ? (int) $data['participant_id'] : null,
                'fund_id' => isset($data['fund_id']) && $data['fund_id'] !== '' && $data['fund_id'] !== null
                    ? (int) $data['fund_id']
                    : Fund::query()->where('code', 'depreciation_fund')->value('id'),
                'amount' => (string) $data['amount'],
                'rate' => $this->percentToRatio((string) $data['rate']),
                'transaction_date' => $data['transaction_date'],
                'year' => (int) $data['year'],
                'month' => (int) $data['month'],
                'description' => $data['description'] ?? '',
                'admin_note' => $data['admin_note'] ?? null,
                'created_by_admin_id' => $request->user()->id,
            ]);
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر إنشاء مذكرة الإهلاك.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم إنشاء مذكرة الإهلاك.', 'data' => ['id' => $note->id]], 201);
        }

        return redirect()->route('admin.depreciation')->with('success', 'تم إنشاء مذكرة الإهلاك.');
    }

    public function updateDepreciation(Request $request, DepreciationNote $depreciationNote): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('depreciation.update');

        $data = $request->validate([
            'amount' => ['sometimes', 'required', 'numeric', 'min:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'rate' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'transaction_date' => ['sometimes', 'required', 'date'],
            'year' => ['sometimes', 'required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['sometimes', 'required', 'integer', 'min:1', 'max:12'],
            'description' => ['nullable', 'string', 'max:500'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'participant_id' => ['nullable', 'integer', 'exists:participants,id'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
        ]);

        $fillable = array_filter([
            'amount' => isset($data['amount']) ? (string) $data['amount'] : null,
            'rate' => isset($data['rate']) ? $this->percentToRatio((string) $data['rate']) : null,
            'transaction_date' => $data['transaction_date'] ?? null,
            'year' => isset($data['year']) ? (int) $data['year'] : null,
            'month' => isset($data['month']) ? (int) $data['month'] : null,
            'description' => $data['description'] ?? $depreciationNote->description,
            'admin_note' => $data['admin_note'] ?? $depreciationNote->admin_note,
            'participant_id' => array_key_exists('participant_id', $data)
                ? (($data['participant_id'] === '' || $data['participant_id'] === null) ? null : (int) $data['participant_id'])
                : $depreciationNote->participant_id,
            'fund_id' => array_key_exists('fund_id', $data)
                ? (($data['fund_id'] === '' || $data['fund_id'] === null) ? null : (int) $data['fund_id'])
                : $depreciationNote->fund_id,
        ], fn ($value) => $value !== null);

        try {
            $depreciationNote->fill($fillable)->save();
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر تحديث مذكرة الإهلاك.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم تحديث مذكرة الإهلاك.', 'data' => ['id' => $depreciationNote->id]]);
        }

        return redirect()->route('admin.depreciation')->with('success', 'تم تحديث مذكرة الإهلاك.');
    }

    public function destroyDepreciation(Request $request, DepreciationNote $depreciationNote): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('depreciation.update');

        try {
            $depreciationNote->delete();
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر حذف مذكرة الإهلاك.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم حذف مذكرة الإهلاك.']);
        }

        return redirect()->route('admin.depreciation')->with('success', 'تم حذف مذكرة الإهلاك.');
    }

    public function updateInvestment(Request $request, Investment $investment): JsonResponse|RedirectResponse
    {
        abort_if($request->user()->cannot('update', $investment), 403, 'Forbidden.');

        $data = $request->validate([
            'participant_id' => ['sometimes', 'required', 'integer', 'exists:participants,id'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'invested_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $investment->fill(array_filter([
                'participant_id' => isset($data['participant_id']) ? (int) $data['participant_id'] : $investment->participant_id,
                'amount' => isset($data['amount']) ? (string) $data['amount'] : $investment->amount,
                'invested_at' => array_key_exists('invested_at', $data)
                    ? ($data['invested_at'] ?: now()->toDateString())
                    : $investment->invested_at,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $investment->notes,
            ], fn ($value) => $value !== null))->save();
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر تحديث الاستثمار.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم تحديث الاستثمار.', 'data' => ['id' => $investment->id]]);
        }

        return redirect()->route('admin.investments')->with('success', 'تم تحديث الاستثمار.');
    }

    public function destroyInvestment(Request $request, Investment $investment): JsonResponse|RedirectResponse
    {
        abort_if($request->user()->cannot('update', $investment), 403, 'Forbidden.');

        try {
            $investment->delete();
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر حذف الاستثمار.');
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم حذف الاستثمار.']);
        }

        return redirect()->route('admin.investments')->with('success', 'تم حذف الاستثمار.');
    }

    public function updateSettings(Request $request): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('settings.manage');

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:100'],
            'currency_code' => ['required', 'string', 'max:10'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'date_format' => ['required', 'string', 'max:20'],
            'roi_base_annual_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'roi_growth_bonus_year1' => ['required', 'numeric', 'min:0', 'max:100'],
            'roi_growth_bonus_year2' => ['required', 'numeric', 'min:0', 'max:100'],
            'roi_growth_bonus_year3' => ['required', 'numeric', 'min:0', 'max:100'],
            'roi_growth_bonus_year4' => ['required', 'numeric', 'min:0', 'max:100'],
            'session_lifetime_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'login_throttle_attempts' => ['required', 'integer', 'min:1', 'max:100'],
        ], [
            'company_name.required' => 'اسم المنصة مطلوب.',
            'currency_code.required' => 'كود العملة مطلوب.',
            'currency_symbol.required' => 'رمز العملة مطلوب.',
            'roi_base_annual_rate.required' => 'معدل العائد الأساسي مطلوب.',
            'roi_base_annual_rate.min' => 'معدل العائد الأساسي يجب ألا يقل عن 0.',
            'roi_base_annual_rate.max' => 'معدل العائد الأساسي يجب ألا يزيد عن 100.',
            'roi_growth_bonus_year1.max' => 'بونص السنة الأولى يجب ألا يزيد عن 100.',
            'roi_growth_bonus_year2.max' => 'بونص السنة الثانية يجب ألا يزيد عن 100.',
            'roi_growth_bonus_year3.max' => 'بونص السنة الثالثة يجب ألا يزيد عن 100.',
            'roi_growth_bonus_year4.max' => 'بونص باقي السنوات يجب ألا يزيد عن 100.',
            'session_lifetime_minutes.required' => 'مدة الجلسة مطلوبة.',
            'login_throttle_attempts.required' => 'حد محاولات الدخول مطلوب.',
        ]);

        $settings = [
            'company_name' => $data['company_name'],
            'currency_code' => $data['currency_code'],
            'currency_symbol' => $data['currency_symbol'],
            'date_format' => $data['date_format'],
            'roi_base_annual_rate' => rtrim(rtrim(bcdiv((string) $data['roi_base_annual_rate'], '100', 6), '0'), '.'),
            'roi_growth_bonuses' => [
                rtrim(rtrim(bcdiv((string) $data['roi_growth_bonus_year1'], '100', 6), '0'), '.'),
                rtrim(rtrim(bcdiv((string) $data['roi_growth_bonus_year2'], '100', 6), '0'), '.'),
                rtrim(rtrim(bcdiv((string) $data['roi_growth_bonus_year3'], '100', 6), '0'), '.'),
                rtrim(rtrim(bcdiv((string) $data['roi_growth_bonus_year4'], '100', 6), '0'), '.'),
            ],
            'session_lifetime_minutes' => (string) $data['session_lifetime_minutes'],
            'login_throttle_attempts' => (string) $data['login_throttle_attempts'],
        ];

        foreach ($settings as $key => $value) {
            AppSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'updated_by_admin_id' => $request->user()->id],
            );
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'تم حفظ الإعدادات.',
                'data' => ['roi_base_annual_rate' => AppSettingBag::get('roi_base_annual_rate', '0.216')],
            ]);
        }

        return redirect()->route('admin.settings')->with('success', 'تم حفظ الإعدادات.');
    }

    private function validateDistributionRates(array $data): void
    {
        DistributionRuleValidator::validate([
            'management_fee_rate' => (string) ($data['management_fee_rate'] ?? '0'),
            'depreciation_fund_rate' => (string) ($data['depreciation_fund_rate'] ?? '0'),
            'growth_fund_rate' => (string) ($data['growth_fund_rate'] ?? '0'),
            'incentive_fund_rate' => (string) ($data['incentive_fund_rate'] ?? '0'),
            'distributed_share_rate' => (string) ($data['distributed_share_rate'] ?? '0'),
        ]);
    }

    /**
     * Convert percent inputs (0-100) to ratios (0-1) for the rate fields.
     */
    private function percentRatesToRatio(array $data): array
    {
        foreach (['management_fee_rate', 'depreciation_fund_rate', 'growth_fund_rate', 'incentive_fund_rate', 'distributed_share_rate'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = bcdiv((string) $data[$field], '100', 4);
            }
        }

        return $data;
    }

    private function percentToRatio(string $percent): string
    {
        return bcdiv($percent, '100', 4);
    }

    private function distributionRuleMessages(): array
    {
        $labels = [
            'management_fee_rate' => 'رسوم الإدارة',
            'depreciation_fund_rate' => 'الإهلاك',
            'growth_fund_rate' => 'النمو',
            'incentive_fund_rate' => 'الحوافز',
            'distributed_share_rate' => 'الموزّع للمشاركين',
        ];

        $messages = [];
        foreach ($labels as $field => $label) {
            $messages["{$field}.required"] = "نسبة {$label} مطلوبة.";
            $messages["{$field}.numeric"] = "نسبة {$label} يجب أن تكون رقمًا.";
            $messages["{$field}.min"] = "نسبة {$label} يجب ألا تقل عن 0.";
            $messages["{$field}.max"] = "نسبة {$label} يجب ألا تزيد عن 100.";
        }

        $messages['effective_from.required'] = 'تاريخ بدء السريان مطلوب.';
        $messages['effective_from.date'] = 'تاريخ بدء السريان غير صالح.';
        $messages['effective_to.date'] = 'تاريخ نهاية السريان غير صالح.';
        $messages['effective_to.after_or_equal'] = 'تاريخ نهاية السريان يجب أن يكون بعد تاريخ البداية أو مساويًا له.';
        $messages['status.required'] = 'حالة القاعدة مطلوبة.';
        $messages['status.in'] = 'قيمة الحالة غير صالحة.';
        $messages['is_default.boolean'] = 'قيمة القاعدة الافتراضية غير صالحة.';
        $messages['notes.max'] = 'الوصف يجب ألا يتجاوز 1000 حرف.';

        return $messages;
    }

    private function fail(Request $request, Throwable $e, string $fallbackMessage): JsonResponse|RedirectResponse
    {
        $message = $fallbackMessage;
        if ($e instanceof InvalidAnnualSettlementException
            || $e instanceof FundBalanceDriftException
            || $e instanceof InvalidCapitalSnapshotException
            || $e instanceof InvalidGrossProfitException
            || $e instanceof InvalidArgumentException) {
            $message = $e->getMessage();
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return redirect()->back()->withInput()->with('error', $message);
    }
}
