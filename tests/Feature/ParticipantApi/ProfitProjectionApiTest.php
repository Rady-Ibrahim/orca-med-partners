<?php

declare(strict_types=1);

namespace Tests\Feature\ParticipantApi;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class ProfitProjectionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_projection_is_simple_at_1_8_percent(): void
    {
        $data = $this->project(['amount' => '100000.00', 'period_type' => 'month', 'period_value' => 1])
            ->assertOk()
            ->json('data');

        self::assertSame('100000.00', $data['initial_amount']);
        self::assertSame('month', $data['period_details']['period_type']);
        self::assertSame(1, $data['period_details']['total_months']);
        self::assertSame('simple', $data['calculation_mode']);
        self::assertSame('0.0180', $data['effective_net_rate']);
        self::assertSame('1800.00', $data['expected_net_profit']);
        self::assertSame('101800.00', $data['expected_total_balance']);
    }

    public function test_multiple_months_accumulate_linearly(): void
    {
        $data = $this->project(['amount' => '100000.00', 'period_type' => 'month', 'period_value' => 2])
            ->assertOk()
            ->json('data');

        self::assertSame(2, $data['period_details']['total_months']);
        self::assertSame('0.0360', $data['effective_net_rate']);
        self::assertSame('3600.00', $data['expected_net_profit']);
        self::assertSame('103600.00', $data['expected_total_balance']);
    }

    public function test_quarter_projection_uses_5_4_percent(): void
    {
        $data = $this->project(['amount' => '100000.00', 'period_type' => 'quarter', 'period_value' => 1])
            ->assertOk()
            ->json('data');

        self::assertSame(3, $data['period_details']['total_months']);
        self::assertSame('0.0540', $data['effective_net_rate']);
        self::assertSame('5400.00', $data['expected_net_profit']);
        self::assertSame('105400.00', $data['expected_total_balance']);
    }

    public function test_semi_annual_projection_uses_10_8_percent(): void
    {
        $data = $this->project(['amount' => '100000.00', 'period_type' => 'semi_annual', 'period_value' => 1])
            ->assertOk()
            ->json('data');

        self::assertSame(6, $data['period_details']['total_months']);
        self::assertSame('0.1080', $data['effective_net_rate']);
        self::assertSame('10800.00', $data['expected_net_profit']);
        self::assertSame('110800.00', $data['expected_total_balance']);
    }

    public function test_annual_simple_projection_uses_21_6_percent_by_default(): void
    {
        $data = $this->project(['amount' => '100000.00', 'period_type' => 'annual', 'period_value' => 1])
            ->assertOk()
            ->json('data');

        self::assertSame(12, $data['period_details']['total_months']);
        self::assertSame('simple', $data['calculation_mode']);
        self::assertSame('0.2160', $data['effective_net_rate']);
        self::assertSame('21600.00', $data['expected_net_profit']);
        self::assertSame('121600.00', $data['expected_total_balance']);
    }

    public function test_annual_compounded_applies_first_growth_bonus(): void
    {
        $data = $this->project([
            'amount' => '100000.00',
            'period_type' => 'annual',
            'period_value' => 1,
            'is_compounded' => true,
        ])
            ->assertOk()
            ->json('data');

        self::assertSame('compounded', $data['calculation_mode']);
        self::assertSame('0.2160', $data['effective_net_rate']);
        self::assertSame('22100.00', $data['expected_net_profit']);
        self::assertSame('122100.00', $data['expected_total_balance']);
    }

    public function test_multi_year_simple_projection_scales_flat_rate(): void
    {
        $data = $this->project(['amount' => '100000.00', 'period_type' => 'years', 'period_value' => 3])
            ->assertOk()
            ->json('data');

        self::assertSame(36, $data['period_details']['total_months']);
        self::assertSame('simple', $data['calculation_mode']);
        self::assertSame('0.6480', $data['effective_net_rate']);
        self::assertSame('64800.00', $data['expected_net_profit']);
        self::assertSame('164800.00', $data['expected_total_balance']);
    }

    public function test_multi_year_compounded_projection_uses_roi_ladder(): void
    {
        $data = $this->project([
            'amount' => '1000000.00',
            'period_type' => 'years',
            'period_value' => 3,
            'is_compounded' => true,
        ])
            ->assertOk()
            ->json('data');

        self::assertSame('compounded', $data['calculation_mode']);
        self::assertSame('831513.43', $data['expected_net_profit']);
        self::assertSame('1831513.43', $data['expected_total_balance']);
    }

    public function test_sub_annual_periods_ignore_compounding(): void
    {
        $data = $this->project([
            'amount' => '100000.00',
            'period_type' => 'month',
            'period_value' => 1,
            'is_compounded' => true,
        ])
            ->assertOk()
            ->json('data');

        self::assertSame('simple', $data['calculation_mode']);
        self::assertSame('1800.00', $data['expected_net_profit']);
    }

    public function test_response_exposes_gross_breakdown_info(): void
    {
        $data = $this->project(['amount' => '100000.00', 'period_type' => 'annual', 'period_value' => 1])
            ->assertOk()
            ->json('data');

        $info = $data['gross_breakdown_info'];
        self::assertSame('0.6500', $info['participant_share_rate']);
        self::assertSame('0.3500', $info['company_deductions_rate']);
        self::assertSame('0.2500', $info['deduction_breakdown']['management_fee_rate']);
        self::assertSame('0.0500', $info['deduction_breakdown']['depreciation_fund_rate']);
        self::assertSame('0.0250', $info['deduction_breakdown']['growth_fund_rate']);
        self::assertSame('0.0250', $info['deduction_breakdown']['incentive_fund_rate']);
        self::assertSame('0.0180', $info['net_monthly_rate']);
        self::assertSame('0.2160', $info['net_annual_rate']);
        self::assertArrayHasKey('note', $info);
        self::assertArrayHasKey('disclaimer', $data);
    }

    public function test_projection_is_available_without_authentication(): void
    {
        $this->postJson('/api/v1/partner/profit-projection', [
            'amount' => '100000.00',
            'period_type' => 'annual',
            'period_value' => 1,
        ])->assertOk();
    }

    public function test_projection_rejects_invalid_inputs(): void
    {
        $this->project(['amount' => '999', 'period_type' => 'month', 'period_value' => 1])->assertStatus(422);
        $this->project(['amount' => 'abc', 'period_type' => 'month', 'period_value' => 1])->assertStatus(422);
        $this->project(['amount' => '100000.00', 'period_type' => 'decade', 'period_value' => 1])->assertStatus(422);
        $this->project(['amount' => '100000.00', 'period_value' => 1])->assertStatus(422);
        $this->project(['amount' => '100000.00', 'period_type' => 'month', 'period_value' => 0])->assertStatus(422);
        $this->project([
            'amount' => '100000.00',
            'period_type' => 'years',
            'period_value' => 51,
        ])->assertStatus(422);
        $this->project([
            'amount' => '100000.00',
            'period_type' => 'annual',
            'period_value' => 1,
            'is_compounded' => 'yes',
        ])->assertStatus(422);
    }

    private function project(array $payload): TestResponse
    {
        return $this->postJson('/api/v1/partner/profit-projection', $payload);
    }
}
