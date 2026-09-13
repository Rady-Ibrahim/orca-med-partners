@extends('admin.pages.layout')

@section('content')

<div class="admin-page-header">
    <div>
        <p class="eyebrow">إدارة النظام</p>
        <h1>الإعدادات</h1>
    </div>
    <div class="page-actions">
        <span class="settings-badge on">● النظام نشط</span>
    </div>
</div>

{{-- ── Tabs ── --}}
<div class="settings-tabs" role="tablist">
    <button class="settings-tab active" data-tab="general"   role="tab">⚙ إعدادات عامة</button>
    <button class="settings-tab"        data-tab="financial" role="tab">◈ القواعد المالية</button>
    <button class="settings-tab"        data-tab="theme"     role="tab">◑ المظهر</button>
    <button class="settings-tab"        data-tab="security"  role="tab">✦ الأمان</button>
</div>

{{-- ══════════════════════════════════════════════════ --}}
{{-- PANE 1 — GENERAL                                  --}}
{{-- ══════════════════════════════════════════════════ --}}
<div class="settings-pane active" id="pane-general">

    <div class="settings-section">
        <div class="settings-section-title">⚙ إعدادات المنصة العامة</div>
        <div class="settings-grid">
            <div class="settings-field">
                <label>اسم المنصة</label>
                <input type="text" value="ORCA MED Partners" readonly>
                <span class="hint">يظهر في الواجهة وعناوين الصفحات</span>
            </div>
            <div class="settings-field">
                <label>رمز العملة</label>
                <select disabled>
                    <option selected>ر.س — ريال سعودي (SAR)</option>
                    <option>ج.م — جنيه مصري (EGP)</option>
                    <option>$ — دولار أمريكي (USD)</option>
                </select>
                <span class="hint">يُستخدم في عرض جميع القيم المالية</span>
            </div>
            <div class="settings-field">
                <label>تنسيق التاريخ</label>
                <select disabled>
                    <option selected>YYYY-MM-DD</option>
                    <option>DD/MM/YYYY</option>
                    <option>MM/DD/YYYY</option>
                </select>
            </div>
            <div class="settings-field">
                <label>اللغة الافتراضية</label>
                <input type="text" value="العربية (AR) — RTL" readonly>
            </div>
        </div>
    </div>

    <div class="settings-section">
        <div class="settings-section-title">◌ إحصاءات قاعدة البيانات</div>
        <div class="settings-grid">
            <div class="settings-field">
                <label>إجمالي الإعدادات المخزنة</label>
                <input type="text" value="{{ $items->total() }}" readonly>
            </div>
            <div class="settings-field">
                <label>آخر تحديث للإعدادات</label>
                <input type="text" value="{{ $items->first()['updated_at'] ?? 'لم يتم بعد' }}" readonly>
            </div>
            <div class="settings-field">
                <label>بيئة التشغيل</label>
                <input type="text" value="{{ ucfirst(app()->environment()) }}" readonly>
            </div>
            <div class="settings-field">
                <label>إصدار Laravel</label>
                <input type="text" value="{{ app()->version() }}" readonly>
            </div>
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════════════════ --}}
{{-- PANE 2 — FINANCIAL                                --}}
{{-- ══════════════════════════════════════════════════ --}}
<div class="settings-pane" id="pane-financial">

    <div class="settings-section">
        <div class="settings-section-title">◈ نسب التوزيع الافتراضية (قاعدة التوزيع النشطة)</div>
        <div class="settings-grid">
            <div class="settings-field">
                <label>رسوم الإدارة</label>
                <input type="text" value="25.00 %" readonly>
                <span class="hint">القيمة الفعلية تُحدَّد من قواعد التوزيع</span>
            </div>
            <div class="settings-field">
                <label>صندوق الاستهلاك</label>
                <input type="text" value="5.00 %" readonly>
            </div>
            <div class="settings-field">
                <label>صندوق النمو</label>
                <input type="text" value="2.50 %" readonly>
            </div>
            <div class="settings-field">
                <label>حوافز المشاركين</label>
                <input type="text" value="2.50 %" readonly>
            </div>
            <div class="settings-field">
                <label>الحصة الموزعة للمشاركين</label>
                <input type="text" value="65.00 %" readonly>
            </div>
            <div class="settings-field">
                <label>المجموع الكلي</label>
                <input type="text" value="100.00 % ✓" readonly>
            </div>
        </div>
    </div>

    <div class="settings-section">
        <div class="settings-section-title">⊞ دقة الأرقام وسياسة التقريب</div>
        <div class="settings-grid">
            <div class="settings-field">
                <label>نوع حقل العملة (DB)</label>
                <input type="text" value="DECIMAL(15, 2)" readonly>
                <span class="hint">لا يوجد float أو double في أي حقل مالي</span>
            </div>
            <div class="settings-field">
                <label>محرك الحساب</label>
                <input type="text" value="bcmath — PHP" readonly>
            </div>
            <div class="settings-field">
                <label>سياسة التقريب</label>
                <input type="text" value="Half-Even (Banker's Rounding)" readonly>
            </div>
            <div class="settings-field">
                <label>دقة العرض</label>
                <input type="text" value="رقمان عشريان دائماً" readonly>
            </div>
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════════════════ --}}
{{-- PANE 3 — THEME                                    --}}
{{-- ══════════════════════════════════════════════════ --}}
<div class="settings-pane" id="pane-theme">

    <div class="settings-section">
        <div class="settings-section-title">◑ تفضيلات المظهر</div>

        <div class="settings-toggle-row">
            <div>
                <strong>الوضع الداكن / الفاتح</strong>
                <span>التبديل بين Dark Mode و Light Mode — يُحفظ التفضيل محلياً.</span>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" id="theme-toggle-settings">
                <span class="toggle-slider"></span>
            </label>
        </div>

        <div class="settings-toggle-row">
            <div>
                <strong>الخط المستخدم</strong>
                <span>Tajawal — خط عربي عصري عالي الوضوح (Google Fonts)</span>
            </div>
            <span class="settings-badge on">مُفعّل</span>
        </div>

        <div class="settings-toggle-row">
            <div>
                <strong>اتجاه الواجهة</strong>
                <span>RTL — من اليمين إلى اليسار بالكامل</span>
            </div>
            <span class="settings-badge on">مُفعّل</span>
        </div>

        <div class="settings-toggle-row">
            <div>
                <strong>موقع الشريط الجانبي</strong>
                <span>يمين الشاشة على الديسكتوب — يسار على الموبايل (منزلق)</span>
            </div>
            <span class="settings-badge on">يمين</span>
        </div>

        <div class="settings-toggle-row">
            <div>
                <strong>انتقالات سلسة عند تبديل المظهر</strong>
                <span>0.25s ease على الخلفيات والألوان</span>
            </div>
            <span class="settings-badge on">مُفعّل</span>
        </div>
    </div>

    <div class="settings-section">
        <div class="settings-section-title">◌ قيم الألوان الأساسية</div>
        <div class="settings-grid">
            <div class="settings-field">
                <label>الخلفية الأساسية (Dark)</label>
                <input type="text" value="#0F172A — Slate Navy" readonly>
            </div>
            <div class="settings-field">
                <label>لون التمييز (Dark)</label>
                <input type="text" value="#00F0FF — Cyber Cyan" readonly>
            </div>
            <div class="settings-field">
                <label>الخلفية الأساسية (Light)</label>
                <input type="text" value="#F1F5F9 — Cool Gray" readonly>
            </div>
            <div class="settings-field">
                <label>لون التمييز (Light)</label>
                <input type="text" value="#1D4ED8 — Royal Blue" readonly>
            </div>
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════════════════ --}}
{{-- PANE 4 — SECURITY                                 --}}
{{-- ══════════════════════════════════════════════════ --}}
<div class="settings-pane" id="pane-security">

    <div class="settings-section">
        <div class="settings-section-title">✦ حالة الأمان والحماية</div>

        <div class="settings-toggle-row">
            <div>
                <strong>سجل التدقيق التلقائي (Audit Log)</strong>
                <span>تسجيل جميع العمليات الحساسة في قاعدة البيانات فور حدوثها</span>
            </div>
            <span class="settings-badge on">مُفعّل</span>
        </div>

        <div class="settings-toggle-row">
            <div>
                <strong>عزل بيانات المشاركين (IDOR Protection)</strong>
                <span>كل مشارك لا يرى إلا بياناته الخاصة — محمي على مستوى الـ Policy</span>
            </div>
            <span class="settings-badge on">مُفعّل</span>
        </div>

        <div class="settings-toggle-row">
            <div>
                <strong>ثبات السجلات المالية المعتمدة</strong>
                <span>السجلات المعتمدة (Approved) غير قابلة للتعديل أو الحذف نهائياً</span>
            </div>
            <span class="settings-badge on">مُفعّل</span>
        </div>

        <div class="settings-toggle-row">
            <div>
                <strong>Laravel Sanctum — API Auth</strong>
                <span>المصادقة عبر Sanctum Tokens لكل طلبات API المشاركين</span>
            </div>
            <span class="settings-badge on">مُفعّل</span>
        </div>

        <div class="settings-toggle-row">
            <div>
                <strong>Rate Limiting — تقييد الطلبات</strong>
                <span>10 محاولات كحد أقصى خلال 60 ثانية لتسجيل الدخول</span>
            </div>
            <span class="settings-badge on">مُفعّل</span>
        </div>

        <div class="settings-toggle-row">
            <div>
                <strong>التحقق بخطوتين (2FA)</strong>
                <span>غير مُفعّل في الإصدار الحالي</span>
            </div>
            <span class="settings-badge off">غير مُفعّل</span>
        </div>
    </div>

    <div class="settings-section">
        <div class="settings-section-title">◷ إعدادات الجلسة والتوكن</div>
        <div class="settings-grid">
            <div class="settings-field">
                <label>مدة انتهاء جلسة الويب</label>
                <input type="text" value="120 دقيقة" readonly>
            </div>
            <div class="settings-field">
                <label>مدة انتهاء API Token</label>
                <input type="text" value="حسب إعدادات Sanctum" readonly>
            </div>
            <div class="settings-field">
                <label>تدوير Refresh Token</label>
                <input type="text" value="مُفعّل — يُلغى القديم فوراً" readonly>
            </div>
            <div class="settings-field">
                <label>بيئة التشغيل الحالية</label>
                <input type="text" value="{{ ucfirst(app()->environment()) }}" readonly>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var cb = document.getElementById('theme-toggle-settings');
    if (!cb) return;
    // Sync checkbox with current theme
    function syncCb() { cb.checked = document.documentElement.classList.contains('light'); }
    syncCb();
    // Watch for changes triggered by the topbar toggle
    new MutationObserver(syncCb).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});
</script>
@endpush
