<?php

declare(strict_types=1);

namespace Tests\Feature\ParticipantApi;

use App\Domain\Financial\ValueObjects\FinancialRoundingService;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class RoiSimulationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_roi_simulation_returns_compound_interest_schedule_as_money_strings(): void
    {
        $participant = $this->createParticipant();

        $response = $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', [
                'base_capital' => '1000000.00',
                'months' => 5,
                'expected_monthly_rate' => '0.15',
            ])
            ->assertOk();

        $data = $response->json('data');

        self::assertSame('1000000.00', $data['base_capital']);
        self::assertSame(5, $data['months']);
        self::assertSame('0.1500', $data['expected_monthly_rate']);
        self::assertCount(5, $data['schedule']);
        self::assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', $data['projected_capital']);
        self::assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', $data['projected_profit']);
        self::assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', $data['average_monthly_profit']);
        self::assertArrayHasKey('disclaimer', $data);

        $last = last($data['schedule']);
        self::assertSame($data['projected_capital'], $last['capital']);
        self::assertSame($data['average_monthly_profit'], (new FinancialRoundingService())->money(bcdiv($data['projected_profit'], '5', 10)));
        self::assertSame(
            $data['projected_profit'],
            (new FinancialRoundingService())->money(bcsub($data['projected_capital'], $data['base_capital'], 2))
        );

        $rounding = new FinancialRoundingService();
        $carry = '1000000.00';
        foreach ($data['schedule'] as $row) {
            self::assertArrayHasKey('month', $row);

            $profit = bcmul($carry, '0.15', 10);
            $closing = bcadd($carry, $profit, 10);
            self::assertSame($rounding->money($profit), $row['profit']);
            self::assertSame($rounding->money($closing), $row['capital']);
            $carry = $closing;
        }
    }

    public function test_roi_simulation_rejects_invalid_inputs(): void
    {
        $participant = $this->createParticipant();

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '0', 'months' => 5, 'expected_monthly_rate' => '0.15'])
            ->assertStatus(422);

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '100.00', 'months' => 0, 'expected_monthly_rate' => '0.15'])
            ->assertStatus(422);

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '100.00', 'months' => 361, 'expected_monthly_rate' => '0.15'])
            ->assertStatus(422);

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '100.00', 'months' => 5, 'expected_monthly_rate' => '2'])
            ->assertStatus(422);
    }

    public function test_roi_simulation_requires_authentication(): void
    {
        $this->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '100.00', 'months' => 1, 'expected_monthly_rate' => '0.10'])->assertUnauthorized();
    }

    private function createParticipant(): Participant
    {
        return Participant::factory()->create(['password' => Hash::make('secret123'), 'status' => 'active']);
    }

    private function token(Participant $participant): string
    {
        return $participant->createToken('participant-api', ['*'])->plainTextToken;
    }
}