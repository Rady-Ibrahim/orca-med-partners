<?php

declare(strict_types=1);

namespace Tests\Feature\ParticipantApi;

use App\Models\Notification;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class NotificationActionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_count_returns_badge_number_for_authenticated_participant(): void
    {
        $participant = $this->createParticipant();
        Notification::query()->create(['participant_id' => $participant->id, 'type' => 'info', 'title' => 'A', 'body' => 'one', 'is_read' => false]);
        Notification::query()->create(['participant_id' => $participant->id, 'type' => 'info', 'title' => 'B', 'body' => 'two', 'is_read' => false]);
        Notification::query()->create(['participant_id' => $participant->id, 'type' => 'info', 'title' => 'C', 'body' => 'three', 'is_read' => true]);

        $this->withToken($this->token($participant))
            ->getJson('/api/v1/me/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2);
    }

    public function test_mark_read_flags_notification_and_is_idempotent(): void
    {
        $participant = $this->createParticipant();
        $notification = Notification::query()->create(['participant_id' => $participant->id, 'type' => 'info', 'title' => 'X', 'body' => 'alert', 'is_read' => false]);

        $this->withToken($this->token($participant))
            ->patchJson('/api/v1/me/notifications/' . $notification->id . '/read')
            ->assertOk()
            ->assertJsonPath('data.read', true);

        self::assertTrue((bool) $notification->fresh()->is_read);

        $this->withToken($this->token($participant))
            ->patchJson('/api/v1/me/notifications/' . $notification->id . '/read')
            ->assertOk();
    }

    public function test_mark_all_read_updates_only_own_unread_notifications(): void
    {
        $participant = $this->createParticipant();
        $other = $this->createParticipant();

        Notification::query()->create(['participant_id' => $participant->id, 'type' => 'info', 'title' => 'P1', 'body' => 'p1', 'is_read' => false]);
        Notification::query()->create(['participant_id' => $participant->id, 'type' => 'info', 'title' => 'P2', 'body' => 'p2', 'is_read' => false]);
        Notification::query()->create(['participant_id' => $other->id, 'type' => 'info', 'title' => 'O1', 'body' => 'o1', 'is_read' => false]);

        $this->withToken($this->token($participant))
            ->patchJson('/api/v1/me/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 2);

        self::assertSame(2, Notification::query()->where('participant_id', $participant->id)->where('is_read', true)->count());
        self::assertSame(1, Notification::query()->where('participant_id', $other->id)->where('is_read', false)->count());
    }

    public function test_participant_cannot_mark_another_participants_notification_and_security_event_is_logged(): void
    {
        $owner = $this->createParticipant();
        $attacker = $this->createParticipant();
        $notification = Notification::query()->create(['participant_id' => $owner->id, 'type' => 'secret', 'title' => 'Private', 'body' => 'not yours', 'is_read' => false]);

        $this->withToken($this->token($attacker))
            ->patchJson('/api/v1/me/notifications/' . $notification->id . '/read')
            ->assertForbidden();

        self::assertFalse((bool) $notification->fresh()->is_read);
        self::assertDatabaseHas('audit_logs', [
            'action' => 'notification_read',
            'auditable_type' => 'notification',
            'actor_id' => $attacker->id,
        ]);
    }

    public function test_missing_notification_returns_404(): void
    {
        $participant = $this->createParticipant();

        $this->withToken($this->token($participant))
            ->patchJson('/api/v1/me/notifications/999999/read')
            ->assertNotFound();
    }

    public function test_notification_actions_require_authentication(): void
    {
        $this->getJson('/api/v1/me/notifications/unread-count')->assertUnauthorized();
        $this->patchJson('/api/v1/me/notifications/read-all')->assertUnauthorized();
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