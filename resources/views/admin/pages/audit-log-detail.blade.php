@extends('admin.pages.layout')

@section('content')
    <div class="admin-page-header">
        <div>
            <p class="eyebrow">المراجعة والتتبع</p>
            <h1>{{ $title }}</h1>
        </div><a class="text-link" href="{{ route('admin.audit-logs') }}">العودة للسجل</a>
    </div>
    <div class="panel detail-grid">
        <dl>
            <dt>المستخدم</dt>
            <dd>{{ $item->actor_type }}:{{ $item->actor_id }}</dd>
        </dl>
        <dl>
            <dt>الحدث</dt>
            <dd>{{ $item->action }}</dd>
        </dl>
        <dl>
            <dt>الكائن</dt>
            <dd>{{ $item->auditable_type }}:{{ $item->auditable_id }}</dd>
        </dl>
        <dl>
            <dt>التاريخ</dt>
            <dd>{{ $item->created_at?->format('Y-m-d H:i:s') }}</dd>
        </dl>
        <section>
            <h2>القيم السابقة</h2>
            <pre>{{ json_encode($item->old_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </section>
        <section>
            <h2>القيم الجديدة</h2>
            <pre>{{ json_encode($item->new_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </section>
        <section>
            <h2>السياق</h2>
            <pre>{{ json_encode($item->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </section>
    </div>
@endsection
