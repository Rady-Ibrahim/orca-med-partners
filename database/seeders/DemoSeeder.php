<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\CapitalSnapshotItem;
use App\Models\DepreciationNote;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\Investment;
use App\Models\MonthlyProfit;
use App\Models\Notification;
use App\Models\Participant;
use App\Models\ParticipantFundAllocation;
use App\Models\ParticipantProfitAllocation;
use App\Models\Settlement;
use App\Models\SettlementAdjustment;
use App\Models\SettlementItem;
use App\Models\SettlementPayment;
use App\Support\AdminAuthorization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * DemoSeeder — بيانات تجريبية شاملة لمشروع ORCA MED Partners
 *
 * يشمل:
 * - 3 admins (superadmin, financial-manager, employee)
 * - 4 مشاركين (participants)
 * - استثمارات لكل مشارك
 * - 6 أشهر ربح شهري معتمدة (يناير–يونيو 2025)
 * - تسوية سنوية 2025 معتمدة ومدفوعة
 * - صناديق بأرصدة حقيقية
 * - إشعارات لكل مشارك
 *
 * بيانات تسجيل الدخول:
 * - superadmin / secret123
 * - financial-manager / secret123
 * - employee / secret123
 * - ahmed.shamri / secret123  (participant)
 * - sara.otaibi / secret123   (participant)
 * - mohammad.qahtani / secret123 (participant)
 * - noura.dosari / secret123  (participant)
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedAdmins();
            $this->seedParticipants();
            $this->seedInvestments();
            $this->seedDistributionRule();
            $this->seedFunds();
            $this->seedMonthlyProfits();
            $this->seedSettlement();
            $this->seedNotifications();
        });
    }

    // ─────────────────────────────────────────────────────────────
    // ADMINS
    // ─────────────────────────────────────────────────────────────

    private function seedAdmins(): void
    {
        $roles = AdminAuthorization::defaultRoleAssignments();

        $admins = [
            [
                'username'       => 'superadmin',
                'name'           => 'Super Administrator',
                'email'          => 'superadmin@orca-demo.test',
                'role'           => AdminAuthorization::ROLE_SUPER_ADMIN,
                'permissions'    => $roles[AdminAuthorization::ROLE_SUPER_ADMIN],
                'is_super_admin' => true,
                'status'         => 'active',
            ],
            [
                'username'       => 'financial-manager',
                'name'           => 'مدير مالي',
                'email'          => 'financial@orca-demo.test',
                'role'           => AdminAuthorization::ROLE_FINANCIAL_MANAGER,
                'permissions'    => $roles[AdminAuthorization::ROLE_FINANCIAL_MANAGER],
                'is_super_admin' => false,
                'status'         => 'active',
            ],
            [
                'username'       => 'employee',
                'name'           => 'موظف إداري',
                'email'          => 'employee@orca-demo.test',
                'role'           => AdminAuthorization::ROLE_EMPLOYEE,
                'permissions'    => $roles[AdminAuthorization::ROLE_EMPLOYEE],
                'is_super_admin' => false,
                'status'         => 'active',
            ],
        ];

        foreach ($admins as $data) {
            Admin::query()->updateOrCreate(
                ['username' => $data['username']],
                array_merge($data, ['password' => Hash::make('secret123')])
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // PARTICIPANTS
    // ─────────────────────────────────────────────────────────────

    private function seedParticipants(): void
    {
        $superAdmin = Admin::query()->where('username', 'superadmin')->first();

        $participants = [
            [
                'first_name'          => 'أحمد',
                'last_name'           => 'الشمري',
                'username'            => 'ahmed.shamri',
                'email'               => 'ahmed@orca-demo.test',
                'status'              => 'active',
                'role'                => 'participant',
                'permissions'         => [],
                'created_by_admin_id' => $superAdmin?->id,
            ],
            [
                'first_name'          => 'سارة',
                'last_name'           => 'العتيبي',
                'username'            => 'sara.otaibi',
                'email'               => 'sara@orca-demo.test',
                'status'              => 'active',
                'role'                => 'participant',
                'permissions'         => [],
                'created_by_admin_id' => $superAdmin?->id,
            ],
            [
                'first_name'          => 'محمد',
                'last_name'           => 'القحطاني',
                'username'            => 'mohammad.qahtani',
                'email'               => 'mohammad@orca-demo.test',
                'status'              => 'active',
                'role'                => 'participant',
                'permissions'         => [],
                'created_by_admin_id' => $superAdmin?->id,
            ],
            [
                'first_name'          => 'نورة',
                'last_name'           => 'الدوسري',
                'username'            => 'noura.dosari',
                'email'               => 'noura@orca-demo.test',
                'status'              => 'active',
                'role'                => 'participant',
                'permissions'         => [],
                'created_by_admin_id' => $superAdmin?->id,
            ],
        ];

        foreach ($participants as $data) {
            Participant::query()->updateOrCreate(
                ['username' => $data['username']],
                array_merge($data, ['password' => Hash::make('secret123')])
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // INVESTMENTS
    // رأس المال: أحمد 500k، سارة 300k، محمد 400k، نورة 200k = 1,400,000
    // ─────────────────────────────────────────────────────────────

    private function seedInvestments(): void
    {
        $superAdmin = Admin::query()->where('username', 'superadmin')->first();
        $financial  = Admin::query()->where('username', 'financial-manager')->first();

        $investments = [
            [
                'username' => 'ahmed.shamri',
                'amount'   => '500000.00',
                'date'     => '2025-01-01',
                'status'   => 'approved',
                'notes'    => 'استثمار أولي - يناير 2025',
            ],
            [
                'username' => 'sara.otaibi',
                'amount'   => '300000.00',
                'date'     => '2025-01-01',
                'status'   => 'approved',
                'notes'    => 'استثمار أولي - يناير 2025',
            ],
            [
                'username' => 'mohammad.qahtani',
                'amount'   => '400000.00',
                'date'     => '2025-01-01',
                'status'   => 'approved',
                'notes'    => 'استثمار أولي - يناير 2025',
            ],
            [
                'username' => 'noura.dosari',
                'amount'   => '200000.00',
                'date'     => '2025-01-01',
                'status'   => 'approved',
                'notes'    => 'استثمار أولي - يناير 2025',
            ],
        ];

        foreach ($investments as $inv) {
            $participant = Participant::query()->where('username', $inv['username'])->first();
            if (! $participant) {
                continue;
            }

            if (Investment::query()->where('participant_id', $participant->id)->exists()) {
                continue;
            }

            Investment::withoutEvents(function () use ($inv, $participant, $superAdmin, $financial): void {
                Investment::query()->create([
                    'participant_id'       => $participant->id,
                    'amount'               => $inv['amount'],
                    'invested_at'          => $inv['date'],
                    'status'               => $inv['status'],
                    'notes'                => $inv['notes'],
                    'created_by_admin_id'  => $superAdmin?->id,
                    'approved_by_admin_id' => $financial?->id,
                    'approved_at'          => now()->setDate(2025, 1, 2),
                ]);
            });
        }
    }

    // ─────────────────────────────────────────────────────────────
    // DISTRIBUTION RULE
    // ─────────────────────────────────────────────────────────────

    private function seedDistributionRule(): void
    {
        $superAdmin = Admin::query()->where('username', 'superadmin')->first();

        if (DistributionRule::query()->where('effective_from', '2025-01-01')->exists()) {
            return;
        }

        DistributionRule::withoutEvents(function () use ($superAdmin): void {
            DistributionRule::query()->create([
                'effective_from'         => '2025-01-01',
                'effective_to'           => null,
                'management_fee_rate'    => '0.2500',
                'depreciation_fund_rate' => '0.0500',
                'growth_fund_rate'       => '0.0250',
                'incentive_fund_rate'    => '0.0250',
                'distributed_share_rate' => '0.6500',
                'status'                 => 'active',
                'is_default'             => true,
                'notes'                  => 'قاعدة التوزيع الافتراضية 2025',
                'created_by_admin_id'    => $superAdmin?->id,
                'approved_by_admin_id'   => $superAdmin?->id,
                'approved_at'            => now()->setDate(2025, 1, 1),
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────
    // FUNDS
    // ─────────────────────────────────────────────────────────────

    private function seedFunds(): void
    {
        $superAdmin = Admin::query()->where('username', 'superadmin')->first();

        $funds = [
            [
                'code'        => 'growth_fund',
                'name'        => 'صندوق النمو',
                'description' => 'صندوق تراكم حصص النمو الشهرية',
            ],
            [
                'code'        => 'incentive_fund',
                'name'        => 'صندوق الحوافز',
                'description' => 'صندوق الحوافز للمشاركين',
            ],
            [
                'code'        => 'depreciation_fund',
                'name'        => 'صندوق الاستهلاك',
                'description' => 'صندوق مخصصات الاستهلاك',
            ],
        ];

        foreach ($funds as $f) {
            Fund::query()->firstOrCreate(
                ['code' => $f['code']],
                [
                    'name'                => $f['name'],
                    'current_balance'     => '0.00',
                    'status'              => 'active',
                    'description'         => $f['description'],
                    'created_by_admin_id' => $superAdmin?->id,
                ]
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // MONTHLY PROFITS  (يناير–يونيو 2025)
    //
    // إجمالي رأس المال = 1,400,000
    // نسب المشاركين:
    //   أحمد   500k / 1400k = 0.3571
    //   سارة   300k / 1400k = 0.2143
    //   محمد   400k / 1400k = 0.2857
    //   نورة   200k / 1400k = 0.1429  (الباقي يُكمّل لتجنب التقريب)
    // ─────────────────────────────────────────────────────────────

    private function seedMonthlyProfits(): void
    {
        $superAdmin = Admin::query()->where('username', 'superadmin')->first();
        $financial  = Admin::query()->where('username', 'financial-manager')->first();

        $rule = DistributionRule::query()->where('effective_from', '2025-01-01')->first();
        if (! $rule) {
            return;
        }

        $participants = Participant::query()->get()->keyBy('username');
        $totalCapital = '1400000.00';

        $ratios = [
            'ahmed.shamri'     => '0.3571',
            'sara.otaibi'      => '0.2143',
            'mohammad.qahtani' => '0.2857',
            'noura.dosari'     => '0.1429',
        ];

        $months = [
            ['year' => 2025, 'month' => 1, 'gross' => '140000.00', 'date' => '2025-01-31'],
            ['year' => 2025, 'month' => 2, 'gross' => '126000.00', 'date' => '2025-02-28'],
            ['year' => 2025, 'month' => 3, 'gross' => '154000.00', 'date' => '2025-03-31'],
            ['year' => 2025, 'month' => 4, 'gross' => '112000.00', 'date' => '2025-04-30'],
            ['year' => 2025, 'month' => 5, 'gross' => '147000.00', 'date' => '2025-05-31'],
            ['year' => 2025, 'month' => 6, 'gross' => '133000.00', 'date' => '2025-06-30'],
        ];

        $growthFund       = Fund::query()->where('code', 'growth_fund')->first();
        $incentiveFund    = Fund::query()->where('code', 'incentive_fund')->first();
        $depreciationFund = Fund::query()->where('code', 'depreciation_fund')->first();

        $ruleSnapshot = [
            'management_fee_rate'    => '0.2500',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate'       => '0.0250',
            'incentive_fund_rate'    => '0.0250',
            'distributed_share_rate' => '0.6500',
        ];

        foreach ($months as $m) {
            if (MonthlyProfit::query()
                ->where('year', $m['year'])
                ->where('month', $m['month'])
                ->where('version', 1)
                ->exists()
            ) {
                continue;
            }

            $gross = $m['gross'];

            // حسابات DECIMAL عبر bcmath فقط — لا float ولا double
            $managementAmount   = bcmul($gross, '0.2500', 2);
            $depreciationAmount = bcmul($gross, '0.0500', 2);
            $growthAmount       = bcmul($gross, '0.0250', 2);
            $incentiveAmount    = bcmul($gross, '0.0250', 2);
            $distributedAmount  = bcmul($gross, '0.6500', 2);

            // ─── Capital Snapshot ───
            $snapshot = CapitalSnapshot::withoutEvents(function () use ($m, $totalCapital, $superAdmin): CapitalSnapshot {
                return CapitalSnapshot::query()->create([
                    'snapshot_date'       => $m['date'],
                    'year'                => $m['year'],
                    'month'               => $m['month'],
                    'total_capital'       => $totalCapital,
                    'status'              => 'final',
                    'snapshot_metadata'   => ['source' => 'demo_seeder'],
                    'created_by_admin_id' => $superAdmin?->id,
                ]);
            });

            // ─── Snapshot Items per participant ───
            foreach ($ratios as $username => $ratio) {
                $participant = $participants->get($username);
                if (! $participant) {
                    continue;
                }

                CapitalSnapshotItem::query()->create([
                    'capital_snapshot_id'          => $snapshot->id,
                    'participant_id'               => $participant->id,
                    'participant_capital_snapshot' => bcmul($totalCapital, $ratio, 2),
                    'participant_ratio_snapshot'   => $ratio,
                    'calculation_metadata'         => ['source' => 'demo_seeder'],
                ]);
            }

            // ─── Monthly Profit ───
            $approvedDay = (int) substr($m['date'], -2);

            $profit = MonthlyProfit::withoutEvents(
                function () use (
                    $m,
                    $snapshot,
                    $rule,
                    $ruleSnapshot,
                    $gross,
                    $managementAmount,
                    $depreciationAmount,
                    $growthAmount,
                    $incentiveAmount,
                    $distributedAmount,
                    $superAdmin,
                    $financial,
                    $approvedDay
                ): MonthlyProfit {
                    return MonthlyProfit::query()->create([
                        'capital_snapshot_id'        => $snapshot->id,
                        'distribution_rule_id'       => $rule->id,
                        'distribution_rule_snapshot' => $ruleSnapshot,
                        'parent_id'                  => null,
                        'year'                       => $m['year'],
                        'month'                      => $m['month'],
                        'version'                    => 1,
                        'status'                     => 'approved',
                        'gross_profit'               => $gross,
                        'management_amount'          => $managementAmount,
                        'depreciation_amount'        => $depreciationAmount,
                        'growth_amount'              => $growthAmount,
                        'incentive_amount'           => $incentiveAmount,
                        'distributed_amount'         => $distributedAmount,
                        'rounding_delta_adjustment'  => '0.00',
                        'notes'                      => "ربح شهر {$m['month']}/{$m['year']} - تجريبي",
                        'created_by_admin_id'        => $superAdmin?->id,
                        'approved_by_admin_id'       => $financial?->id,
                        'approved_at'                => now()->setDate($m['year'], $m['month'], $approvedDay),
                    ]);
                }
            );

            // ─── Participant Profit Allocations ───
            // المشارك الأخير يأخذ الباقي لتجنب فروق التقريب
            $totalAllocated = '0.00';
            $lastUsername   = array_key_last($ratios);

            foreach ($ratios as $username => $ratio) {
                $participant = $participants->get($username);
                if (! $participant) {
                    continue;
                }

                if ($username === $lastUsername) {
                    $allocationAmount = bcsub($distributedAmount, $totalAllocated, 2);
                } else {
                    $allocationAmount = bcmul($distributedAmount, $ratio, 2);
                    $totalAllocated   = bcadd($totalAllocated, $allocationAmount, 2);
                }

                ParticipantProfitAllocation::query()->create([
                    'monthly_profit_id' => $profit->id,
                    'participant_id'    => $participant->id,
                    'amount'            => $allocationAmount,
                    'share_ratio'       => $ratio,
                    'status'            => 'approved',
                ]);

                // ─── Fund Allocations per participant ───
                if ($growthFund) {
                    ParticipantFundAllocation::query()->create([
                        'fund_id'           => $growthFund->id,
                        'monthly_profit_id' => $profit->id,
                        'participant_id'    => $participant->id,
                        'amount'            => bcmul($growthAmount, $ratio, 2),
                        'allocation_type'   => 'growth',
                    ]);
                }

                if ($incentiveFund) {
                    ParticipantFundAllocation::query()->create([
                        'fund_id'           => $incentiveFund->id,
                        'monthly_profit_id' => $profit->id,
                        'participant_id'    => $participant->id,
                        'amount'            => bcmul($incentiveAmount, $ratio, 2),
                        'allocation_type'   => 'incentive',
                    ]);
                }

                if ($depreciationFund) {
                    ParticipantFundAllocation::query()->create([
                        'fund_id'           => $depreciationFund->id,
                        'monthly_profit_id' => $profit->id,
                        'participant_id'    => $participant->id,
                        'amount'            => bcmul($depreciationAmount, $ratio, 2),
                        'allocation_type'   => 'depreciation',
                    ]);
                }
            }

            // ─── Fund Transactions ───
            $this->recordFundTransaction($growthFund, $profit, $growthAmount, 'profit_allocation', $m['date'], $superAdmin);
            $this->recordFundTransaction($incentiveFund, $profit, $incentiveAmount, 'profit_allocation', $m['date'], $superAdmin);
            $this->recordFundTransaction($depreciationFund, $profit, $depreciationAmount, 'profit_allocation', $m['date'], $superAdmin);

            // ─── Depreciation Note ───
            if ($depreciationFund) {
                DepreciationNote::withoutEvents(function () use ($profit, $depreciationFund, $depreciationAmount, $m, $superAdmin): void {
                    DepreciationNote::query()->create([
                        'participant_id'      => null,
                        'fund_id'             => $depreciationFund->id,
                        'monthly_profit_id'   => $profit->id,
                        'amount'              => $depreciationAmount,
                        'rate'                => '0.0500',
                        'transaction_date'    => $m['date'],
                        'year'                => $m['year'],
                        'month'               => $m['month'],
                        'description'         => "مخصص استهلاك شهر {$m['month']}/{$m['year']}",
                        'admin_note'          => 'تم إنشاؤه تلقائياً من الـ DemoSeeder',
                        'created_by_admin_id' => $superAdmin?->id,
                    ]);
                });
            }
        }

        // تحديث أرصدة الصناديق بآخر رصيد ناتج
        $this->recalculateFundBalances();
    }

    private function recordFundTransaction(
        ?Fund $fund,
        MonthlyProfit $profit,
        string $amount,
        string $type,
        string $date,
        ?Admin $admin
    ): void {
        if (! $fund) {
            return;
        }

        $currentBalance = (string) (FundTransaction::query()
            ->where('fund_id', $fund->id)
            ->orderByDesc('id')
            ->value('resulting_balance') ?? '0.00');

        $newBalance = bcadd($currentBalance, $amount, 2);

        FundTransaction::withoutEvents(function () use ($fund, $profit, $type, $date, $amount, $newBalance, $admin): void {
            FundTransaction::query()->create([
                'fund_id'             => $fund->id,
                'monthly_profit_id'   => $profit->id,
                'transaction_type'    => $type,
                'transaction_date'    => $date,
                'amount'              => $amount,
                'resulting_balance'   => $newBalance,
                'reference'           => "PROFIT-{$profit->id}",
                'description'         => "تحويل من أرباح شهر {$profit->month}/{$profit->year}",
                'notes'               => null,
                'created_by_admin_id' => $admin?->id,
            ]);
        });
    }

    private function recalculateFundBalances(): void
    {
        foreach (['growth_fund', 'incentive_fund', 'depreciation_fund'] as $code) {
            $fund = Fund::query()->where('code', $code)->first();
            if (! $fund) {
                continue;
            }

            $lastBalance = FundTransaction::query()
                ->where('fund_id', $fund->id)
                ->orderByDesc('id')
                ->value('resulting_balance');

            if ($lastBalance !== null) {
                DB::table('funds')->where('id', $fund->id)->update(['current_balance' => $lastBalance]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────
    // SETTLEMENT 2025
    //
    // إجمالي الأرباح الموزعة للأشهر الستة:
    //   gross 812,000 → distributed 65% = 527,800
    // ─────────────────────────────────────────────────────────────

    private function seedSettlement(): void
    {
        if (Settlement::query()->where('year', 2025)->where('version', 1)->exists()) {
            return;
        }

        $superAdmin = Admin::query()->where('username', 'superadmin')->first();
        $financial  = Admin::query()->where('username', 'financial-manager')->first();

        // مجموع الأرباح المخصصة لكل مشارك
        $profitAllocations = ParticipantProfitAllocation::query()
            ->join('monthly_profits', 'participant_profit_allocations.monthly_profit_id', '=', 'monthly_profits.id')
            ->where('monthly_profits.year', 2025)
            ->where('monthly_profits.status', 'approved')
            ->select(
                'participant_profit_allocations.participant_id',
                DB::raw('SUM(participant_profit_allocations.amount) as total')
            )
            ->groupBy('participant_profit_allocations.participant_id')
            ->pluck('total', 'participant_profit_allocations.participant_id');

        if ($profitAllocations->isEmpty()) {
            return;
        }

        $totalDistributed = (string) $profitAllocations->sum();
        $netPayable       = $totalDistributed;

        $settlement = Settlement::withoutEvents(function () use ($totalDistributed, $netPayable, $superAdmin, $financial): Settlement {
            return Settlement::query()->create([
                'parent_id'                => null,
                'year'                     => 2025,
                'version'                  => 1,
                'status'                   => 'paid',
                'total_distributed_amount' => $totalDistributed,
                'participant_profit_share' => $totalDistributed,
                'participant_fund_share'   => '0.00',
                'net_payable'              => $netPayable,
                'amount_due'               => $netPayable,
                'paid_amount'              => $netPayable,
                'notes'                    => 'تسوية سنوية 2025 — تجريبية',
                'created_by_admin_id'      => $superAdmin?->id,
                'approved_by_admin_id'     => $financial?->id,
                'paid_by_admin_id'         => $financial?->id,
                'approved_at'              => now()->setDate(2025, 7, 1),
                'payout_at'                => now()->setDate(2025, 7, 15),
            ]);
        });

        // ─── Settlement Items ───
        foreach ($profitAllocations as $participantId => $profitShare) {
            SettlementItem::query()->create([
                'settlement_id'  => $settlement->id,
                'participant_id' => $participantId,
                'profit_share'   => (string) $profitShare,
                'fund_share'     => '0.00',
                'net_payable'    => (string) $profitShare,
                'payment_status' => 'paid',
                'paid_amount'    => (string) $profitShare,
                'paid_at'        => now()->setDate(2025, 7, 15),
            ]);
        }

        // ─── Settlement Payment ───
        $payment = SettlementPayment::withoutEvents(function () use ($settlement, $netPayable, $financial): SettlementPayment {
            return SettlementPayment::query()->create([
                'settlement_id'       => $settlement->id,
                'amount'              => $netPayable,
                'paid_at'             => now()->setDate(2025, 7, 15),
                'payment_method'      => 'bank_transfer',
                'payment_source'      => 'operating_account',
                'reference'           => 'SETTLE-2025-001',
                'description'         => 'دفعة التسوية السنوية 2025',
                'created_by_admin_id' => $financial?->id,
            ]);
        });

        // ─── Settlement Adjustment (تعديل رمزي صفري) ───
        SettlementAdjustment::withoutEvents(function () use ($settlement, $payment, $superAdmin): void {
            SettlementAdjustment::query()->create([
                'settlement_id'         => $settlement->id,
                'settlement_payment_id' => $payment->id,
                'type'                  => 'correction',
                'direction'             => 'credit',
                'amount'                => '0.00',
                'reason'                => 'تعديل تجريبي — لا تأثير مالي',
                'reference'             => 'ADJ-2025-001',
                'created_by_admin_id'   => $superAdmin?->id,
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────
    // NOTIFICATIONS
    // ─────────────────────────────────────────────────────────────

    private function seedNotifications(): void
    {
        $superAdmin   = Admin::query()->where('username', 'superadmin')->first();
        $participants = Participant::query()->get();

        foreach ($participants as $participant) {
            if (Notification::query()->where('participant_id', $participant->id)->exists()) {
                continue;
            }

            $notifs = [
                [
                    'type'    => 'investment_approved',
                    'title'   => 'تم اعتماد استثمارك',
                    'body'    => "مرحباً {$participant->first_name}، تم اعتماد استثمارك بنجاح.",
                    'is_read' => true,
                ],
                [
                    'type'    => 'monthly_profit_approved',
                    'title'   => 'أرباح يناير 2025 معتمدة',
                    'body'    => 'تم اعتماد توزيع الأرباح لشهر يناير 2025.',
                    'is_read' => true,
                ],
                [
                    'type'    => 'monthly_profit_approved',
                    'title'   => 'أرباح فبراير 2025 معتمدة',
                    'body'    => 'تم اعتماد توزيع الأرباح لشهر فبراير 2025.',
                    'is_read' => false,
                ],
                [
                    'type'    => 'monthly_profit_approved',
                    'title'   => 'أرباح مارس 2025 معتمدة',
                    'body'    => 'تم اعتماد توزيع الأرباح لشهر مارس 2025.',
                    'is_read' => false,
                ],
                [
                    'type'    => 'settlement_approved',
                    'title'   => 'تسوية 2025 معتمدة ومدفوعة',
                    'body'    => 'تم اعتماد التسوية السنوية لعام 2025 وصرف المستحقات.',
                    'is_read' => false,
                ],
                [
                    'type'    => 'system',
                    'title'   => 'مرحباً بك في منصة ORCA MED Partners',
                    'body'    => 'يمكنك الآن متابعة استثماراتك وأرباحك من خلال لوحة التحكم.',
                    'is_read' => true,
                ],
            ];

            foreach ($notifs as $n) {
                Notification::query()->create([
                    'participant_id'      => $participant->id,
                    'type'                => $n['type'],
                    'title'               => $n['title'],
                    'body'                => $n['body'],
                    'is_read'             => $n['is_read'],
                    'metadata'            => null,
                    'created_by_admin_id' => $superAdmin?->id,
                ]);
            }
        }
    }
}
