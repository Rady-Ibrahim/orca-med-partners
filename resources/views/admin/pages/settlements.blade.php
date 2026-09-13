@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">التسويات السنوية</p>
        <h1>التسويات</h1>
    </div>
</div>

<form class="page-filter-bar" method="GET" action="{{ route('admin.settlements') }}">
    <label>
        السنة
        <input type="number" name="year" value="{{ request('year') }}" placeholder="2025" min="2020" max="2099">
    </label>
    <label>
        الحالة
        <select name="status">
            <option value="">الكل</option>
            <option value="draft" @selected(request('status') === 'draft')>مسودة</option>
            <option value="approved" @selected(request('status') === 'approved')>معتمدة</option>
            <option value="paid" @selected(request('status') === 'paid')>مدفوعة</option>
            <option value="cancelled" @selected(request('status') === 'cancelled')>ملغاة</option>
        </select>
    </label>
    <div class="filter-actions">
        <button type="submit" class="primary-button">تطبيق</button>
        <a href="{{ route('admin.settlements') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>◫</span><p>لا توجد تسويات</p></div>
@else
    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>السنة</th>
                        <th>عدد المشاركين</th>
                        <th>إجمالي الربح الموزع</th>
                        <th>المستحق</th>
                        <th>المدفوع</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><strong>{{ $item['year'] }}</strong></td>
                            <td>{{ $item['participants'] }}</td>
                            <td class="numeric">{{ $item['annual_profit'] }} ر.س</td>
                            <td class="numeric">{{ $item['amount_due'] }} ر.س</td>
                            <td class="numeric">{{ $item['paid_amount'] }} ر.س</td>
                            <td><span class="status-badge status-{{ $item['status'] }}">{{ match($item['status']) { 'draft' => 'مسودة', 'approved' => 'معتمدة', 'paid' => 'مدفوعة', 'cancelled' => 'ملغاة', 'superseded' => 'مستبدلة', default => $item['status'] } }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $items->links() }}</div>
    </div>
@endif
@endsection
