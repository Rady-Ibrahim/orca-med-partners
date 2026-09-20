@extends('admin.pages.layout')

@section('content')

<div class="admin-page-header">
    <div>
        <p class="eyebrow">قاعدة المشاركين</p>
        <h1>{{ $formAction === 'create' ? 'إنشاء حساب مشارك' : 'تعديل بيانات المشارك' }}</h1>
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

<form method="POST"
      action="{{ $formAction === 'create' ? route('admin.participants.store') : route('admin.participants.update', $participant) }}"
      class="settings-section">
    @csrf
    @if ($formAction === 'edit')
        @method('PUT')
    @endif

    <div class="settings-section-title">◉ البيانات الشخصية</div>
    <div class="settings-grid">
        <div class="settings-field">
            <label for="first_name">الاسم الأول</label>
            <input id="first_name" name="first_name" value="{{ old('first_name', $participant?->first_name) }}"
                required autofocus>
            @error('first_name') <span class="hint" style="color:var(--red)">{{ $message }}</span> @enderror
        </div>
        <div class="settings-field">
            <label for="last_name">اسم العائلة</label>
            <input id="last_name" name="last_name" value="{{ old('last_name', $participant?->last_name) }}" required>
            @error('last_name') <span class="hint" style="color:var(--red)">{{ $message }}</span> @enderror
        </div>
        <div class="settings-field">
            <label for="username">اسم المستخدم</label>
            <input id="username" name="username" value="{{ old('username', $participant?->username) }}"
                required dir="ltr" style="text-align:right">
            <span class="hint">يُستخدم لتسجيل الدخول — لا يمكن أن يتكرر</span>
            @error('username') <span class="hint" style="color:var(--red)">{{ $message }}</span> @enderror
        </div>
        <div class="settings-field">
            <label for="email">البريد الإلكتروني</label>
            <input id="email" name="email" type="email" value="{{ old('email', $participant?->email) }}"
                dir="ltr" style="text-align:right">
            <span class="hint">اختياري — يُستخدم للتواصل</span>
            @error('email') <span class="hint" style="color:var(--red)">{{ $message }}</span> @enderror
        </div>
        <div class="settings-field">
            <label for="status">الحالة</label>
            <select id="status" name="status" required>
                <option value="active" @selected(old('status', $participant?->status) === 'active')>نشط</option>
                <option value="inactive" @selected(old('status', $participant?->status) === 'inactive')>غير نشط</option>
            </select>
            <span class="hint">الحساب غير النشط لا يمكنه تسجيل الدخول</span>
        </div>
        @if ($formAction === 'create')
            <div class="settings-field">
                <label for="password">كلمة المرور</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" required dir="ltr" style="text-align:right">
                    <button type="button" class="password-toggle js-password-toggle" tabindex="-1" aria-label="إظهار كلمة المرور" aria-pressed="false">
                        <svg class="eye-icon eye" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-icon eye-off" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                    </button>
                </div>
                <span class="hint">8 أحرف على الأقل</span>
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
        @else
            <div class="settings-field">
                <label>كلمة المرور</label>
                <input type="text" value="••••••••" readonly>
                <span class="hint">لتغييرها استخدم زر «تغيير كلمة المرور»</span>
            </div>
        @endif
    </div>

    <div style="display:flex;gap:10px;margin-top:24px">
        <button type="submit" class="primary-button">
            {{ $formAction === 'create' ? 'إنشاء الحساب' : 'حفظ التعديلات' }} ←
        </button>
        <a href="{{ route('admin.participants') }}" class="secondary-button">إلغاء</a>
    </div>
</form>

@endsection