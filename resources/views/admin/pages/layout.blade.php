<!doctype html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'لوحة الإدارة' }} | ORCA MED Partners</title>
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
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                    href="{{ route('admin.dashboard') }}"><span class="nav-icon">⌂</span>لوحة التحكم</a>
                <a class="nav-link {{ request()->routeIs('admin.participants') ? 'active' : '' }}"
                    href="{{ route('admin.participants') }}"><span class="nav-icon">◉</span>المشاركون</a>
                <a class="nav-link {{ request()->routeIs('admin.investments') ? 'active' : '' }}"
                    href="{{ route('admin.investments') }}"><span class="nav-icon">↗</span>الاستثمارات</a>
                <a class="nav-link {{ request()->routeIs('admin.capital') ? 'active' : '' }}"
                    href="{{ route('admin.capital') }}"><span class="nav-icon">▦</span>رأس المال</a>
                <div class="sidebar-label nav-section">الأداء المالي</div>
                <a class="nav-link {{ request()->routeIs('admin.monthly-profits') ? 'active' : '' }}"
                    href="{{ route('admin.monthly-profits') }}"><span class="nav-icon">⌁</span>الأرباح الشهرية</a>
                <a class="nav-link {{ request()->routeIs('admin.settlements') ? 'active' : '' }}"
                    href="{{ route('admin.settlements') }}"><span class="nav-icon">◫</span>التسويات السنوية</a>
                <a class="nav-link {{ request()->routeIs('admin.funds') ? 'active' : '' }}"
                    href="{{ route('admin.funds') }}"><span class="nav-icon">◌</span>الصناديق</a>
                <a class="nav-link {{ request()->routeIs('admin.depreciation') ? 'active' : '' }}"
                    href="{{ route('admin.depreciation') }}"><span class="nav-icon">⌇</span>الإهلاك</a>
                <div class="sidebar-label nav-section">النظام</div>
                <a class="nav-link {{ request()->routeIs('admin.reports') ? 'active' : '' }}"
                    href="{{ route('admin.reports') }}"><span class="nav-icon">▤</span>التقارير</a>
                <a class="nav-link {{ request()->routeIs('admin.notifications') ? 'active' : '' }}"
                    href="{{ route('admin.notifications') }}"><span class="nav-icon">◍</span>الإشعارات</a>
                <a class="nav-link {{ request()->routeIs('admin.distribution-rules') ? 'active' : '' }}"
                    href="{{ route('admin.distribution-rules') }}"><span class="nav-icon">◎</span>قواعد التوزيع</a>
                <a class="nav-link {{ request()->routeIs('admin.settings') ? 'active' : '' }}"
                    href="{{ route('admin.settings') }}"><span class="nav-icon">⚙</span>الإعدادات</a>
                <a class="nav-link {{ request()->routeIs('admin.audit-logs') ? 'active' : '' }}"
                    href="{{ route('admin.audit-logs') }}"><span class="nav-icon">◌</span>سجل التدقيق</a>
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
                <div class="breadcrumb"><span>الرئيسية</span><b>/</b><strong>{{ $title }}</strong></div>
                <div class="topbar-actions">
                    <label class="search-box"><span aria-hidden="true">⌕</span><input placeholder="ابحث في المنصة"
                            aria-label="بحث"></label>
                    <div class="profile-menu">
                        <div class="avatar">{{ mb_substr(request()->user()->name, 0, 1) }}</div>
                        <div>
                            <strong>{{ request()->user()->name }}</strong><small>{{ request()->user()->role }}</small>
                        </div><span class="chevron">⌄</span>
                    </div>
                    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="logout-button"
                            aria-label="تسجيل الخروج">↪</button></form>
                </div>
            </header>
            <div class="content-wrap">
                @yield('content')
            </div>
        </main>
    </div>
</body>

</html>
