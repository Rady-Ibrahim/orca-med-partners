<?php

declare(strict_types=1);

namespace Tests\Feature\ParticipantApi;

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

    public function test_unauthenticated_participant_dashboard_request_is_rejected(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/me/profits')->assertUnauthorized();
    }
}
