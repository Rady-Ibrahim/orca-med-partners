@extends('admin.pages.layout')

@section('content')
    <div class="admin-page-header">
        <div>
            <p class="eyebrow">الإشعارات</p>
            <h1>تنبيهات النظام</h1>
        </div>
    </div>

    @if ($items->isEmpty())
        <div class="empty-state panel"><span>◍</span>
            <p>لا توجد إشعارات</p>
        </div>
    @else
        <div class="panel list-panel">
            @foreach ($items as $item)
                @php
                    $isRead =
                        strtolower((string) ($item['status'] ?? '')) === 'read' ||
                        strtolower((string) ($item['status'] ?? '')) === 'مقروء';
                @endphp
                <div class="list-row">
                    <div class="list-indicator {{ $isRead ? 'read' : 'unread' }}"></div>
                    <div class="list-copy">
                        <h3>{{ $item['title'] }}</h3>
                        <p>{{ $item['body'] }}</p>
                    </div>
                    <div class="list-meta">
                        <span>{{ $item['created_at'] }}</span>
                        <span class="status-badge status-{{ $item['type'] }}">{{ $item['type'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
