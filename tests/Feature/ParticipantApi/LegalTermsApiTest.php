<?php

declare(strict_types=1);

namespace Tests\Feature\ParticipantApi;

use Tests\TestCase;

final class LegalTermsApiTest extends TestCase
{
    public function test_terms_are_publicly_available_without_authentication(): void
    {
        $this->getJson('/api/v1/legal/terms')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'title',
                    'updated_at',
                    'sections' => [
                        '*' => ['heading', 'body'],
                    ],
                ],
            ]);
    }

    public function test_terms_contain_investment_and_security_disclosures(): void
    {
        $response = $this->getJson('/api/v1/legal/terms')->assertOk();

        $headings = array_column($response->json('data.sections'), 'heading');
        self::assertNotEmpty($headings);
        self::assertStringContainsString('أمان الحساب', implode(' ', $headings));
        self::assertStringContainsString('إخلاء المسؤولية', implode(' ', $headings));
    }
}