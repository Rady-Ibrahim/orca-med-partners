@extends('admin.pages.layout')

@section('content')
    <div class="admin-page-header">
        <div>
            <p class="eyebrow">الاحتياطيات</p>
            <h1>الصناديق</h1>
        </div>
    </div>

    @if ($items->isEmpty())
        <div class="empty-state panel"><span>◌</span>
            <p>لا توجد بيانات صناديق</p>
        </div>
    @else
        <div class="panel table-panel compact-panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>اسم الصندوق</th>
                            <th>الكود</th>
                            <th>الرصيد</th>
                            <th>الحالة</th>
                            <th>عدد الحركات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td>{{ $item['code'] }}</td>
                                <td class="numeric">{{ $item['balance'] }} ر.س</td>
                                <td><span class="status-badge status-{{ $item['status'] }}">{{ $item['status'] }}</span></td>
                                <td>{{ $item['transactions'] }}</td>
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
