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
                'years' => 5,
                'expected_annual_rate' => '0.15',
            ])
            ->assertOk();

        $data = $response->json('data');

        self::assertSame('1000000.00', $data['base_capital']);
        self::assertSame(5, $data['years']);
        self::assertSame('0.1500', $data['expected_annual_rate']);
        self::assertCount(5, $data['schedule']);
        self::assertMatchesRegularExpression('/^\d+\.\d{2}$/', $data['projected_capital']);
        self::assertMatchesRegularExpression('/^\d+\.\d{2}$/', $data['projected_profit']);
        self::assertArrayHasKey('disclaimer', $data);

        $last = last($data['schedule']);
        self::assertSame($data['projected_capital'], $last['closing_capital']);
        self::assertSame($data['projected_profit'], sprintf('%0.2f', (float) $data['projected_capital'] - (float) $data['base_capital']));

        $expected = '1000000.00';
        $rounding = new FinancialRoundingService();
        foreach ($data['schedule'] as $row) {
            self::assertSame($expected, $row['opening_capital']);

            $profit = bcmul($expected, '0.15', 10);
            self::assertSame($rounding->money($profit), $row['profit']);

            $closing = $rounding->money(bcadd($expected, $profit, 10));
            self::assertSame($closing, $row['closing_capital']);
            $expected = $closing;
        }
    }

    public function test_roi_simulation_rejects_invalid_inputs(): void
    {
        $participant = $this->createParticipant();

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '0', 'years' => 5, 'expected_annual_rate' => '0.15'])
            ->assertStatus(422);

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '100.00', 'years' => 0, 'expected_annual_rate' => '0.15'])
            ->assertStatus(422);

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '100.00', 'years' => 5, 'expected_annual_rate' => '2'])
            ->assertStatus(422);
    }

    public function test_roi_simulation_requires_authentication(): void
    {
        $this->postJson('/api/v1/me/tools/roi-simulation', ['base_capital' => '100.00', 'years' => 1, 'expected_annual_rate' => '0.10'])->assertUnauthorized();
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