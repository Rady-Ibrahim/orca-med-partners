@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">الأداء المالي</p>
        <h1>الأرباح الشهرية</h1>
    </div>
</div>

<form class="page-filter-bar" method="GET" action="{{ route('admin.monthly-profits') }}">
    <label>
        السنة
        <input type="number" name="year" value="{{ request('year') }}" placeholder="2025" min="2020" max="2099">
    </label>
    <label>
        الشهر
        <select name="month">
            <option value="">الكل</option>
            @foreach(range(1,12) as $m)
                <option value="{{ $m }}" @selected((int)request('month') === $m)>{{ $m }}</option>
            @endforeach
        </select>
    </label>
    <label>
        الحالة
        <select name="status">
            <option value="">الكل</option>
            <option value="draft" @selected(request('status') === 'draft')>مسودة</option>
            <option value="approved" @selected(request('status') === 'approved')>معتمد</option>
            <option value="superseded" @selected(request('status') === 'superseded')>مستبدل</option>
        </select>
    </label>
    <div class="filter-actions">
        <button type="submit" class="primary-button">تطبيق</button>
        <a href="{{ route('admin.monthly-profits') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>⌁</span><p>لا توجد أرباح شهرية</p></div>
@else
    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>الفترة</th>
                        <th>إجمالي الربح</th>
                        <th>رسوم الإدارة</th>
                        <th>الموزع للمشاركين</th>
                        <th>الحالة</th>
                        <th>تاريخ الاعتماد</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><strong>{{ $item['period'] }}</strong></td>
                            <td class="numeric">{{ $item['gross'] }} ر.س</td>
                            <td class="numeric">{{ $item['management'] }} ر.س</td>
                            <td class="numeric">{{ $item['distributed'] }} ر.س</td>
                            <td><span class="status-badge status-{{ $item['status'] }}">{{ match($item['status']) { 'draft' => 'مسودة', 'approved' => 'معتمد', 'superseded' => 'مستبدل', default => $item['status'] } }}</span></td>
                            <td class="muted">{{ $item['approved_at'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $items->links() }}</div>
    </div>
@endif
@endsection
