@extends('admin.pages.layout')

@section('content')
    <div class="admin-page-header">
        <div>
            <p class="eyebrow">مراجعة النشاط</p>
            <h1>سجل التدقيق</h1>
        </div>
    </div>

    <form class="panel report-filters" method="GET">
        <label>المستخدم<input name="actor" value="{{ request('actor') }}"></label>
        <label>الحدث<input name="action" value="{{ request('action') }}"></label>
        <label>نوع الكائن<input name="entity" value="{{ request('entity') }}"></label>
        <label>معرف الكائن<input name="entity_id" value="{{ request('entity_id') }}"></label>
        <label>من تاريخ<input type="date" name="date_from" value="{{ request('date_from') }}"></label>
        <label>إلى تاريخ<input type="date" name="date_to" value="{{ request('date_to') }}"></label>
        <button class="primary-button" type="submit">تطبيق الفلاتر</button>
    </form>

    @if ($items->isEmpty())
        <div class="empty-state panel"><span>◌</span>
            <p>لا توجد سجلات تدقيق</p>
        </div>
    @else
        <div class="panel table-panel compact-panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>الحدث</th>
                            <th>المستخدم</th>
                            <th>الكائن</th>
                            <th>الأداة</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td><a class="text-link"
                                        href="{{ route('admin.audit-logs.show', $item->id) }}">{{ \App\Support\AuditLogPresenter::action($item->action) }}</a>
                                </td>
                                <td>{{ \App\Support\AuditLogPresenter::actor($item->actor_type, (string) $item->actor_id) }}
                                </td>
                                <td>{{ \App\Support\AuditLogPresenter::entity($item->auditable_type) }} #{{ $item->auditable_id }}
                                </td>
                                <td><span class="status-badge status-info">{{ \App\Support\AuditLogPresenter::action($item->action) }}</span>
                                </td>
                                <td>{{ $item->created_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">
                {{ $items->links() }}
            </div>
        </div>
    @endif
@endsection
