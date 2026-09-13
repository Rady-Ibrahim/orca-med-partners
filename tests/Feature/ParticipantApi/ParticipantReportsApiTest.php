<?php

declare(strict_types=1);

namespace Tests\Feature\ParticipantApi;

use App\Models\Participant;
use App\Models\Settlement;
use App\Models\SettlementItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ParticipantReportsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_index_lists_types_with_participant_available_years(): void
    {
        $participant = $this->createParticipantWithSettlement();

        $response = $this->withToken($this->token($participant))
            ->getJson('/api/v1/me/reports')
            ->assertOk();

        $reports = collect($response->json('data.reports'));
        self::assertTrue($reports->contains('type', 'settlement_statement'));
        self::assertTrue($reports->contains('type', 'annual_investment_summary'));

        $statement = $reports->firstWhere('type', 'settlement_statement');
        self::assertSame([2026], $statement['available_years']);
    }

    public function test_settlement_statement_pdf_is_streamed_with_expected_headers(): void
    {
        $participant = $this->createParticipantWithSettlement();

        $this->withToken($this->token($participant))
            ->get('/api/v1/me/reports/settlement_statement/download?format=pdf&year=2026')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="settlement-statement-' . $participant->username . '-2026.pdf"');
    }

    public function test_settlement_statement_xlsx_is_downloaded(): void
    {
        $participant = $this->createParticipantWithSettlement();

        $this->withToken($this->token($participant))
            ->get('/api/v1/me/reports/settlement_statement/download?format=xlsx&year=2026')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_annual_investment_summary_pdf_and_xlsx_are_downloadable(): void
    {
        $participant = $this->createParticipant();

        $this->withToken($this->token($participant))
            ->get('/api/v1/me/reports/annual_investment_summary/download?format=pdf&year=2026')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->withToken($this->token($participant))
            ->get('/api/v1/me/reports/annual_investment_summary/download?format=xlsx&year=2026')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_report_download_requires_authentication_and_valid_format(): void
    {
        $this->getJson('/api/v1/me/reports')->assertUnauthorized();

        $participant = $this->createParticipant();
        $token = $this->token($participant);

        $this->withToken($token)
            ->getJson('/api/v1/me/reports/settlement_statement/download?format=csv&year=2026')
            ->assertStatus(422);

        $this->withToken($token)
            ->getJson('/api/v1/me/reports/unknown_type/download?format=pdf')
            ->assertNotFound();
    }

    private function createParticipant(): Participant
    {
        return Participant::factory()->create(['password' => Hash::make('secret123'), 'status' => 'active']);
    }

    private function createParticipantWithSettlement(): Participant
    {
        $participant = $this->createParticipant();

        $settlement = Settlement::query()->create([
            'year' => 2026,
            'version' => 1,
            'status' => 'approved',
            'total_distributed_amount' => '160.00',
            'participant_profit_share' => '130.00',
            'participant_fund_share' => '30.00',
            'net_payable' => '160.00',
            'amount_due' => '160.00',
            'paid_amount' => '100.00',
        ]);

        SettlementItem::query()->create([
            'settlement_id' => $settlement->id,
            'participant_id' => $participant->id,
            'profit_share' => '130.00',
            'fund_share' => '30.00',
            'net_payable' => '160.00',
            'payment_status' => 'partial',
            'paid_amount' => '100.00',
        ]);

        return $participant;
    }

    private function token(Participant $participant): string
    {
        return $participant->createToken('participant-api', ['*'])->plainTextToken;
    }
}