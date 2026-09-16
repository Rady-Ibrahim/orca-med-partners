<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitRevisionAction;
use App\Actions\Funds\CreateFundAction;
use App\Actions\Funds\CreateFundTransactionAction;
use App\Actions\Investment\ApproveInvestmentAction;
use App\Actions\Settlements\ApproveSettlementAction;
use App\Actions\Settlements\CreateAnnualSettlementAction;
use App\Actions\Settlements\CreateSettlementRevisionAction;
use App\Actions\Settlements\RecordSettlementPaymentAction;
use App\Domain\Financial\Exceptions\FundBalanceDriftException;
use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use App\Domain\Financial\Exceptions\InvalidAnnualSettlementException;
use App\Domain\Financial\Exceptions\InvalidCapitalSnapshotException;
use App\Domain\Financial\Exceptions\InvalidGrossProfitException;
use App\Domain\Financial\Rules\DistributionRuleValidator;
use App\Http\Requests\StoreFundRequest;
use App\Http\Requests\StoreFundTransactionRequest;
use App\Http\Requests\StoreSettlementPaymentRequest;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\Investment;
use App\Models\MonthlyProfit;
use App\Models\Settlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Throwable;

final class AdminActionsController
{
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

    public function storeCapitalSnapshot(Request $request): JsonResponse|RedirectResponse
    {
        abort_if($request->user()->cannot('capital.manage'), 403, 'Forbidden.');

        $data = $request->validate([
            'snapshot_date' => ['required', 'date'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'total_capital' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.participant_id' => ['required', 'integer', 'exists:participants,id'],
            'items.*.capital' => ['required', 'numeric', 'min:0'],
        ]);

        $snapshot = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request): CapitalSnapshot {
            $totalCapital = '0.00';
            foreach ($data['items'] as $item) {
                $totalCapital = bcadd($totalCapital, number_format((float) $item['capital'], 2, '.', ''), 2);
            }

            $snapshot = CapitalSnapshot::query()->create([
                'snapshot_date' => $data['snapshot_date'],
                'year' => (int) $data['year'],
                'month' => (int) $data['month'],
                'total_capital' => isset($data['total_capital']) && $data['total_capital'] !== null ? (string) $data['total_capital'] : $totalCapital,
                'status' => 'final',
                'created_by_admin_id' => $request->user()->id,
            ]);

            foreach ($data['items'] as $index => $item) {
                $capital = number_format((float) $item['capital'], 2, '.', '');
                $ratio = bccomp((string) $totalCapital, '0.00', 2) === 0
                    ? '0.0000'
                    : bcdiv($capital, $totalCapital, 4);

                \App\Models\CapitalSnapshotItem::query()->create([
                    'capital_snapshot_id' => $snapshot->id,
                    'participant_id' => (int) $item['participant_id'],
                    'participant_capital_snapshot' => $capital,
                    'participant_ratio_snapshot' => $ratio,
                    'calculation_metadata' => ['index' => $index],
                ]);
            }

            return $snapshot;
        });

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم إنشاء لقطة رأس المال.', 'data' => ['id' => $snapshot->id]], 201);
        }

        return redirect()->route('admin.capital')->with('success', 'تم إنشاء لقطة رأس المال.');
    }

    public function storeDistributionRule(Request $request): JsonResponse|RedirectResponse
    {
        Gate::forUser($request->user())->authorize('create', DistributionRule::class);

        $data = $request->validate([
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'management_fee_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'depreciation_fund_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'growth_fund_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'incentive_fund_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'distributed_share_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'status' => ['required', 'in:draft,active,locked'],
            'is_default' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

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

        $data = $request->validate([
            'effective_from' => ['sometimes', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'management_fee_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'depreciation_fund_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'growth_fund_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'incentive_fund_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'distributed_share_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'status' => ['sometimes', 'in:draft,active,locked'],
            'is_default' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

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

    private function fail(Request $request, Throwable $e, string $fallbackMessage): JsonResponse|RedirectResponse
    {
        $message = $fallbackMessage;
        if ($e instanceof ImmutableFinancialRecordException
            || $e instanceof InvalidAnnualSettlementException
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