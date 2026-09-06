<!doctype html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>دخول الإدارة | ORCA MED Partners</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-panel">
            <div class="brand-mark" aria-hidden="true">O</div>
            <p class="eyebrow">ORCA MED PARTNERS</p>
            <h1>مرحبًا بك مجددًا</h1>
            <p class="auth-intro">سجّل الدخول إلى مركز الإدارة المالي لمتابعة الاستثمارات والأرباح والتسويات.</p>
            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('admin.login.submit') }}" class="auth-form">
                @csrf
                <label for="username">اسم المستخدم</label>
                <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" required
                    autofocus>
                <label for="password">كلمة المرور</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <button class="button button-primary button-wide" type="submit">دخول آمن <span
                        aria-hidden="true">←</span></button>
            </form>
            <p class="auth-footnote">وصول مخصص لفريق الإدارة المصرّح له.</p>
        </section>
        <aside class="auth-aside">
            <div class="aside-orbit orbit-one"></div>
            <div class="aside-orbit orbit-two"></div>
            <p class="eyebrow eyebrow-light">FINANCIAL OPERATIONS</p>
            <h2>رؤية أوضح.<br>قرارات أسرع.</h2>
            <p>مساحة عمل موحّدة للبيانات المالية المعتمدة وحركة الصناديق ومتابعة المشاركين.</p>
            <div class="aside-stat"><strong>01</strong><span>مركز مالي موثوق</span></div>
        </aside>
    </main>
</body>

</html>
