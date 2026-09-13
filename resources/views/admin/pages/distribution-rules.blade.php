@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">قواعد التشغيل</p>
        <h1>قواعد التوزيع</h1>
    </div>
</div>

<form class="page-filter-bar" method="GET" action="{{ route('admin.distribution-rules') }}">
    <label>
        الحالة
        <select name="status">
            <option value="">الكل</option>
            <option value="active" @selected(request('status') === 'active')>نشطة</option>
            <option value="inactive" @selected(request('status') === 'inactive')>غير نشطة</option>
        </select>
    </label>
    <label>
        السنة
        <input type="number" name="year" value="{{ request('year') }}" placeholder="2025" min="2020" max="2099">
    </label>
    <div class="filter-actions">
        <button type="submit" class="primary-button">تطبيق</button>
        <a href="{{ route('admin.distribution-rules') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>◎</span><p>لا توجد قواعد توزيع</p></div>
@else
    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>الوصف</th>
                        <th>سريانية من</th>
                        <th>سريانية حتى</th>
                        <th>الإدارة</th>
                        <th>الإهلاك</th>
                        <th>النمو</th>
                        <th>الحوافز</th>
                        <th>الموزع</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><strong>{{ $item['name'] }}</strong></td>
                            <td>{{ $item['effective_from'] }}</td>
                            <td>{{ $item['effective_to'] }}</td>
                            <td class="numeric">{{ $item['management'] }}</td>
                            <td class="numeric">{{ $item['depreciation'] }}</td>
                            <td class="numeric">{{ $item['growth'] }}</td>
                            <td class="numeric">{{ $item['incentive'] }}</td>
                            <td class="numeric">{{ $item['distributed'] }}</td>
                            <td><span class="status-badge status-{{ $item['status'] }}">{{ $item['status'] === 'active' ? 'نشطة' : 'غير نشطة' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $items->links() }}</div>
    </div>
@endif
@endsection
