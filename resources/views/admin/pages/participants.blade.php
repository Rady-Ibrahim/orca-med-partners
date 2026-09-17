@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">قاعدة المشاركين</p>
        <h1>المشاركون</h1>
    </div>
    <div class="page-actions">
        <button type="button" class="primary-button" data-modal-open="modal-participant-create">+ إضافة مشارك جديد</button>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form class="page-filter-bar" method="GET" action="{{ route('admin.participants') }}">
    <label>
        بحث (الاسم / المستخدم)
        <input name="search" value="{{ request('search') }}" placeholder="أحمد...">
    </label>
    <label>
        الحالة
        <select name="status">
            <option value="">الكل</option>
            <option value="active" @selected(request('status') === 'active')>نشط</option>
            <option value="inactive" @selected(request('status') === 'inactive')>غير نشط</option>
        </select>
    </label>
    <div class="filter-actions">
        <button type="submit" class="primary-button">تطبيق</button>
        <a href="{{ route('admin.participants') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>◉</span><p>لا توجد بيانات مشاركين</p></div>
@else
    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>المشارك</th>
                        <th>اسم المستخدم</th>
                        <th>البريد الإلكتروني</th>
                        <th>الحالة</th>
                        <th>الاستثمار</th>
                        <th>تاريخ الانضمام</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><strong>{{ $item['name'] }}</strong></td>
                            <td>{{ $item['username'] }}</td>
                            <td>{{ $item['email'] }}</td>
                            <td><span class="status-badge status-{{ $item['status'] }}">{{ $item['status'] === 'active' ? 'نشط' : 'غير نشط' }}</span></td>
                            <td class="numeric">{{ $item['investment'] }} ر.س</td>
                            <td class="muted">{{ $item['joined'] }}</td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="action-edit" data-fill-modal="modal-participant-edit"
                                        data-action-url="{{ route('admin.participants.update', $item['id']) }}"
                                        data-edit='@json($item["edit_payload"])'>تعديل</button>
                                    <a class="action-password" href="{{ route('admin.participants.password', $item['id']) }}">كلمة المرور</a>
                                    <button type="button" class="action-danger" data-post
                                        data-url="{{ route('admin.participants.revoke-tokens', $item['id']) }}"
                                        data-confirm="سيتم إلغاء جميع رموز الوصول النشطة للمشارك «{{ $item['name'] }}». متابعة؟">إلغاء الرموز</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $items->links() }}</div>
    </div>
@endif

{{-- ── إضافة مشارك ── --}}
<div class="modal-backdrop" id="modal-participant-create" hidden>
    <div class="modal-card wide">
        <div class="modal-header">
            <h3>+ إضافة مشارك جديد</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="{{ route('admin.participants.store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="om-field">
                        <label for="p_first_name">الاسم الأول</label>
                        <input id="p_first_name" name="first_name" required>
                    </div>
                    <div class="om-field">
                        <label for="p_last_name">اسم العائلة</label>
                        <input id="p_last_name" name="last_name" required>
                    </div>
                    <div class="om-field">
                        <label for="p_username">اسم المستخدم</label>
                        <input id="p_username" name="username" required dir="ltr">
                    </div>
                    <div class="om-field">
                        <label for="p_email">البريد الإلكتروني</label>
                        <input id="p_email" name="email" type="email" dir="ltr">
                    </div>
                    <div class="om-field">
                        <label for="p_password">كلمة المرور</label>
                        <input id="p_password" name="password" type="password" required minlength="8" dir="ltr" autocomplete="new-password">
                        <span class="hint">8 أحرف على الأقل</span>
                    </div>
                    <div class="om-field">
                        <label for="p_password_confirmation">تأكيد كلمة المرور</label>
                        <input id="p_password_confirmation" name="password_confirmation" type="password" required dir="ltr" autocomplete="new-password">
                    </div>
                    <div class="om-field">
                        <label for="p_status">الحالة</label>
                        <select id="p_status" name="status" required>
                            <option value="active" selected>نشط</option>
                            <option value="inactive">غير نشط</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>إنشاء الحساب</button>
            </div>
        </form>
    </div>
</div>

{{-- ── تعديل مشارك ── --}}
<div class="modal-backdrop" id="modal-participant-edit" hidden>
    <div class="modal-card">
        <div class="modal-header">
            <h3>تعديل بيانات المشارك</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="" method="POST" novalidate>
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="om-field">
                        <label for="pe_first_name">الاسم الأول</label>
                        <input id="pe_first_name" name="first_name">
                    </div>
                    <div class="om-field">
                        <label for="pe_last_name">اسم العائلة</label>
                        <input id="pe_last_name" name="last_name">
                    </div>
                    <div class="om-field">
                        <label for="pe_username">اسم المستخدم</label>
                        <input id="pe_username" name="username" dir="ltr">
                    </div>
                    <div class="om-field">
                        <label for="pe_email">البريد الإلكتروني</label>
                        <input id="pe_email" name="email" type="email" dir="ltr">
                    </div>
                    <div class="om-field">
                        <label for="pe_status">الحالة</label>
                        <select id="pe_status" name="status">
                            <option value="active">نشط</option>
                            <option value="inactive">غير نشط</option>
                        </select>
                    </div>
                    <div class="om-field">
                        <label>كلمة المرور</label>
                        <input type="text" value="••••••••" readonly>
                        <span class="hint">تُغيّر من صفحة كلمة المرور</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>
@endsection