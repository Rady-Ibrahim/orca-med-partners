@php
    $companyName = (string) \App\Support\AppSettingBag::get('company_name', 'ORCA MED Partners');
    $routeName = request()->route()?->getName() ?? '';
    $isActive = fn (string $prefix) => str_starts_with($routeName, $prefix);
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'لوحة الإدارة' }} | {{ $companyName }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>(function(){try{var t=localStorage.getItem('orca-theme');if(t==='light')document.documentElement.classList.add('light');}catch(e){}}());</script>
</head>
<body class="dashboard-page">

    <div class="app-layout">

        <aside class="app-sidebar" data-sidebar>
            <div class="sidebar-brand">
                <span class="brand-mark small">O</span>
                <div>
                    <strong>{{ strtoupper($companyName) }}</strong>
                    <small>PARTNERS</small>
                </div>
            </div>
            <nav class="sidebar-nav" aria-label="التنقل الرئيسي">
                <div class="sidebar-label">مساحة العمل</div>
                <a class="nav-link @if($isActive('admin.dashboard')) active @endif" href="{{ route('admin.dashboard') }}">
                    <span class="nav-icon">⌂</span>
                    <span class="nav-text">لوحة التحكم</span>
                </a>
                <a class="nav-link @if($isActive('admin.participants')) active @endif" href="{{ route('admin.participants') }}">
                    <span class="nav-icon">◉</span>
                    <span class="nav-text">المشاركون</span>
                </a>
                <a class="nav-link @if($isActive('admin.investments')) active @endif" href="{{ route('admin.investments') }}">
                    <span class="nav-icon">↗</span>
                    <span class="nav-text">الاستثمارات</span>
                </a>
                <a class="nav-link @if($isActive('admin.capital')) active @endif" href="{{ route('admin.capital') }}">
                    <span class="nav-icon">▦</span>
                    <span class="nav-text">رأس المال</span>
                </a>

                <div class="sidebar-label nav-section">الأداء المالي</div>
                <a class="nav-link @if($isActive('admin.monthly-profits')) active @endif" href="{{ route('admin.monthly-profits') }}">
                    <span class="nav-icon">⌁</span>
                    <span class="nav-text">الأرباح الشهرية</span>
                </a>
                <a class="nav-link @if($isActive('admin.settlements')) active @endif" href="{{ route('admin.settlements') }}">
                    <span class="nav-icon">◫</span>
                    <span class="nav-text">التسويات السنوية</span>
                </a>
                <a class="nav-link @if($isActive('admin.funds')) active @endif" href="{{ route('admin.funds') }}">
                    <span class="nav-icon">◌</span>
                    <span class="nav-text">الصناديق</span>
                </a>
                <a class="nav-link @if($isActive('admin.depreciation')) active @endif" href="{{ route('admin.depreciation') }}">
                    <span class="nav-icon">⌇</span>
                    <span class="nav-text">الإهلاك</span>
                </a>

                <div class="sidebar-label nav-section">النظام</div>
                <a class="nav-link @if($isActive('admin.reports')) active @endif" href="{{ route('admin.reports') }}">
                    <span class="nav-icon">▤</span>
                    <span class="nav-text">التقارير</span>
                </a>
                <a class="nav-link @if($isActive('admin.notifications')) active @endif" href="{{ route('admin.notifications') }}">
                    <span class="nav-icon">◍</span>
                    <span class="nav-text">الإشعارات</span>
                </a>
                <a class="nav-link @if($isActive('admin.distribution-rules')) active @endif" href="{{ route('admin.distribution-rules') }}">
                    <span class="nav-icon">◎</span>
                    <span class="nav-text">قواعد التوزيع</span>
                </a>
                <a class="nav-link @if($isActive('admin.settings')) active @endif" href="{{ route('admin.settings') }}">
                    <span class="nav-icon">⚙</span>
                    <span class="nav-text">الإعدادات</span>
                </a>
                <a class="nav-link @if($isActive('admin.audit-logs')) active @endif" href="{{ route('admin.audit-logs') }}">
                    <span class="nav-icon">◎</span>
                    <span class="nav-text">سجل التدقيق</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="avatar sidebar-avatar">{{ mb_substr(request()->user()?->name ?? 'A', 0, 1) }}</div>
                    <div class="sidebar-user-info">
                        <strong>{{ request()->user()?->name ?? '' }}</strong>
                        <small>{{ request()->user()?->role ?? '' }}</small>
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
                <div class="breadcrumb">
                    <span>الرئيسية</span><b>/</b><strong id="page-title">{{ $title ?? '' }}</strong>
                </div>
                <div class="app-header-actions">
                    <label class="search-box">
                        <span aria-hidden="true">⌕</span>
                        <input placeholder="ابحث في المنصة" aria-label="بحث">
                    </label>
                    <button class="theme-toggle" title="تبديل المظهر">☀️</button>

                    {{-- Profile Dropdown --}}
                    <div class="profile-dropdown" id="profile-dropdown">
                        <button class="profile-trigger" id="profile-trigger" aria-haspopup="true"
                            aria-expanded="false">
                            <div class="avatar">{{ mb_substr(request()->user()?->name ?? 'A', 0, 1) }}</div>
                            <div class="profile-trigger-info">
                                <strong>{{ request()->user()?->name ?? '' }}</strong>
                                <small>{{ request()->user()?->role ?? '' }}</small>
                            </div>
                            <span class="chevron">⌄</span>
                        </button>
                        <div class="dropdown-menu" id="dropdown-menu" role="menu">
                            <div class="dropdown-header">
                                <div class="avatar dropdown-avatar">{{ mb_substr(request()->user()?->name ?? 'A', 0, 1) }}</div>
                                <div>
                                    <strong>{{ request()->user()?->name ?? '' }}</strong>
                                    <small>{{ request()->user()?->email ?? request()->user()?->role ?? '' }}</small>
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
                @yield('content')
            </div>
        </main>
    </div>

@stack('scripts')
</body>
</html>