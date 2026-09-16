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
            <input id="password" name="password" type="password" required dir="ltr" style="text-align:right">
            <span class="hint">8 أحرف على الأقل — سيُطلب من المشارك تسجيل الدخول مجدداً بعد التغيير</span>
            @error('password') <span class="hint" style="color:var(--red)">{{ $message }}</span> @enderror
        </div>
        <div class="settings-field">
            <label for="password_confirmation">تأكيد كلمة المرور</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required dir="ltr" style="text-align:right">
        </div>
    </div>

    <div style="display:flex;gap:10px;margin-top:24px">
        <button type="submit" class="primary-button">حفظ كلمة المرور ←</button>
        <a href="{{ route('admin.participants') }}" class="secondary-button">إلغاء</a>
    </div>
</form>

@endsection