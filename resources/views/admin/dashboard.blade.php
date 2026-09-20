@php
    $kpis = $dashboard['kpis'];
    $formatMoney = fn($value) => \App\Support\DecimalFormatter::money($value);
    $chartPercent = fn($value) => \App\Support\DecimalFormatter::ratioPercent($value, $dashboard['chart_max']);
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="dashboard-page">
    <div class="app-layout">

        <aside class="app-sidebar" data-sidebar>
            <div class="sidebar-brand">
                <span class="brand-mark small">O</span>
                <div>
                    <strong>ORCA MED</strong>
                    <small>PARTNERS</small>
                </div>
            </div>
            <nav class="sidebar-nav" aria-label="التنقل الرئيسي">
                <div class="sidebar-label">مساحة العمل</div>
                <a class="nav-link active" href="{{ route('admin.dashboard') }}">
                    <span class="nav-icon">⌂</span>
                    <span class="nav-text">لوحة التحكم</span>
                </a>
                <a class="nav-link" href="{{ route('admin.participants') }}">
                    <span class="nav-icon">◉</span>
                    <span class="nav-text">المشاركون</span>
                </a>
                <a class="nav-link" href="{{ route('admin.investments') }}">
                    <span class="nav-icon">↗</span>
                    <span class="nav-text">الاستثمارات</span>
                </a>
                <a class="nav-link" href="{{ route('admin.capital') }}">
                    <span class="nav-icon">▦</span>
                    <span class="nav-text">رأس المال</span>
                </a>

                <div class="sidebar-label nav-section">الأداء المالي</div>
                <a class="nav-link" href="{{ route('admin.monthly-profits') }}">
                    <span class="nav-icon">⌁</span>
                    <span class="nav-text">الأرباح الشهرية</span>
                </a>
                <a class="nav-link" href="{{ route('admin.settlements') }}">
                    <span class="nav-icon">◫</span>
                    <span class="nav-text">التسويات السنوية</span>
                </a>
                <a class="nav-link" href="{{ route('admin.funds') }}">
                    <span class="nav-icon">◌</span>
                    <span class="nav-text">الصناديق</span>
                </a>
                <a class="nav-link" href="{{ route('admin.depreciation') }}">
                    <span class="nav-icon">⌇</span>
                    <span class="nav-text">الإهلاك</span>
                </a>

                <div class="sidebar-label nav-section">النظام</div>
                <a class="nav-link" href="{{ route('admin.reports') }}">
                    <span class="nav-icon">▤</span>
                    <span class="nav-text">التقارير</span>
                </a>
                <a class="nav-link" href="{{ route('admin.notifications') }}">
                    <span class="nav-icon">◍</span>
                    <span class="nav-text">الإشعارات</span>
                </a>
                <a class="nav-link" href="{{ route('admin.distribution-rules') }}">
                    <span class="nav-icon">◎</span>
                    <span class="nav-text">قواعد التوزيع</span>
                </a>
                <a class="nav-link" href="{{ route('admin.settings') }}">
                    <span class="nav-icon">⚙</span>
                    <span class="nav-text">الإعدادات</span>
                </a>
                <a class="nav-link" href="{{ route('admin.audit-logs') }}">
                    <span class="nav-icon">◎</span>
                    <span class="nav-text">سجل التدقيق</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="avatar sidebar-avatar">{{ mb_substr(request()->user()->name, 0, 1) }}</div>
                    <div class="sidebar-user-info">
                        <strong>{{ request()->user()->name }}</strong>
                        <small>{{ request()->user()->role }}</small>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}" class="sidebar-logout-form">
                    @csrf
                    <button type="submit" class="sidebar-logout-btn" title="تسجيل الخروج"><span>↪</span></button>
                </form>
            </div>
        </aside>

        {{-- ── BACKDROP (جوال) ── --}}
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <main class="app-main">
            <header class="app-header">
                <button class="icon-button sidebar-toggle-btn" id="sidebarToggle" type="button"
                    aria-label="فتح القائمة">☰</button>
                <div class="breadcrumb"><span>الرئيسية</span><b>/</b><strong>لوحة التحكم</strong></div>
                <div class="app-header-actions">
                    <label class="search-box">
                        <span aria-hidden="true">⌕</span>
                        <input placeholder="ابحث في المنصة" aria-label="بحث">
                    </label>
                    <div class="notification-dropdown" id="notification-dropdown">
                        <button type="button" class="icon-button notification-button" id="notification-trigger"
                            aria-label="الإشعارات" aria-expanded="false">
                            <span>♧</span>
                            @if ($dashboard['attention']['unread_notifications'] > 0)
                                <i>{{ $dashboard['attention']['unread_notifications'] }}</i>
                            @endif
                        </button>
                        <div class="dropdown-menu notification-menu" id="notification-menu" role="menu">
                            <div class="dropdown-header">
                                <div>
                                    <strong>الإشعارات</strong>
                                    <small>
                                        {{ $dashboard['attention']['unread_notifications'] > 0 ? $dashboard['attention']['unread_notifications'].' غير مقروء' : 'لا توجد إشعارات جديدة' }}
                                    </small>
                                </div>
                            </div>
                            @if (empty($dashboard['recent_notifications']))
                                <div class="notification-empty">لا توجد إشعارات بعد</div>
                            @else
                                @foreach ($dashboard['recent_notifications'] as $notification)
                                    <div class="notification-row {{ $notification['is_read'] ? 'read' : 'unread' }}">
                                        <div>
                                            <strong>{{ $notification['title'] }}</strong>
                                            <p>{{ $notification['body'] }}</p>
                                            <small>{{ $notification['participant'] }} · {{ $notification['created_at'] }}</small>
                                        </div>
                                        <span class="notification-dot"></span>
                                    </div>
                                @endforeach
                            @endif
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('admin.notifications') }}">
                                <span class="dropdown-icon">◍</span> عرض كل الإشعارات
                            </a>
                        </div>
                    </div>
                    <button class="theme-toggle" title="تبديل المظهر">☀️</button>
                    <div class="profile-dropdown" id="profile-dropdown">
                        <button class="profile-trigger" id="profile-trigger" aria-haspopup="true"
                            aria-expanded="false">
                            <div class="avatar">{{ mb_substr(request()->user()->name, 0, 1) }}</div>
                            <div class="profile-trigger-info">
                                <strong>{{ request()->user()->name }}</strong>
                                <small>{{ request()->user()->role }}</small>
                            </div>
                            <span class="chevron">⌄</span>
                        </button>
                        <div class="dropdown-menu" id="dropdown-menu" role="menu">
                            <div class="dropdown-header">
                                <div class="avatar dropdown-avatar">{{ mb_substr(request()->user()->name, 0, 1) }}
                                </div>
                                <div>
                                    <strong>{{ request()->user()->name }}</strong>
                                    <small>{{ request()->user()->email ?? request()->user()->role }}</small>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('admin.settings') }}">
                                <span class="dropdown-icon">⚙</span> الإعدادات
                            </a>
                            <a class="dropdown-item" href="{{ route('admin.audit-logs') }}">
                                <span class="dropdown-icon">◎</span> سجل التدقيق
                            </a>
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item dropdown-logout">
                                    <span class="dropdown-icon">↪</span> تسجيل الخروج
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <div class="page-body">
                <section class="welcome-row">
                    <div>
                        <p class="eyebrow">نظرة عامة · {{ $dashboard['year'] }}</p>
                        <h1>صباح الخير، {{ request()->user()->name }} <span class="wave">✦</span></h1>
                        <p class="page-subtitle">إليك ملخص الأداء المالي لمنصة ORCA MED Partners.</p>
                    </div>
                    <div class="date-chip">
                        <span>اليوم</span>
                        <strong>{{ now()->locale('ar')->translatedFormat('l، j F Y') }}</strong>
                    </div>
                </section>

                <section class="kpi-grid" aria-label="المؤشرات الرئيسية">
                    <article class="kpi-card kpi-primary">
                        <div class="kpi-icon">◈</div>
                        <span>إجمالي رأس المال</span>
                        <strong>{{ $formatMoney($kpis['capital']) }} <small>ر.س</small></strong>
                        <em>آخر لقطة رأس مال</em>
                    </article>
                    <article class="kpi-card">
                        <div class="kpi-icon blue">↗</div>
                        <span>إجمالي الاستثمارات</span>
                        <strong>{{ $formatMoney($kpis['investments']) }} <small>ر.س</small></strong>
                        <em>كل الاستثمارات المسجلة</em>
                    </article>
                    <article class="kpi-card">
                        <div class="kpi-icon cyan">◉</div>
                        <span>المشاركون النشطون</span>
                        <strong>{{ number_format($kpis['participants']) }}</strong>
                        <em>حساب نشط</em>
                    </article>
                    <article class="kpi-card">
                        <div class="kpi-icon violet">⌁</div>
                        <span>الأرباح المعتمدة</span>
                        <strong>{{ $formatMoney($kpis['approved_profits']) }} <small>ر.س</small></strong>
                        <em>نتائج معتمدة فقط</em>
                    </article>
                    <article class="kpi-card">
                        <div class="kpi-icon amber">◫</div>
                        <span>إجمالي المستحقات</span>
                        <strong>{{ $formatMoney($kpis['amount_due']) }} <small>ر.س</small></strong>
                        <em>التسويات الحالية</em>
                    </article>
                    <article class="kpi-card">
                        <div class="kpi-icon green">✓</div>
                        <span>إجمالي المدفوع</span>
                        <strong>{{ $formatMoney($kpis['paid']) }} <small>ر.س</small></strong>
                        <em>دفعات مكتملة</em>
                    </article>
                </section>

                <section class="dashboard-grid overview-grid" id="capital">
                    <article class="panel chart-panel">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">الأداء المالي</p>
                                <h2>نظرة عامة على الأرباح</h2>
                            </div>
                            <span class="legend">
                                <i class="legend-blue"></i>إجمالي الربح
                                <i class="legend-light"></i>الموزع للمشاركين
                            </span>
                        </div>
                        <div class="chart-wrap">
                            <div class="y-axis">
                                <span>{{ $formatMoney($dashboard['chart_max']) }}</span>
                                <span>{{ $formatMoney(bcmul($dashboard['chart_max'], '0.66', 2)) }}</span>
                                <span>{{ $formatMoney(bcmul($dashboard['chart_max'], '0.33', 2)) }}</span>
                                <span>0</span>
                            </div>
                            <div class="bars-area">
                                @foreach ($dashboard['monthly_series'] as $point)
                                    <div class="bar-group">
                                        <div class="bar-pair">
                                            <span class="bar bar-gross"
                                                style="height: {{ $chartPercent($point['gross']) }}%"
                                                title="{{ $formatMoney($point['gross']) }}"></span>
                                            <span class="bar bar-distributed"
                                                style="height: {{ $chartPercent($point['distributed']) }}%"
                                                title="{{ $formatMoney($point['distributed']) }}"></span>
                                        </div>
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
                            </div>
                            <span class="rule-date">مُعتمدة</span>
                        </div>
                        @if ($dashboard['distribution_rule'])
                            <div class="rule-list">
                                @foreach ([['management', 'الإدارة', 'rule-navy'], ['depreciation', 'صندوق الإهلاك', 'rule-sky'], ['growth', 'صندوق النمو', 'rule-blue'], ['incentive', 'حافز المشاركين', 'rule-cyan'], ['distributed', 'الموزع للمشاركين', 'rule-deep']] as [$key, $label, $color])
                                    <div class="rule-row">
                                        <span class="rule-dot {{ $color }}"></span>
                                        <span>{{ $label }}</span>
                                        <strong>{{ \App\Support\DecimalFormatter::percent($dashboard['distribution_rule'][$key]) }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="empty-state compact">
                                <span>◌</span>
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
                        </div>
                        <a href="#reports" class="text-link">عرض كل الحركات ←</a>
                    </div>
                    <div class="fund-grid">
                        @forelse($dashboard['funds'] as $fund)
                            <article class="fund-card">
                                <div class="fund-top">
                                    <span class="fund-symbol">◌</span>
                                    <span
                                        class="status-badge status-{{ $fund['status'] }}">{{ $statusLabels[$fund['status']] ?? $fund['status'] }}</span>
                                </div>
                                <h3>{{ $fund['name'] }}</h3>
                                <strong class="fund-balance">
                                    {{ $formatMoney($fund['balance']) }}
                                    <small>ر.س</small>
                                </strong>
                                <div class="fund-meta">
                                    <span>إيداعات <b>{{ $formatMoney($fund['deposits']) }}</b></span>
                                    <span>سحوبات <b>{{ $formatMoney($fund['withdrawals']) }}</b></span>
                                </div>
                                <small class="fund-date">آخر حركة:
                                    {{ $fund['last_transaction'] ?? 'لا توجد حركات' }}</small>
                            </article>
                        @empty
                            <div class="empty-state">
                                <span>◌</span>
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
                            </div>
                            <a href="#participants" class="icon-link" aria-label="توسيع">↗</a>
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
                                                <div class="person-cell">
                                                    <span
                                                        class="person-avatar">{{ mb_substr($participant['name'] ?: $participant['username'], 0, 1) }}</span>
                                                    <div>
                                                        <strong>{{ $participant['name'] ?: $participant['username'] }}</strong>
                                                        <small>{{ $participant['username'] }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="numeric">{{ $formatMoney($participant['investment']) }}</td>
                                            <td>
                                                <span
                                                    class="status-badge status-{{ $participant['status'] }}">{{ $statusLabels[$participant['status']] ?? $participant['status'] }}</span>
                                            </td>
                                            <td class="muted">{{ $participant['joined'] }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4">
                                                <div class="empty-state compact">
                                                    <span>◉</span>
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
                            </div>
                            <span class="attention-icon">!</span>
                        </div>
                        <div class="attention-list">
                            <div>
                                <span class="attention-number">{{ $dashboard['attention']['draft_profits'] }}</span>
                                <p><strong>أرباح شهرية</strong><small>بانتظار الاعتماد</small></p>
                                <b>›</b>
                            </div>
                            <div>
                                <span
                                    class="attention-number">{{ $dashboard['attention']['draft_settlements'] }}</span>
                                <p><strong>تسويات سنوية</strong><small>في حالة المسودة</small></p>
                                <b>›</b>
                            </div>
                            <div>
                                <span
                                    class="attention-number">{{ $dashboard['attention']['unread_notifications'] }}</span>
                                <p><strong>إشعارات جديدة</strong><small>لم تتم قراءتها</small></p>
                                <b>›</b>
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
                            </div>
                            <a href="#profits" class="text-link">عرض الكل ←</a>
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
                                            <td>
                                                <span
                                                    class="status-badge status-{{ $profit['status'] }}">{{ $statusLabels[$profit['status']] ?? $profit['status'] }}</span>
                                            </td>
                                            <td class="muted">{{ $profit['approved_at'] ?? '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5">
                                                <div class="empty-state compact">
                                                    <span>⌁</span>
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
                            </div>
                            <a href="#settlements" class="text-link">إدارة التسويات ←</a>
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
                                            <td>
                                                <span
                                                    class="status-badge status-{{ $settlement['status'] }}">{{ $statusLabels[$settlement['status']] ?? $settlement['status'] }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6">
                                                <div class="empty-state compact">
                                                    <span>◫</span>
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
                                <div class="activity-row">
                                    <span class="activity-dot">⌇</span>
                                    <div>
                                        <strong>{{ $note['description'] }}</strong>
                                        <small>{{ $note['period'] }} · {{ $note['date'] }}</small>
                                    </div>
                                    <b>{{ $formatMoney($note['amount']) }}</b>
                                </div>
                            @empty
                                <div class="empty-state compact">
                                    <span>⌇</span>
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
                                <div class="activity-row">
                                    <span class="activity-dot blue-dot">✓</span>
                                    <div>
                                        <strong>{{ $activity['action'] }}</strong>
                                        <small>{{ $activity['entity'] }} · {{ $activity['at'] }}</small>
                                    </div>
                                    <b>›</b>
                                </div>
                            @empty
                                <div class="empty-state compact">
                                    <span>◎</span>
                                    <p>لا توجد نشاطات مسجلة</p>
                                </div>
                            @endforelse
                        </div>
                    </article>
                </section>

                <footer class="dashboard-footer">
                    <span>ORCA MED Partners · مركز الإدارة المالي</span>
                    <span>آخر تحديث {{ now()->format('H:i') }}</span>
                </footer>
            </div>
        </main>
    </div>
</body>

</html>
