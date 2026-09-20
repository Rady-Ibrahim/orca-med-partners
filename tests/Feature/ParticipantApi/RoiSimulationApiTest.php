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

    public function test_roi_simulation_returns_compounded_annual_schedule_matching_specification(): void
    {
        $participant = $this->createParticipant();

        $response = $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', [
                'base_capital' => '1000000.00',
                'years' => 3,
                'is_compounded' => true,
            ])
            ->assertOk();

        $data = $response->json('data');

        self::assertSame('1000000.00', $data['base_capital']);
        self::assertSame(3, $data['years']);
        self::assertTrue($data['is_compounded']);
        self::assertSame('0.2160', $data['base_annual_rate']);
        self::assertSame(['0.0050', '0.0100', '0.0075'], $data['growth_bonus']);
        self::assertSame('1831513.43', $data['projected_capital']);
        self::assertSame('831513.43', $data['projected_profit']);
        self::assertSame('277171.14', $data['average_annual_profit']);
        self::assertArrayHasKey('disclaimer', $data);

        self::assertSame([
            ['year' => 1, 'expected_annual_rate' => '0.2210', 'profit' => '221000.00', 'capital' => '1221000.00'],
            ['year' => 2, 'expected_annual_rate' => '0.2260', 'profit' => '275946.00', 'capital' => '1496946.00'],
            ['year' => 3, 'expected_annual_rate' => '0.2235', 'profit' => '334567.43', 'capital' => '1831513.43'],
        ], $data['schedule']);

        $last = last($data['schedule']);
        self::assertSame($data['projected_capital'], $last['capital']);
        self::assertSame(
            $data['projected_profit'],
            (new FinancialRoundingService)->money(bcsub($data['projected_capital'], $data['base_capital'], 2))
        );

        $schedule = last($data['schedule'])['capital'];
        self::assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', $schedule);
    }

    public function test_roi_simulation_accepts_custom_rate_and_bonus_ladder(): void
    {
        $participant = $this->createParticipant();

        $response = $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', [
                'base_capital' => '1000.00',
                'years' => 2,
                'is_compounded' => true,
                'base_annual_rate' => '0.10',
                'growth_bonus' => ['0.05', '0.075'],
            ])
            ->assertOk();

        $data = $response->json('data');

        self::assertSame('0.1000', $data['base_annual_rate']);
        self::assertSame(['0.0500', '0.0750'], $data['growth_bonus']);

        $rounding = new FinancialRoundingService;
        $carry = '1000.00';
        $rates = ['0.1500', '0.1750'];
        foreach ($data['schedule'] as $index => $row) {
            $profit = $rounding->money(bcmul($carry, $rates[$index], 10));
            $closing = $rounding->money(bcadd($carry, $profit, 10));
            self::assertSame($rates[$index], $row['expected_annual_rate']);
            self::assertSame($profit, $row['profit']);
            self::assertSame($closing, $row['capital']);
            $carry = $closing;
        }
    }

    public function test_roi_simulation_rejects_invalid_inputs(): void
    {
        $participant = $this->createParticipant();

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '0', 'years' => 5, 'is_compounded' => true])
            ->assertStatus(422);

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '100.00', 'years' => 0, 'is_compounded' => true])
            ->assertStatus(422);

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '100.00', 'years' => 51, 'is_compounded' => true])
            ->assertStatus(422);

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '100.00', 'years' => 5, 'is_compounded' => 'yes'])
            ->assertStatus(422);

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '100.00', 'years' => 5, 'is_compounded' => true, 'base_annual_rate' => '2'])
            ->assertStatus(422);

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '100.00', 'years' => 5, 'is_compounded' => true, 'growth_bonus' => ['abc']])
            ->assertStatus(422);
    }

    public function test_roi_simulation_requires_authentication(): void
    {
        $this->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '100.00', 'years' => 1, 'is_compounded' => true])
            ->assertUnauthorized();
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
