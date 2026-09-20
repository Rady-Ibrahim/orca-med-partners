<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\AppSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    /**
     * Default application settings seeded on first install.
     *
     * Stores the ROI growth-rate percentages used by the investment calculator
     * as decimal ratios, plus platform / session / login defaults the admin can
     * edit from the Settings page.
     */
    public function run(): void
    {
        $defaults = [
            'company_name' => 'ORCA MED Partners',
            'currency_code' => 'SAR',
            'currency_symbol' => 'ر.س',
            'date_format' => 'Y-m-d',
            'roi_base_annual_rate' => '0.216',
            'roi_growth_bonuses' => ['0.005', '0.010', '0.0075', '0.005'],
            'session_lifetime_minutes' => '120',
            'login_throttle_attempts' => '10',
        ];

        $firstAdmin = Admin::query()->orderBy('id')->value('id');

        DB::transaction(function () use ($defaults, $firstAdmin): void {
            foreach ($defaults as $key => $value) {
                AppSetting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'description' => $this->description($key),
                        'updated_by_admin_id' => $firstAdmin,
                    ],
                );
            }
        });
    }

    private function description(string $key): ?string
    {
        return [
            'company_name' => 'اسم المنصة الظاهر في الواجهة',
            'currency_code' => 'كود العملة المعروض',
            'currency_symbol' => 'رمز العملة المعروض بجانب القيم المالية',
            'date_format' => 'تنسيق التاريخ المستخدم في العرض',
            'roi_base_annual_rate' => 'معدل العائد السنوي الأساسي الصافي (نسبة عشرية)',
            'roi_growth_bonuses' => 'بونص النمو السنوي (نسب عشرية) — السنة 1، 2، 3، ثم السنوات التالية',
            'session_lifetime_minutes' => 'مدة انتهاء جلسة الويب بالدقائق',
            'login_throttle_attempts' => 'الحد الأقصى لمحاولات تسجيل الدخول',
        ][$key] ?? null;
    }
}
