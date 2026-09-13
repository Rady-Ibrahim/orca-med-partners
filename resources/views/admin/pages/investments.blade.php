@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">المشاريع المالية</p>
        <h1>الاستثمارات</h1>
    </div>
</div>

<form class="page-filter-bar" method="GET" action="{{ route('admin.investments') }}">
    <label>
        اسم المشارك
        <input name="participant" value="{{ request('participant') }}" placeholder="أحمد...">
    </label>
    <label>
        الحالة
        <select name="status">
            <option value="">الكل</option>
            <option value="approved" @selected(request('status') === 'approved')>معتمد</option>
            <option value="pending" @selected(request('status') === 'pending')>قيد الانتظار</option>
            <option value="rejected" @selected(request('status') === 'rejected')>مرفوض</option>
        </select>
    </label>
    <label>
        من تاريخ
        <input type="date" name="date_from" value="{{ request('date_from') }}">
    </label>
    <label>
        إلى تاريخ
        <input type="date" name="date_to" value="{{ request('date_to') }}">
    </label>
    <div class="filter-actions">
        <button type="submit" class="primary-button">تطبيق</button>
        <a href="{{ route('admin.investments') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>↗</span><p>لا توجد بيانات استثمارات</p></div>
@else
    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>المشارك</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>تاريخ الاستثمار</th>
                        <th>ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><strong>{{ $item['participant'] }}</strong></td>
                            <td class="numeric">{{ $item['amount'] }} ر.س</td>
                            <td><span class="status-badge status-{{ $item['status'] }}">{{ match($item['status']) { 'approved' => 'معتمد', 'pending' => 'قيد الانتظار', 'rejected' => 'مرفوض', default => $item['status'] } }}</span></td>
                            <td class="muted">{{ $item['date'] }}</td>
                            <td>{{ $item['notes'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $items->links() }}</div>
    </div>
@endif
@endsection
