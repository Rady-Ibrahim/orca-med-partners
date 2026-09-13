@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">الإشعارات</p>
        <h1>تنبيهات النظام</h1>
    </div>
</div>

<form class="page-filter-bar" method="GET" action="{{ route('admin.notifications') }}">
    <label>
        نوع الإشعار
        <input name="type" value="{{ request('type') }}" placeholder="investment_approved...">
    </label>
    <label>
        حالة القراءة
        <select name="is_read">
            <option value="">الكل</option>
            <option value="0" @selected(request('is_read') === '0')>غير مقروء</option>
            <option value="1" @selected(request('is_read') === '1')>مقروء</option>
        </select>
    </label>
    <label>
        المشارك
        <input name="participant" value="{{ request('participant') }}" placeholder="أحمد...">
    </label>
    <div class="filter-actions">
        <button type="submit" class="primary-button">تطبيق</button>
        <a href="{{ route('admin.notifications') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>◍</span><p>لا توجد إشعارات</p></div>
@else
    <div class="panel list-panel">
        @foreach ($items as $item)
            <div class="list-row">
                <div class="list-indicator {{ $item['is_read'] ? 'read' : 'unread' }}"></div>
                <div class="list-copy">
                    <h3>{{ $item['title'] }}</h3>
                    <p>{{ $item['body'] }}</p>
                    <small style="color:#7899b0;font-size:10px">المشارك: {{ $item['participant'] }}</small>
                </div>
                <div class="list-meta">
                    <span>{{ $item['created_at'] }}</span>
                    <span class="status-badge status-{{ $item['is_read'] ? 'approved' : 'draft' }}">{{ $item['status'] }}</span>
                    <span style="font-size:9px;color:#9aacba">{{ $item['type'] }}</span>
                </div>
            </div>
        @endforeach
    </div>
    <div class="pagination-wrap" style="padding:12px 22px">{{ $items->links() }}</div>
@endif
@endsection
