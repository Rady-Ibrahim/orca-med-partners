@extends('admin.pages.layout')

@section('content')
    <div class="admin-page-header">
        <div>
            <p class="eyebrow">مراجعة النشاط</p>
            <h1>سجل التدقيق</h1>
        </div>
    </div>

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
                            <th>النوع</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item['action'] }}</td>
                                <td>{{ $item['actor'] }}</td>
                                <td>{{ $item['entity'] }}</td>
                                <td><span class="status-badge status-info">{{ $item['action'] }}</span></td>
                                <td>{{ $item['created_at'] }}</td>
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
