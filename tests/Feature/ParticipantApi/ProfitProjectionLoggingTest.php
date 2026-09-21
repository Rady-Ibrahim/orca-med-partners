<?php

declare(strict_types=1);

namespace Tests\Feature\ParticipantApi;

use App\Models\Participant;
use App\Models\ProfitProjectionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ProfitProjectionLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_projection_records_log_for_guest(): void
    {
        $this->postJson('/api/v1/partner/profit-projection', [
            'amount' => '100000.00',
            'period_type' => 'annual',
            'period_value' => 1,
            'is_compounded' => false,
        ])->assertOk();

        $this->assertDatabaseHas('profit_projection_logs', [
            'participant_id' => null,
            'amount' => '100000.00',
            'period_type' => 'annual',
            'period_value' => 1,
            'is_compounded' => 0,
            'expected_net_profit' => '21600.00',
            'expected_total_balance' => '121600.00',
        ]);

        $log = ProfitProjectionLog::query()->first();
        self::assertNotNull($log);
        self::assertSame('annual', $log->period_type);
        self::assertFalse($log->is_compounded);
        self::assertNotNull($log->created_at);
    }

    public function test_projection_records_log_for_authenticated_participant(): void
    {
        $participant = Participant::factory()->create([
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $token = $participant->createToken('participant-api', ['*'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/partner/profit-projection', [
                'amount' => '1000.00',
                'period_type' => 'month',
                'period_value' => 1,
            ])
            ->assertOk();

        $log = ProfitProjectionLog::query()->first();
        self::assertNotNull($log);
        self::assertSame($participant->id, $log->participant_id);
        self::assertSame('18.00', $log->expected_net_profit);
        self::assertSame('1018.00', $log->expected_total_balance);
    }

    public function test_projection_records_compounded_flag_and_multi_year_values(): void
    {
        $this->postJson('/api/v1/partner/profit-projection', [
            'amount' => '1000000.00',
            'period_type' => 'years',
            'period_value' => 3,
            'is_compounded' => true,
        ])->assertOk();

        $log = ProfitProjectionLog::query()->first();
        self::assertNotNull($log);
        self::assertTrue($log->is_compounded);
        self::assertSame('831513.43', $log->expected_net_profit);
        self::assertSame('1831513.43', $log->expected_total_balance);
    }

    public function test_invalid_projection_does_not_record_log(): void
    {
        $this->postJson('/api/v1/partner/profit-projection', [
            'amount' => '50',
            'period_type' => 'month',
            'period_value' => 1,
        ])->assertStatus(422);

        self::assertSame(0, ProfitProjectionLog::query()->count());
    }
}
