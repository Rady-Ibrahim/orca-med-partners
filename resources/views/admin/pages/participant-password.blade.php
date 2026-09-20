@extends('admin.pages.layout')

@section('content')

<div class="admin-page-header">
    <div>
        <p class="eyebrow">قاعدة المشاركين</p>
        <h1>تغيير كلمة المرور</h1>
        <p class="page-subtitle">المشارك: <strong>{{ $participant->first_name }} {{ $participant->last_name }}</strong>
            <span class="muted">({{ $participant->username }})</span></p>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.participants') }}" class="secondary-button">↩ العودة للقائمة</a>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul style="margin:0;padding-right:18px">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('admin.participants.password.update', $participant) }}" class="settings-section">
    @csrf
    @method('PUT')

    <div class="settings-section-title">✦ كلمة المرور الجديدة</div>
    <div class="settings-grid">
        <div class="settings-field">
            <label for="password">كلمة المرور الجديدة</label>
            <div class="password-field">
                <input id="password" name="password" type="password" required dir="ltr" style="text-align:right">
                <button type="button" class="password-toggle js-password-toggle" tabindex="-1" aria-label="إظهار كلمة المرور" aria-pressed="false">
                    <svg class="eye-icon eye" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="eye-icon eye-off" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
            </div>
            <span class="hint">8 أحرف على الأقل — سيُطلب من المشارك تسجيل الدخول مجدداً بعد التغيير</span>
            @error('password') <span class="hint" style="color:var(--red)">{{ $message }}</span> @enderror
        </div>
        <div class="settings-field">
            <label for="password_confirmation">تأكيد كلمة المرور</label>
            <div class="password-field">
                <input id="password_confirmation" name="password_confirmation" type="password" required dir="ltr" style="text-align:right">
                <button type="button" class="password-toggle js-password-toggle" tabindex="-1" aria-label="إظهار كلمة المرور" aria-pressed="false">
                    <svg class="eye-icon eye" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="eye-icon eye-off" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
            </div>
        </div>
    </div>

    <div style="display:flex;gap:10px;margin-top:24px">
        <button type="submit" class="primary-button">حفظ كلمة المرور ←</button>
        <a href="{{ route('admin.participants') }}" class="secondary-button">إلغاء</a>
    </div>
</form>

@endsection