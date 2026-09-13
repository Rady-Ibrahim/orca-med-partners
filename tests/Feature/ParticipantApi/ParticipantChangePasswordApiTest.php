<?php

declare(strict_types=1);

namespace Tests\Feature\ParticipantApi;

use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ParticipantChangePasswordApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_v1_route_updates_password_and_revokes_tokens(): void
    {
        $participant = $this->createParticipant();
        $token = $this->token($participant);

        $this->withToken($token)
            ->postJson('/api/v1/auth/participant/change-password', [
                'current_password' => 'secret123',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ])
            ->assertOk();

        self::assertTrue(Hash::check('new-secret-123', $participant->fresh()->password));
        self::assertCount(0, $participant->tokens()->get());

        $this->withToken($token)->getJson('/api/me')->assertForbidden();
    }

    public function test_change_password_rejects_incorrect_current_password(): void
    {
        $participant = $this->createParticipant();

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/auth/participant/change-password', [
                'current_password' => 'wrong-current',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ])
            ->assertStatus(422);

        self::assertTrue(Hash::check('secret123', $participant->fresh()->password));
    }

    public function test_change_password_rejects_weak_passwords(): void
    {
        $participant = $this->createParticipant();

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/auth/participant/change-password', [
                'current_password' => 'secret123',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertStatus(422);
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