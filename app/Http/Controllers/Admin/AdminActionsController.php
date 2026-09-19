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
use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use App\Domain\Financial\Exceptions\InvalidAnnualSettlementException;
use App\Domain\Financial\Exceptions\InvalidCapitalSnapshotException;
use App\Domain\Financial\Exceptions\InvalidGrossProfitException;
use App\Domain\Financial\Rules\DistributionRuleValidator;
use App\Http\Requests\StoreFundRequest;
use App\Http\Requests\StoreFundTransactionRequest;
use App\Http\Requests\StoreSettlementPaymentRequest;
use App\Http\Requests\UpdateFundRequest;
use App\Models\CapitalSnapshot;
use App\Models\CapitalSnapshotItem;
use App\Models\DepreciationNote;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\Investment;
use App\Models\MonthlyProfit;
use App\Models\Settlement;
use App\Services\SecurityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            if (in_array($fund->code, ['depreciation_fund', 'growth_fund', 'incentive_fund'], true)) {
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
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'transaction_date' => ['sometimes', 'date'],
        ]);

        $old = $fundTransaction->only(['reference', 'description', 'notes', 'transaction_date']);

        try {
            $fundTransaction->fill([
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? $fundTransaction->transaction_date?->toDateString(),
            ])->save();
        } catch (ImmutableFinancialRecordException $e) {
            return $this->fail($request, $e, 'لا يمكن تعديل المبلغ أو النوع أو رصيد الحركة المالية.');
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر تحديث الحركة المالية.');
        }

        app(SecurityAuditService::class)->log('fund_transaction_updated', $request->user(), 'fund_transaction', $fundTransaction->id, [
            'fund_id' => $fund->id,
            'old' => $old,
            'new' => $fundTransaction->only(['reference', 'description', 'notes', 'transaction_date']),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'تم تحديث الحركة المالية.', 'data' => ['id' => $fundTransaction->id]]);
        }

        return redirect()->route('admin.funds')->with('success', 'تم تحديث الحركة المالية.');
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

        $snapshot = DB::transaction(function () use ($data, $request): CapitalSnapshot {
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

                CapitalSnapshotItem::query()->create([
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

    public function updateCapitalSnapshot(Request $request, CapitalSnapshot $capitalSnapshot): JsonResponse|RedirectResponse
    {
        abort_if($request->user()->cannot('capital.manage'), 403, 'Forbidden.');

        $data = $request->validate([
            'snapshot_date' => ['sometimes', 'date'],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'total_capital' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.participant_id' => ['sometimes', 'integer', 'exists:participants,id'],
            'items.*.capital' => ['sometimes', 'numeric', 'min:0'],
        ]);

        try {
            DB::transaction(function () use ($data, $capitalSnapshot): void {
                $capitalSnapshot->fill($data)->save();

                if (array_key_exists('items', $data) && $data['items'] !== null) {
                    $totalCapital = '0.00';
                    foreach ($data['items'] as $item) {
                        $totalCapital = bcadd($totalCapital, number_format((float) $item['capital'], 2, '.', ''), 2);
                    }

                    $capitalSnapshot->items()->delete();

                    foreach ($data['items'] as $index => $item) {
                        $capital = number_format((float) $item['capital'], 2, '.', '');
                        $ratio = bccomp($totalCapital, '0.00', 2) === 0
                            ? '0.0000'
                            : bcdiv($capital, $totalCapital, 4);

                        CapitalSnapshotItem::query()->create([
                            'capital_snapshot_id' => $capitalSnapshot->id,
                            'participant_id' => (int) $item['participant_id'],
                            'participant_capital_snapshot' => $capital,
                            'participant_ratio_snapshot' => $ratio,
                            'calculation_metadata' => ['index' => $index],
                        ]);
                    }

                    $capitalSnapshot->update(['total_capital' => $totalCapital]);
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

        try {
            $distributionRule->delete();
        } catch (Throwable $e) {
            return $this->fail($request, $e, 'تعذر حذف قاعدة التوزيع.');
        }

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
            'rate' => ['required', 'numeric', 'min:0', 'max:1'],
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
                'rate' => (string) $data['rate'],
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
            'rate' => ['sometimes', 'required', 'numeric', 'min:0', 'max:1'],
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
            'rate' => isset($data['rate']) ? (string) $data['rate'] : null,
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
        abort_if($investment->status !== 'pending', 403, 'يمكن تعديل الاستثمارات قيد الانتظار فقط.');

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
        abort_if($investment->status !== 'pending', 403, 'يمكن حذف الاستثمارات قيد الانتظار فقط.');

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
