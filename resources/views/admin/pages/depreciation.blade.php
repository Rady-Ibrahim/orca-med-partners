@extends('admin.pages.layout')

@section('content')
    <div class="admin-page-header">
        <div>
            <p class="eyebrow">الإهلاك</p>
            <h1>مذكرات الإهلاك</h1>
        </div>
    </div>

    @if ($items->isEmpty())
        <div class="empty-state panel"><span>⌇</span>
            <p>لا توجد مذكرات إهلاك</p>
        </div>
    @else
        <div class="panel table-panel compact-panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>الفترة</th>
                            <th>المبلغ</th>
                            <th>السعر</th>
                            <th>التاريخ</th>
                            <th>الوصف</th>
                            <th>الصندوق</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item['period'] }}</td>
                                <td class="numeric">{{ $item['amount'] }} ر.س</td>
                                <td>{{ $item['rate'] }}</td>
                                <td>{{ $item['date'] }}</td>
                                <td>{{ $item['description'] }}</td>
                                <td>{{ $item['fund'] }}</td>
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
