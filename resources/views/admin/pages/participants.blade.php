@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">قاعدة المشاركين</p>
        <h1>المشاركون</h1>
    </div>
</div>

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
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $items->links() }}</div>
    </div>
@endif
@endsection
