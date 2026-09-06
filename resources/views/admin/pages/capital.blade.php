@extends('admin.pages.layout')

@section('content')
    <div class="admin-page-header">
        <div>
            <p class="eyebrow">الحصة الرأسمالية</p>
            <h1>رأس المال</h1>
        </div>
    </div>

    @if ($items->isEmpty())
        <div class="empty-state panel"><span>▦</span>
            <p>لا توجد لقطات رأسمالية</p>
        </div>
    @else
        <div class="panel table-panel compact-panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>التاريخ</th>
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
                                <td><span class="status-badge status-{{ $item['status'] }}">{{ $item['status'] }}</span></td>
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
