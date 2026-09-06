<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase1SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_financial_tables_exist(): void
    {
        $tables = [
            'admins',
            'participants',
            'investments',
            'capital_snapshots',
            'capital_snapshot_items',
            'distribution_rules',
            'monthly_profits',
            'funds',
            'fund_transactions',
            'depreciation_notes',
            'participant_profit_allocations',
            'participant_fund_allocations',
            'settlements',
            'settlement_items',
            'notifications',
            'audit_logs',
            'app_settings',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }
    }

    public function test_financial_tables_have_core_decimal_and_revision_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('distribution_rules', ['management_fee_rate', 'depreciation_fund_rate', 'growth_fund_rate', 'incentive_fund_rate', 'distributed_share_rate']));
        $this->assertTrue(Schema::hasColumns('monthly_profits', ['year', 'month', 'version', 'status', 'capital_snapshot_id', 'distribution_rule_id', 'gross_profit', 'rounding_delta_adjustment', 'parent_id']));
        $this->assertTrue(Schema::hasColumns('funds', ['code', 'name', 'current_balance']));
        $this->assertTrue(Schema::hasColumns('settlements', ['year', 'version', 'status', 'total_distributed_amount', 'net_payable', 'amount_due', 'paid_amount', 'parent_id']));
    }

    public function test_core_constraints_exist_for_revision_and_snapshot_relationships(): void
    {
        $this->assertTrue(Schema::hasColumn('monthly_profits', 'parent_id'));
        $this->assertTrue(Schema::hasColumn('settlements', 'parent_id'));
        $this->assertTrue(Schema::hasColumn('capital_snapshot_items', 'capital_snapshot_id'));
        $this->assertTrue(Schema::hasColumn('capital_snapshot_items', 'participant_id'));
        $this->assertTrue(Schema::hasColumn('fund_transactions', 'fund_id'));
    }
}
