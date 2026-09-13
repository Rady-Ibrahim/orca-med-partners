<?php

declare(strict_types=1);

namespace Tests\Feature\ParticipantApi;

use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class SecuritySettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_2fa_status_defaults_to_disabled(): void
    {
        $participant = $this->createParticipant();

        $this->withToken($this->token($participant))
            ->getJson('/api/v1/me/settings/2fa')
            ->assertOk()
            ->assertJsonPath('data.2fa_enabled', false);
    }

    public function test_toggle_2fa_requires_correct_current_password(): void
    {
        $participant = $this->createParticipant();

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/settings/2fa/toggle', ['password' => 'wrong-password', 'enable' => true])
            ->assertStatus(422);

        self::assertFalse((bool) $participant->fresh()->two_factor_enabled);
    }

    public function test_toggle_2fa_enables_and_disables_with_audit_trail(): void
    {
        $participant = $this->createParticipant();

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/settings/2fa/toggle', ['password' => 'secret123', 'enable' => true])
            ->assertOk()
            ->assertJsonPath('data.2fa_enabled', true);

        self::assertTrue((bool) $participant->fresh()->two_factor_enabled);
        self::assertNotNull($participant->fresh()->two_factor_enabled_at);

        $this->withToken($this->token($participant))
            ->getJson('/api/v1/me/settings/2fa')
            ->assertOk()
            ->assertJsonPath('data.2fa_enabled', true);

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/me/settings/2fa/toggle', ['password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('data.2fa_enabled', false);

        self::assertDatabaseHas('audit_logs', ['action' => 'two_factor_toggled', 'actor_id' => $participant->id]);
    }

    public function test_settings_require_authentication(): void
    {
        $this->getJson('/api/v1/me/settings/2fa')->assertUnauthorized();
        $this->postJson('/api/v1/me/settings/2fa/toggle', ['password' => 'x'])->assertUnauthorized();
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