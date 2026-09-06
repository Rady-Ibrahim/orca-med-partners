@extends('admin.pages.layout')

@section('content')
    <div class="admin-page-header">
        <div>
            <p class="eyebrow">قواعد التشغيل</p>
            <h1>قواعد التوزيع</h1>
        </div>
    </div>

    @if ($items->isEmpty())
        <div class="empty-state panel"><span>◎</span>
            <p>لا توجد قواعد توزيع</p>
        </div>
    @else
        <div class="panel table-panel compact-panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>الإدارة</th>
                            <th>الإهلاك</th>
                            <th>الموزع</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td class="numeric">{{ $item['management'] }}</td>
                                <td class="numeric">{{ $item['depreciation'] }}</td>
                                <td class="numeric">{{ $item['distributed'] }}</td>
                                <td><span
                                        class="status-badge status-{{ strtolower(str_replace(' ', '-', (string) ($item['status'] ?? 'inactive'))) }}">{{ $item['status'] }}</span>
                                </td>
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
