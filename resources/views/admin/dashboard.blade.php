@php
    $kpis = $dashboard['kpis'];
    $formatMoney = fn($value) => number_format((float) $value, 2);
    $statusLabels = [
        'draft' => 'مسودة',
        'approved' => 'معتمد',
        'paid' => 'مدفوع',
        'cancelled' => 'ملغى',
        'superseded' => 'مستبدل',
        'active' => 'نشط',
        'inactive' => 'غير نشط',
    ];
@endphp
<!doctype html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>لوحة الإدارة | ORCA MED Partners</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="dashboard-page">
    <div class="app-frame">
        <aside class="sidebar" data-sidebar>
            <div class="sidebar-brand"><span class="brand-mark small">O</span>
                <div><strong>ORCA MED</strong><small>PARTNERS</small></div>
            </div>
            <div class="sidebar-label">مساحة العمل</div>
            <nav class="sidebar-nav" aria-label="التنقل الرئيسي">
                <a class="nav-link active" href="{{ route('admin.dashboard') }}"><span class="nav-icon">⌂</span>لوحة
                    التحكم</a>
                <a class="nav-link" href="#participants"><span class="nav-icon">◉</span>المشاركون</a>
                <a class="nav-link" href="#participants"><span class="nav-icon">↗</span>الاستثمارات</a>
                <a class="nav-link" href="#capital"><span class="nav-icon">▦</span>رأس المال</a>
                <div class="sidebar-label nav-section">الأداء المالي</div>
                <a class="nav-link" href="#profits"><span class="nav-icon">⌁</span>الأرباح الشهرية</a>
                <a class="nav-link" href="#settlements"><span class="nav-icon">◫</span>التسويات السنوية</a>
                <a class="nav-link" href="#funds"><span class="nav-icon">◌</span>الصناديق</a>
                <a class="nav-link" href="#depreciation"><span class="nav-icon">⌇</span>الإهلاك</a>
                <div class="sidebar-label nav-section">النظام</div>
                <a class="nav-link" href="#reports"><span class="nav-icon">▤</span>التقارير</a>
                <a class="nav-link" href="#activities"><span class="nav-icon">◍</span>الإشعارات</a>
                <a class="nav-link" href="#activities"><span class="nav-icon">◎</span>سجل التدقيق</a>
                <a class="nav-link" href="#settings"><span class="nav-icon">⚙</span>الإعدادات</a>
            </nav>
            <div class="sidebar-bottom">
                <div class="secure-note"><span>✓</span>
                    <div><strong>بيئة آمنة</strong><small>بيانات مشفّرة ومراقبة</small></div>
                </div>
            </div>
        </aside>
        <main class="main-content">
            <header class="topbar">
                <button class="icon-button menu-toggle" data-menu-toggle aria-label="فتح القائمة">☰</button>
                <div class="breadcrumb"><span>الرئيسية</span><b>/</b><strong>لوحة التحكم</strong></div>
                <div class="topbar-actions">
                    <label class="search-box"><span aria-hidden="true">⌕</span><input placeholder="ابحث في المنصة"
                            aria-label="بحث"></label>
                    <button class="icon-button notification-button" aria-label="الإشعارات"><span>♧</span>
                        @if ($dashboard['attention']['unread_notifications'] > 0)
                            <i>{{ $dashboard['attention']['unread_notifications'] }}</i>
                        @endif
                    </button>
                    <div class="profile-menu">
                        <div class="avatar">{{ mb_substr(request()->user()->name, 0, 1) }}</div>
                        <div><strong>{{ request()->user()->name }}</strong><small>{{ request()->user()->role }}</small>
                        </div><span class="chevron">⌄</span>
                    </div>
                    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="logout-button"
                            aria-label="تسجيل الخروج">↪</button></form>
                </div>
            </header>
            <div class="content-wrap">
                <section class="welcome-row">
                    <div>
                        <p class="eyebrow">نظرة عامة · {{ $dashboard['year'] }}</p>
                        <h1>صباح الخير، {{ request()->user()->name }} <span class="wave">✦</span></h1>
                        <p class="page-subtitle">إليك ملخص الأداء المالي لمنصة ORCA MED Partners.</p>
                    </div>
                    <div class="date-chip">
                        <span>اليوم</span><strong>{{ now()->locale('ar')->translatedFormat('l، j F Y') }}</strong>
                    </div>
                </section>
                <section class="kpi-grid" aria-label="المؤشرات الرئيسية">
                    <article class="kpi-card kpi-primary">
                        <div class="kpi-icon">◈</div><span>إجمالي رأس
                            المال</span><strong>{{ $formatMoney($kpis['capital']) }} <small>ر.س</small></strong><em>آخر
                            لقطة رأس مال</em>
                    </article>
                    <article class="kpi-card">
                        <div class="kpi-icon blue">↗</div><span>إجمالي
                            الاستثمارات</span><strong>{{ $formatMoney($kpis['investments']) }}
                            <small>ر.س</small></strong><em>كل الاستثمارات المسجلة</em>
                    </article>
                    <article class="kpi-card">
                        <div class="kpi-icon cyan">◉</div><span>المشاركون
                            النشطون</span><strong>{{ number_format($kpis['participants']) }}</strong><em>حساب نشط</em>
                    </article>
                    <article class="kpi-card">
                        <div class="kpi-icon violet">⌁</div><span>الأرباح
                            المعتمدة</span><strong>{{ $formatMoney($kpis['approved_profits']) }}
                            <small>ر.س</small></strong><em>نتائج معتمدة فقط</em>
                    </article>
                    <article class="kpi-card">
                        <div class="kpi-icon amber">◫</div><span>إجمالي
                            المستحقات</span><strong>{{ $formatMoney($kpis['amount_due']) }}
                            <small>ر.س</small></strong><em>التسويات الحالية</em>
                    </article>
                    <article class="kpi-card">
                        <div class="kpi-icon green">✓</div><span>إجمالي
                            المدفوع</span><strong>{{ $formatMoney($kpis['paid']) }}
                            <small>ر.س</small></strong><em>دفعات مكتملة</em>
                    </article>
                </section>
                <section class="dashboard-grid overview-grid" id="capital">
                    <article class="panel chart-panel">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">الأداء المالي</p>
                                <h2>نظرة عامة على الأرباح</h2>
                            </div><span class="legend"><i class="legend-blue"></i>إجمالي الربح <i
                                    class="legend-light"></i>الموزع للمشاركين</span>
                        </div>
                        <div class="chart-wrap">
                            <div class="y-axis">
                                <span>{{ $formatMoney($dashboard['chart_max']) }}</span><span>{{ $formatMoney($dashboard['chart_max'] * 0.66) }}</span><span>{{ $formatMoney($dashboard['chart_max'] * 0.33) }}</span><span>0</span>
                            </div>
                            <div class="bars-area">
                                @foreach ($dashboard['monthly_series'] as $point)
                                    <div class="bar-group">
                                        <div class="bar-pair"><span class="bar bar-gross"
                                                style="height: {{ min(100, ($point['gross'] / $dashboard['chart_max']) * 100) }}%"
                                                title="{{ $formatMoney($point['gross']) }}"></span><span
                                                class="bar bar-distributed"
                                                style="height: {{ min(100, ($point['distributed'] / $dashboard['chart_max']) * 100) }}%"
                                                title="{{ $formatMoney($point['distributed']) }}"></span></div>
                                        <small>{{ $point['label'] }}</small>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </article>
                    <article class="panel rule-panel">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">قاعدة التوزيع الحالية</p>
                                <h2>توزيع الربح</h2>
                            </div><span class="rule-date">مُعتمدة</span>
                        </div>
                        @if ($dashboard['distribution_rule'])
                            <div class="rule-list">
                                @foreach ([['management', 'الإدارة', 'rule-navy'], ['depreciation', 'صندوق الإهلاك', 'rule-sky'], ['growth', 'صندوق النمو', 'rule-blue'], ['incentive', 'حافز المشاركين', 'rule-cyan'], ['distributed', 'الموزع للمشاركين', 'rule-deep']] as [$key, $label, $color])
                                    <div class="rule-row"><span
                                            class="rule-dot {{ $color }}"></span><span>{{ $label }}</span><strong>{{ number_format((float) $dashboard['distribution_rule'][$key] * 100, 2) }}%</strong>
                                    </div>
                                @endforeach
                            </div>
                        @else<div class="empty-state compact"><span>◌</span>
                                <p>لا توجد قاعدة توزيع فعالة</p>
                            </div>
                        @endif
                    </article>
                </section>
                <section class="section-block" id="funds">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">احتياطيات المؤسسة</p>
                            <h2>الصناديق التشغيلية</h2>
                        </div><a href="#reports" class="text-link">عرض كل الحركات ←</a>
                    </div>
                    <div class="fund-grid">
                        @forelse($dashboard['funds'] as $fund)
                            <article class="fund-card">
                                <div class="fund-top"><span class="fund-symbol">◌</span><span
                                        class="status-badge status-{{ $fund['status'] }}">{{ $statusLabels[$fund['status']] ?? $fund['status'] }}</span>
                                </div>
                                <h3>{{ $fund['name'] }}</h3><strong
                                    class="fund-balance">{{ $formatMoney($fund['balance']) }}
                                    <small>ر.س</small></strong>
                                <div class="fund-meta"><span>إيداعات
                                        <b>{{ $formatMoney($fund['deposits']) }}</b></span><span>سحوبات
                                        <b>{{ $formatMoney($fund['withdrawals']) }}</b></span></div><small
                                    class="fund-date">آخر حركة:
                                    {{ $fund['last_transaction'] ?? 'لا توجد حركات' }}</small>
                        </article>@empty<div class="empty-state"><span>◌</span>
                                <p>لا توجد بيانات صناديق لهذه الفترة</p>
                            </div>
                        @endforelse
                    </div>
                </section>
                <section class="dashboard-grid tables-grid" id="participants">
                    <article class="panel table-panel">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">قاعدة المشاركين</p>
                                <h2>أحدث المشاركين</h2>
                            </div><a href="#participants" class="icon-link" aria-label="توسيع">↗</a>
                        </div>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>المشارك</th>
                                        <th>الاستثمار</th>
                                        <th>الحالة</th>
                                        <th>تاريخ الانضمام</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($dashboard['participants'] as $participant)
                                        <tr>
                                            <td>
                                                <div class="person-cell"><span
                                                        class="person-avatar">{{ mb_substr($participant['name'] ?: $participant['username'], 0, 1) }}</span>
                                                    <div>
                                                        <strong>{{ $participant['name'] ?: $participant['username'] }}</strong><small>{{ $participant['username'] }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="numeric">{{ $formatMoney($participant['investment']) }}</td>
                                            <td><span
                                                    class="status-badge status-{{ $participant['status'] }}">{{ $statusLabels[$participant['status']] ?? $participant['status'] }}</span>
                                            </td>
                                            <td class="muted">{{ $participant['joined'] }}</td>
                                    </tr>@empty<tr>
                                            <td colspan="4">
                                                <div class="empty-state compact"><span>◉</span>
                                                    <p>لا توجد بيانات مشاركين</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </article>
                    <article class="panel attention-panel">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">المتابعة</p>
                                <h2>يتطلب انتباهك</h2>
                            </div><span class="attention-icon">!</span>
                        </div>
                        <div class="attention-list">
                            <div><span class="attention-number">{{ $dashboard['attention']['draft_profits'] }}</span>
                                <p><strong>أرباح شهرية</strong><small>بانتظار الاعتماد</small></p><b>›</b>
                            </div>
                            <div><span
                                    class="attention-number">{{ $dashboard['attention']['draft_settlements'] }}</span>
                                <p><strong>تسويات سنوية</strong><small>في حالة المسودة</small></p><b>›</b>
                            </div>
                            <div><span
                                    class="attention-number">{{ $dashboard['attention']['unread_notifications'] }}</span>
                                <p><strong>إشعارات جديدة</strong><small>لم تتم قراءتها</small></p><b>›</b>
                            </div>
                        </div>
                    </article>
                </section>
                <section class="dashboard-grid tables-grid" id="profits">
                    <article class="panel table-panel">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">النشاط المالي</p>
                                <h2>الأرباح الشهرية</h2>
                            </div><a href="#profits" class="text-link">عرض الكل ←</a>
                        </div>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>الفترة</th>
                                        <th>إجمالي الربح</th>
                                        <th>الموزع</th>
                                        <th>الحالة</th>
                                        <th>اعتمد في</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($dashboard['profits'] as $profit)
                                        <tr>
                                            <td><strong>{{ $profit['period'] }}</strong></td>
                                            <td class="numeric">{{ $formatMoney($profit['gross']) }}</td>
                                            <td class="numeric">{{ $formatMoney($profit['distributed']) }}</td>
                                            <td><span
                                                    class="status-badge status-{{ $profit['status'] }}">{{ $statusLabels[$profit['status']] ?? $profit['status'] }}</span>
                                            </td>
                                            <td class="muted">{{ $profit['approved_at'] ?? '—' }}</td>
                                    </tr>@empty<tr>
                                            <td colspan="5">
                                                <div class="empty-state compact"><span>⌁</span>
                                                    <p>لا توجد أرباح مسجلة</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </article>
                </section>
                <section class="dashboard-grid tables-grid" id="settlements">
                    <article class="panel table-panel">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">التسويات السنوية</p>
                                <h2>آخر التسويات</h2>
                            </div><a href="#settlements" class="text-link">إدارة التسويات ←</a>
                        </div>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>السنة</th>
                                        <th>المشاركون</th>
                                        <th>الربح السنوي</th>
                                        <th>المستحق</th>
                                        <th>المدفوع</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($dashboard['settlements'] as $settlement)
                                        <tr>
                                            <td><strong>{{ $settlement['year'] }}</strong></td>
                                            <td>{{ $settlement['participants'] }}</td>
                                            <td class="numeric">{{ $formatMoney($settlement['profit']) }}</td>
                                            <td class="numeric">{{ $formatMoney($settlement['due']) }}</td>
                                            <td class="numeric">{{ $formatMoney($settlement['paid']) }}</td>
                                            <td><span
                                                    class="status-badge status-{{ $settlement['status'] }}">{{ $statusLabels[$settlement['status']] ?? $settlement['status'] }}</span>
                                            </td>
                                    </tr>@empty<tr>
                                            <td colspan="6">
                                                <div class="empty-state compact"><span>◫</span>
                                                    <p>لا توجد تسويات متاحة</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </article>
                </section>
                <section class="dashboard-grid lower-grid" id="depreciation">
                    <article class="panel table-panel">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">سجل الإهلاك</p>
                                <h2>آخر العمليات</h2>
                            </div>
                        </div>
                        <div class="activity-list">
                            @forelse($dashboard['depreciation'] as $note)
                                <div class="activity-row"><span class="activity-dot">⌇</span>
                                    <div><strong>{{ $note['description'] }}</strong><small>{{ $note['period'] }} ·
                                            {{ $note['date'] }}</small></div>
                                    <b>{{ $formatMoney($note['amount']) }}</b>
                            </div>@empty<div class="empty-state compact"><span>⌇</span>
                                    <p>لا توجد عمليات إهلاك</p>
                                </div>
                            @endforelse
                        </div>
                    </article>
                    <article class="panel table-panel" id="activities">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">الأمان والتتبع</p>
                                <h2>آخر النشاطات</h2>
                            </div>
                        </div>
                        <div class="activity-list">
                            @forelse($dashboard['activities'] as $activity)
                                <div class="activity-row"><span class="activity-dot blue-dot">✓</span>
                                    <div><strong>{{ $activity['action'] }}</strong><small>{{ $activity['entity'] }} ·
                                            {{ $activity['at'] }}</small></div><b>›</b>
                            </div>@empty<div class="empty-state compact"><span>◎</span>
                                    <p>لا توجد نشاطات مسجلة</p>
                                </div>
                            @endforelse
                        </div>
                    </article>
                </section>
                <footer class="dashboard-footer"><span>ORCA MED Partners · مركز الإدارة المالي</span><span>آخر تحديث
                        {{ now()->format('H:i') }}</span></footer>
            </div>
        </main>
    </div>
</body>

</html>
