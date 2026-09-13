@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">الحصة الرأسمالية</p>
        <h1>رأس المال</h1>
    </div>
</div>

<form class="page-filter-bar" method="GET" action="{{ route('admin.capital') }}">
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
            <option value="final" @selected(request('status') === 'final')>نهائي</option>
            <option value="draft" @selected(request('status') === 'draft')>مسودة</option>
        </select>
    </label>
    <div class="filter-actions">
        <button type="submit" class="primary-button">تطبيق</button>
        <a href="{{ route('admin.capital') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>▦</span><p>لا توجد لقطات رأسمالية</p></div>
@else
    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>تاريخ اللقطة</th>
                        <th>السنة</th>
                        <th>الشهر</th>
                        <th>إجمالي رأس المال</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>{{ $item['date'] }}</td>
                            <td>{{ $item['year'] }}</td>
                            <td>{{ $item['month'] }}</td>
                            <td class="numeric">{{ $item['total'] }} ر.س</td>
                            <td><span class="status-badge status-{{ $item['status'] }}">{{ $item['status'] === 'final' ? 'نهائي' : $item['status'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $items->links() }}</div>
    </div>
@endif
@endsection
