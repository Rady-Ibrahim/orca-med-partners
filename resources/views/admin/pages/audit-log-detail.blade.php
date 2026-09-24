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
            <dd>{{ \App\Support\AuditLogPresenter::actor($item->actor_type, (string) $item->actor_id) }}</dd>
        </dl>
        <dl>
            <dt>الحدث</dt>
            <dd>{{ \App\Support\AuditLogPresenter::action($item->action) }}</dd>
        </dl>
        <dl>
            <dt>الكائن</dt>
            <dd>{{ \App\Support\AuditLogPresenter::entity($item->auditable_type) }} #{{ $item->auditable_id }}</dd>
        </dl>
        <dl>
            <dt>التاريخ</dt>
            <dd>{{ $item->created_at?->format('Y-m-d H:i:s') }}</dd>
        </dl>
        @if (! empty($item->ip_address))
            <dl>
                <dt>عنوان IP</dt>
                <dd>{{ $item->ip_address }}</dd>
            </dl>
        @endif
        @if ($item->old_values)
            <section>
                <h2>القيم السابقة</h2>
                <pre>{{ \App\Support\AuditLogPresenter::values($item->old_values) }}</pre>
            </section>
        @endif
        @if ($item->new_values)
            <section>
                <h2>القيم الجديدة</h2>
                <pre>{{ \App\Support\AuditLogPresenter::values($item->new_values) }}</pre>
            </section>
        @endif
        @if ($item->metadata)
            <section>
                <h2>السياق</h2>
                <pre>{{ \App\Support\AuditLogPresenter::values($item->metadata) }}</pre>
            </section>
        @endif
    </div>
@endsection
