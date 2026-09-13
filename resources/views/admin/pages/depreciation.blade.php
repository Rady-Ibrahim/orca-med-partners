@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">الإهلاك</p>
        <h1>مذكرات الإهلاك</h1>
    </div>
</div>

<form class="page-filter-bar" method="GET" action="{{ route('admin.depreciation') }}">
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
        الصندوق
        <input name="fund" value="{{ request('fund') }}" placeholder="depreciation_fund...">
    </label>
    <div class="filter-actions">
        <button type="submit" class="primary-button">تطبيق</button>
        <a href="{{ route('admin.depreciation') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>⌇</span><p>لا توجد مذكرات إهلاك</p></div>
@else
    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>الفترة</th>
                        <th>المبلغ</th>
                        <th>النسبة</th>
                        <th>التاريخ</th>
                        <th>الوصف</th>
                        <th>الصندوق</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><strong>{{ $item['period'] }}</strong></td>
                            <td class="numeric">{{ $item['amount'] }} ر.س</td>
                            <td>{{ $item['rate'] }}</td>
                            <td class="muted">{{ $item['date'] }}</td>
                            <td>{{ $item['description'] }}</td>
                            <td>{{ $item['fund'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $items->links() }}</div>
    </div>
@endif
@endsection
