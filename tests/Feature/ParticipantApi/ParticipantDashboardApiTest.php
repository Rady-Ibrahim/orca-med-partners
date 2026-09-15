<?php

declare(strict_types=1);

namespace Tests\Feature\ParticipantApi;

use App\Models\CapitalSnapshot;
use App\Models\Investment;
use App\Models\Notification;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ParticipantDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_read_own_dashboard_contract_without_secrets(): void
    {
        $participant = Participant::factory()->create([
            'username' => 'phase8-owner',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        Investment::query()->create([
            'participant_id' => $participant->id,
            'amount' => '1000.00',
            'invested_at' => '2026-09-08',
            'status' => 'active',
        ]);
        Notification::query()->create([
            'participant_id' => $participant->id,
            'type' => 'investment_update',
            'title' => 'تحديث',
            'body' => 'تم التحديث',
            'metadata' => ['investment_id' => 1],
        ]);

        $token = $participant->createToken('participant-api', ['*'])->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $token];

        $profile = $this->withHeaders($headers)->getJson('/api/me');
        $profile->assertOk()->assertJsonPath('data.id', $participant->id)->assertJsonMissing(['password', 'access_token', 'refresh_token']);

        foreach (['investment', 'capital', 'profits', 'funds', 'depreciation', 'settlements', 'notifications'] as $resource) {
            $this->withHeaders($headers)->getJson('/api/me/' . $resource)->assertOk()->assertJsonPath('success', true);
        }

        $this->withHeaders($headers)->getJson('/api/me/profits?year=2026')->assertOk();
        $this->withHeaders($headers)->getJson('/api/me/notifications?participant_id=999999&read=false')->assertOk()->assertJsonPath('data.data.0.id', Notification::query()->first()->id);
    }

    public function test_participant_collection_scope_never_returns_another_participants_data(): void
    {
        $owner = Participant::factory()->create(['password' => Hash::make('secret123'), 'status' => 'active']);
        $other = Participant::factory()->create(['password' => Hash::make('secret123'), 'status' => 'active']);
        Investment::query()->create(['participant_id' => $other->id, 'amount' => '9000.00', 'invested_at' => '2026-09-08', 'status' => 'active']);
        Notification::query()->create(['participant_id' => $other->id, 'type' => 'important', 'title' => 'Private', 'body' => 'Private data']);

        $token = $owner->createToken('participant-api', ['*'])->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/me/investment');
        $response->assertOk()->assertJsonMissing(['9000.00', 'Private']);

        $notifications = $this->withToken($token)->getJson('/api/me/notifications');
        $notifications->assertOk()->assertJsonMissing(['Private']);
    }

    public function test_participant_cannot_mutate_financial_dashboard_resources(): void
    {
        $participant = Participant::factory()->create(['password' => Hash::make('secret123'), 'status' => 'active']);
        $token = $participant->createToken('participant-api', ['*'])->plainTextToken;

        $this->withToken($token)->postJson('/api/me/investment')->assertStatus(405);
        $this->withToken($token)->postJson('/api/me/profits')->assertStatus(405);
        $this->withToken($token)->postJson('/api/me/settlements')->assertStatus(405);
        $this->withToken($token)->postJson('/api/me/funds')->assertStatus(405);
        $this->withToken($token)->postJson('/api/me/depreciation')->assertStatus(405);
    }

    public function test_capital_growth_movement_is_continuous_across_pages(): void
    {
        $participant = Participant::factory()->create(['password' => Hash::make('secret123'), 'status' => 'active']);
        $token = $participant->createToken('participant-api', ['*'])->plainTextToken;

        for ($i = 1; $i <= 25; $i++) {
            CapitalSnapshot::query()->create([
                'snapshot_date' => sprintf('2026-01-%02d', $i),
                'year' => 2026,
                'month' => 1,
                'total_capital' => (string) ($i * 100),
                'status' => 'final',
            ]);
        }

        $page1 = $this->withToken($token)->getJson('/api/me/capital/growth?page=1')->assertOk();
        self::assertCount(20, $page1->json('data.data'));
        self::assertSame('2000.00', $page1->json('data.data.19.snapshot_capital'));
        self::assertSame('100.00', $page1->json('data.data.19.movement'));

        $page2 = $this->withToken($token)->getJson('/api/me/capital/growth?page=2')->assertOk();
        self::assertCount(5, $page2->json('data.data'));
        self::assertSame('2100.00', $page2->json('data.data.0.snapshot_capital'));
        self::assertSame('100.00', $page2->json('data.data.0.movement'));
    }

    public function test_unauthenticated_participant_dashboard_request_is_rejected(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/me/profits')->assertUnauthorized();
    }
}
