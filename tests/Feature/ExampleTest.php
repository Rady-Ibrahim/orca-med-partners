<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Guests hitting the root are redirected to the admin login page.
     */
    public function test_guests_are_redirected_to_admin_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('admin.login'));
    }
}
