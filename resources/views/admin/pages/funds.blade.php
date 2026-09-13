@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">الاحتياطيات المالية</p>
        <h1>الصناديق</h1>
    </div>
</div>

<form class="page-filter-bar" method="GET" action="{{ route('admin.funds') }}">
    <label>
        بحث (اسم / كود)
        <input name="search" value="{{ request('search') }}" placeholder="growth_fund...">
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
        <a href="{{ route('admin.funds') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>◌</span><p>لا توجد بيانات صناديق</p></div>
@else
    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>اسم الصندوق</th>
                        <th>الكود</th>
                        <th>الرصيد الحالي</th>
                        <th>الحالة</th>
                        <th>عدد الحركات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><strong>{{ $item['name'] }}</strong></td>
                            <td><code>{{ $item['code'] }}</code></td>
                            <td class="numeric">{{ $item['balance'] }} ر.س</td>
                            <td><span class="status-badge status-{{ $item['status'] }}">{{ $item['status'] === 'active' ? 'نشط' : 'غير نشط' }}</span></td>
                            <td>{{ $item['transactions'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $items->links() }}</div>
    </div>
@endif
@endsection
