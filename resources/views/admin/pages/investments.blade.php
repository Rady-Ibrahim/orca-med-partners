@extends('admin.pages.layout')

@section('content')
    <div class="admin-page-header">
        <div>
            <p class="eyebrow">المشاريع المالية</p>
            <h1>الاستثمارات</h1>
        </div>
    </div>

    @if ($items->isEmpty())
        <div class="empty-state panel"><span>↗</span>
            <p>لا توجد بيانات استثمارات</p>
        </div>
    @else
        <div class="panel table-panel compact-panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>المشارك</th>
                            <th>المبلغ</th>
                            <th>الحالة</th>
                            <th>التاريخ</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item['participant'] }}</td>
                                <td class="numeric">{{ $item['amount'] }} ر.س</td>
                                <td><span class="status-badge status-{{ $item['status'] }}">{{ $item['status'] }}</span></td>
                                <td>{{ $item['date'] }}</td>
                                <td>{{ $item['notes'] }}</td>
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
