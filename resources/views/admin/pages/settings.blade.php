@extends('admin.pages.layout')

@section('content')

<div class="admin-page-header">
    <div>
        <p class="eyebrow">إدارة النظام</p>
        <h1>الإعدادات</h1>
    </div>
    <div class="page-actions">
        <button type="submit" form="settings-form" class="primary-button">حفظ الإعدادات</button>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form id="settings-form" data-ajax-form action="{{ route('admin.settings.update') }}" method="POST" novalidate>
    @csrf

    {{-- ── Tabs ── --}}
    <div class="settings-tabs" role="tablist">
        <button type="button" class="settings-tab active" data-tab="general"   role="tab">⚙ إعدادات عامة</button>
        <button type="button" class="settings-tab"        data-tab="financial" role="tab">◈ القواعد المالية</button>
        <button type="button" class="settings-tab"        data-tab="security"  role="tab">✦ الأمان</button>
    </div>

    {{-- ══════════════════════════════════════════════════ --}}
    {{-- PANE 1 — GENERAL                                  --}}
    {{-- ══════════════════════════════════════════════════ --}}
    <div class="settings-pane active" id="pane-general">
        <div class="settings-section">
            <div class="settings-section-title">⚙ إعدادات المنصة العامة</div>
            <div class="settings-grid">
                <div class="settings-field">
                    <label for="set_company_name">اسم المنصة</label>
                    <input id="set_company_name" name="company_name" type="text" value="{{ $settings['company_name'] }}" required maxlength="100">
                    <span class="hint">يظهر في الواجهة وعناوين الصفحات</span>
                </div>
                <div class="settings-field">
                    <label for="set_currency_code">كود العملة</label>
                    <input id="set_currency_code" name="currency_code" type="text" value="{{ $settings['currency_code'] }}" required maxlength="10" dir="ltr">
                    <span class="hint">مثال: SAR — USD — EGP</span>
                </div>
                <div class="settings-field">
                    <label for="set_currency_symbol">رمز العملة</label>
                    <input id="set_currency_symbol" name="currency_symbol" type="text" value="{{ $settings['currency_symbol'] }}" required maxlength="10">
                    <span class="hint">يُستخدم في عرض جميع القيم المالية</span>
                </div>
                <div class="settings-field">
                    <label for="set_date_format">تنسيق التاريخ</label>
                    <input id="set_date_format" name="date_format" type="text" value="{{ $settings['date_format'] }}" required maxlength="20" dir="ltr">
                    <span class="hint">مثال: Y-m-d أو d/m/Y</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════ --}}
    {{-- PANE 2 — FINANCIAL                                --}}
    {{-- ══════════════════════════════════════════════════ --}}
    <div class="settings-pane" id="pane-financial">
        <div class="settings-section">
            <div class="settings-section-title">◈ حاسبة العائد السنوي (ROI) التراكمي</div>
            <div class="settings-grid">
                <div class="settings-field">
                    <label for="set_roi_base">معدل العائد الأساسي السنوي %</label>
                    <input id="set_roi_base" name="roi_base_annual_rate" type="number" value="{{ $settings['roi_base_annual_rate_percent'] }}" required min="0" max="100" step="0.01" dir="ltr">
                    <span class="hint">صافي النسبة الأساسية قبل بونص النمو</span>
                </div>
                <div class="settings-field">
                    <label for="set_roi_bonus_1">بونص نمو — السنة الأولى %</label>
                    <input id="set_roi_bonus_1" name="roi_growth_bonus_year1" type="number" value="{{ $settings['roi_growth_bonus_year1_percent'] }}" required min="0" max="100" step="0.01" dir="ltr">
                </div>
                <div class="settings-field">
                    <label for="set_roi_bonus_2">بونص نمو — السنة الثانية %</label>
                    <input id="set_roi_bonus_2" name="roi_growth_bonus_year2" type="number" value="{{ $settings['roi_growth_bonus_year2_percent'] }}" required min="0" max="100" step="0.01" dir="ltr">
                </div>
                <div class="settings-field">
                    <label for="set_roi_bonus_3">بونص نمو — السنة الثالثة %</label>
                    <input id="set_roi_bonus_3" name="roi_growth_bonus_year3" type="number" value="{{ $settings['roi_growth_bonus_year3_percent'] }}" required min="0" max="100" step="0.01" dir="ltr">
                </div>
                <div class="settings-field">
                    <label for="set_roi_bonus_4">بونص نمو — السنة الرابعة فما بعد %</label>
                    <input id="set_roi_bonus_4" name="roi_growth_bonus_year4" type="number" value="{{ $settings['roi_growth_bonus_year4_percent'] }}" required min="0" max="100" step="0.01" dir="ltr">
                </div>
            </div>
        </div>

        <div class="settings-section">
            <div class="settings-section-title">◌ نسب التوزيع الافتراضية (قاعدة التوزيع النشطة)</div>
            <div class="settings-grid">
                @if ($activeRule)
                    <div class="settings-field">
                        <label>رسوم الإدارة</label>
                        <input type="text" value="{{ $activeRule->management_fee_rate * 100 }} %" readonly>
                        <span class="hint">تُدار من صفحة «قواعد التوزيع»</span>
                    </div>
                    <div class="settings-field">
                        <label>صندوق الإهلاك</label>
                        <input type="text" value="{{ $activeRule->depreciation_fund_rate * 100 }} %" readonly>
                    </div>
                    <div class="settings-field">
                        <label>صندوق النمو</label>
                        <input type="text" value="{{ $activeRule->growth_fund_rate * 100 }} %" readonly>
                    </div>
                    <div class="settings-field">
                        <label>حوافز المشاركين</label>
                        <input type="text" value="{{ $activeRule->incentive_fund_rate * 100 }} %" readonly>
                    </div>
                    <div class="settings-field">
                        <label>الحصة الموزعة للمشاركين</label>
                        <input type="text" value="{{ $activeRule->distributed_share_rate * 100 }} %" readonly>
                    </div>
                @else
                    <div class="settings-field">
                        <label>قاعدة التوزيع</label>
                        <input type="text" value="لا توجد قاعدة نشطة حالياً" readonly>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════ --}}
    {{-- PANE 3 — SECURITY                                 --}}
    {{-- ══════════════════════════════════════════════════ --}}
    <div class="settings-pane" id="pane-security">
        <div class="settings-section">
            <div class="settings-section-title">✦ إعدادات الجلسة وتسجيل الدخول</div>
            <div class="settings-grid">
                <div class="settings-field">
                    <label for="set_session_lifetime">مدة انتهاء جلسة الويب (دقيقة)</label>
                    <input id="set_session_lifetime" name="session_lifetime_minutes" type="number" value="{{ $settings['session_lifetime_minutes'] }}" required min="5" max="1440" dir="ltr">
                    <span class="hint">من 5 إلى 1440 دقيقة</span>
                </div>
                <div class="settings-field">
                    <label for="set_login_attempts">الحد الأقصى لمحاولات تسجيل الدخول</label>
                    <input id="set_login_attempts" name="login_throttle_attempts" type="number" value="{{ $settings['login_throttle_attempts'] }}" required min="1" max="100" dir="ltr">
                </div>
            </div>
        </div>
    </div>

    <div class="settings-section" style="grid-column: 1 / -1;">
        <button type="submit" class="primary-button" data-submit>حفظ الإعدادات</button>
    </div>
</form>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.settings-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.settings-tab').forEach(function (t) { t.classList.remove('active'); });
            document.querySelectorAll('.settings-pane').forEach(function (p) { p.classList.remove('active'); });
            tab.classList.add('active');
            var pane = document.getElementById('pane-' + tab.dataset.tab);
            if (pane) pane.classList.add('active');
        });
    });
});
</script>
@endpush