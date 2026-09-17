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

    {{-- DIAGNOSTIC MARKER (remove after debugging) --}}
    <div style="display:none" data-diag-form-version="debug-2026-09-17-v1" data-diag-participant-id="{{ $participant?->getKey() }}"></div>

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
                <input id="password" name="password" type="password" required dir="ltr" style="text-align:right">
                <span class="hint">8 أحرف على الأقل</span>
                @error('password') <span class="hint" style="color:var(--red)">{{ $message }}</span> @enderror
            </div>
            <div class="settings-field">
                <label for="password_confirmation">تأكيد كلمة المرور</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required dir="ltr" style="text-align:right">
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