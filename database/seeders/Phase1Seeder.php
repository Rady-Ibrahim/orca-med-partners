<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\DistributionRule;
use App\Models\Fund;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class Phase1Seeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::query()->firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'System Administrator',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'status' => 'active',
            ]
        );

        DistributionRule::query()->firstOrCreate(
            ['effective_from' => '2025-01-01'],
            [
                'effective_to' => null,
                'management_fee_rate' => 0.2500,
                'depreciation_fund_rate' => 0.0500,
                'growth_fund_rate' => 0.0250,
                'incentive_fund_rate' => 0.0250,
                'distributed_share_rate' => 0.6500,
                'status' => 'active',
                'is_default' => true,
                'notes' => 'Initial approved configuration.',
                'created_by_admin_id' => $admin->id,
                'approved_by_admin_id' => $admin->id,
                'approved_at' => now(),
            ]
        );

        $funds = [
            ['code' => 'growth_fund', 'name' => 'Growth Fund'],
            ['code' => 'incentive_fund', 'name' => 'Participant Incentive Fund'],
            ['code' => 'depreciation_fund', 'name' => 'Depreciation Fund'],
        ];

        foreach ($funds as $fund) {
            Fund::query()->firstOrCreate(
                ['code' => $fund['code']],
                [
                    'name' => $fund['name'],
                    'current_balance' => 0,
                    'status' => 'active',
                    'description' => 'Seeded fund.',
                    'created_by_admin_id' => $admin->id,
                ]
            );
        }
    }
}
