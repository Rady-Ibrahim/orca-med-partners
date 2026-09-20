<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminSettingsWebTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create([
            'username' => 'settings-web-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'Settings Admin',
            'role' => 'super-admin',
            'is_super_admin' => true,
        ]);
    }

    public function test_settings_page_renders_editable_form_with_current_values(): void
    {
        $admin = $this->admin();
        AppSetting::query()->create(['key' => 'company_name', 'value' => 'ORCA MED Partners']);

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('settings-form')
            ->assertSee('roi_growth_bonus_year1')
            ->assertSee('ORCA MED Partners')
            ->assertDontSee('إصدار Laravel');
    }

    public function test_settings_require_settings_manage_permission(): void
    {
        $blocked = Admin::factory()->create([
            'username' => 'settings-blocked',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'Settings Blocked',
            'role' => 'employee',
            'permissions' => ['settings.view'],
            'is_super_admin' => false,
        ]);

        $this->withSession(['web_admin_id' => $blocked->id])
            ->postJson('/admin/settings', ['company_name' => 'Hack'])
            ->assertForbidden();
    }

    public function test_admin_can_update_settings_with_percent_to_ratio_conversion(): void
    {
        $admin = $this->admin();

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/settings', [
                'company_name' => 'ORCA Partners',
                'currency_code' => 'SAR',
                'currency_symbol' => 'ر.س',
                'date_format' => 'd/m/Y',
                'roi_base_annual_rate' => '21.6',
                'roi_growth_bonus_year1' => '0.5',
                'roi_growth_bonus_year2' => '1',
                'roi_growth_bonus_year3' => '0.75',
                'roi_growth_bonus_year4' => '0.5',
                'session_lifetime_minutes' => '90',
                'login_throttle_attempts' => '15',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('app_settings', ['key' => 'company_name']);
        $this->assertDatabaseCount('app_settings', 8);
        $this->assertSame('ORCA Partners', AppSetting::query()->where('key', 'company_name')->first()?->value);
        $this->assertSame('0.216', AppSetting::query()->where('key', 'roi_base_annual_rate')->first()?->value);
        $this->assertSame('90', AppSetting::query()->where('key', 'session_lifetime_minutes')->first()?->value);

        $this->assertSame(
            ['0.005', '0.01', '0.0075', '0.005'],
            AppSetting::query()->where('key', 'roi_growth_bonuses')->first()?->value,
        );
    }

    public function test_settings_reject_invalid_percents(): void
    {
        $admin = $this->admin();

        $this->withSession(['web_admin_id' => $admin->id])
            ->postJson('/admin/settings', [
                'company_name' => 'X',
                'currency_code' => 'SAR',
                'currency_symbol' => 'ر.س',
                'date_format' => 'Y-m-d',
                'roi_base_annual_rate' => '101',
                'roi_growth_bonus_year1' => '0.5',
                'roi_growth_bonus_year2' => '1',
                'roi_growth_bonus_year3' => '0.75',
                'roi_growth_bonus_year4' => '0.5',
                'session_lifetime_minutes' => '90',
                'login_throttle_attempts' => '15',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['roi_base_annual_rate']);
    }

    public function test_update_settings_requires_web_admin_session(): void
    {
        $this->postJson('/admin/settings', ['company_name' => 'X'])->assertStatus(302);
    }
}
