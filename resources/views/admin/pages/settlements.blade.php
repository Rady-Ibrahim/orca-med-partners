@extends('admin.pages.layout')

@section('content')
    <div class="admin-page-header">
        <div>
            <p class="eyebrow">التسويات السنوية</p>
            <h1>التسويات</h1>
        </div>
    </div>

    @if ($items->isEmpty())
        <div class="empty-state panel"><span>◫</span>
            <p>لا توجد تسويات</p>
        </div>
    @else
        <div class="panel table-panel compact-panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>السنة</th>
                            <th>المشاركون</th>
                            <th>الربح السنوي</th>
                            <th>المستحق</th>
                            <th>المدفوع</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item['year'] }}</td>
                                <td>{{ $item['participants'] }}</td>
                                <td class="numeric">{{ $item['annual_profit'] }} ر.س</td>
                                <td class="numeric">{{ $item['amount_due'] }} ر.س</td>
                                <td class="numeric">{{ $item['paid_amount'] }} ر.س</td>
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
