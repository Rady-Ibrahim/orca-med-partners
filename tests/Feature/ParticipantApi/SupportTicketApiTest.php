<?php

declare(strict_types=1);

namespace Tests\Feature\ParticipantApi;

use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class SupportTicketApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_submit_support_ticket(): void
    {
        $participant = $this->createParticipant();

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/support/ticket', [
                'subject' => 'استفسار عن كشف التسوية',
                'message' => 'أحتاج توضيحاً حول حساب حصة الصندوق في كشف عام 2026.',
                'category' => 'financial',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');

        self::assertDatabaseHas('support_tickets', [
            'participant_id' => $participant->id,
            'category' => 'financial',
            'status' => 'open',
        ]);
    }

    public function test_support_ticket_validates_input(): void
    {
        $participant = $this->createParticipant();

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/support/ticket', ['subject' => 'x', 'message' => 'short'])
            ->assertStatus(422);

        $this->withToken($this->token($participant))
            ->postJson('/api/v1/support/ticket', ['subject' => 'استفسار', 'message' => ''])
            ->assertStatus(422);
    }

    public function test_support_ticket_requires_authentication(): void
    {
        $this->postJson('/api/v1/support/ticket', ['subject' => 's', 'message' => 'm'])->assertUnauthorized();
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