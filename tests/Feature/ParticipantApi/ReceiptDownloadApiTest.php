<?php

declare(strict_types=1);

namespace Tests\Feature\ParticipantApi;

use App\Models\Participant;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Models\SettlementPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ReceiptDownloadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_downloads_own_payment_receipt_as_private_streamed_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/receipt-1.png', 'FAKE_PNG_BYTES');

        $participant = $this->createParticipant();
        [$settlement, $payment] = $this->createPaymentWithReceipt($participant->id, 'receipts/receipt-1.png', 'transfer-receipt.png', 'image/png');

        $this->withToken($this->token($participant))
            ->get("/api/v1/me/settlements/{$settlement->id}/payments/{$payment->id}/receipt")
            ->assertOk()
            ->assertHeader('content-type', 'image/png')
            ->assertHeader('content-disposition', 'inline; filename="transfer-receipt.png"')
            ->assertStreamedContent('FAKE_PNG_BYTES');

        self::assertDatabaseHas('audit_logs', ['action' => 'document_downloaded', 'auditable_id' => $payment->id]);
    }

    public function test_participant_cannot_download_another_participants_receipt_and_event_is_logged(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/secret.png', 'SECRET');

        $owner = $this->createParticipant();
        $attacker = $this->createParticipant();
        [$settlement, $payment] = $this->createPaymentWithReceipt($owner->id, 'receipts/secret.png');

        $this->withToken($this->token($attacker))
            ->get("/api/v1/me/settlements/{$settlement->id}/payments/{$payment->id}/receipt")
            ->assertForbidden();

        self::assertDatabaseHas('audit_logs', [
            'action' => 'authorization_denied',
            'auditable_type' => 'settlement_payment',
            'actor_id' => $attacker->id,
        ]);
    }

    public function test_payment_not_part_of_requested_settlement_is_rejected(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/oreo.png', 'DATA');

        $participant = $this->createParticipant();
        [$settlement, $payment] = $this->createPaymentWithReceipt($participant->id, 'receipts/oreo.png');

        $foreignSettlement = Settlement::query()->create([
            'year' => 2025,
            'version' => 1,
            'status' => 'approved',
            'total_distributed_amount' => '0.00',
            'participant_profit_share' => '0.00',
            'participant_fund_share' => '0.00',
            'net_payable' => '0.00',
            'amount_due' => '0.00',
            'paid_amount' => '0.00',
        ]);
        SettlementItem::query()->create([
            'settlement_id' => $foreignSettlement->id,
            'participant_id' => $participant->id,
            'profit_share' => '0.00',
            'fund_share' => '0.00',
            'net_payable' => '0.00',
            'payment_status' => 'pending',
            'paid_amount' => '0.00',
        ]);

        $this->withToken($this->token($participant))
            ->get("/api/v1/me/settlements/{$foreignSettlement->id}/payments/{$payment->id}/receipt")
            ->assertForbidden();
    }

    public function test_missing_receipt_returns_404(): void
    {
        Storage::fake('local');

        $participant = $this->createParticipant();
        $settlement = $this->createSettlement();
        SettlementItem::query()->create([
            'settlement_id' => $settlement->id,
            'participant_id' => $participant->id,
            'profit_share' => '0.00',
            'fund_share' => '0.00',
            'net_payable' => '0.00',
            'payment_status' => 'pending',
            'paid_amount' => '0.00',
        ]);
        $payment = SettlementPayment::query()->create([
            'settlement_id' => $settlement->id,
            'amount' => '100.00',
            'paid_at' => now(),
            'payment_method' => 'bank_transfer',
            'reference' => 'TRX-1',
        ]);

        $this->withToken($this->token($participant))
            ->get("/api/v1/me/settlements/{$settlement->id}/payments/{$payment->id}/receipt")
            ->assertNotFound();
    }

    public function test_receipt_download_requires_authentication(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/auth-test.png', 'DATA');

        $participant = $this->createParticipant();
        [$settlement, $payment] = $this->createPaymentWithReceipt($participant->id, 'receipts/auth-test.png');

        $this->get("/api/v1/me/settlements/{$settlement->id}/payments/{$payment->id}/receipt")
            ->assertUnauthorized();
    }

    private function createPaymentWithReceipt(int $participantId, string $path, string $filename = 'receipt.png', string $mime = 'image/png'): array
    {
        $settlement = $this->createSettlement();

        SettlementItem::query()->create([
            'settlement_id' => $settlement->id,
            'participant_id' => $participantId,
            'profit_share' => '100.00',
            'fund_share' => '0.00',
            'net_payable' => '100.00',
            'payment_status' => 'paid',
            'paid_amount' => '100.00',
        ]);

        $payment = SettlementPayment::query()->create([
            'settlement_id' => $settlement->id,
            'amount' => '100.00',
            'paid_at' => now(),
            'payment_method' => 'bank_transfer',
            'reference' => 'TRX-' . uniqid(),
        ]);

        $payment->receipt()->create([
            'file_path' => $path,
            'original_filename' => $filename,
            'mime' => $mime,
        ]);

        return [$settlement, $payment];
    }

    private function createSettlement(): Settlement
    {
        return Settlement::query()->create([
            'year' => 2026,
            'version' => 1,
            'status' => 'approved',
            'total_distributed_amount' => '100.00',
            'participant_profit_share' => '100.00',
            'participant_fund_share' => '0.00',
            'net_payable' => '100.00',
            'amount_due' => '100.00',
            'paid_amount' => '100.00',
        ]);
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