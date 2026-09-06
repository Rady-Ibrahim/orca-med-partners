@extends('admin.pages.layout')

@section('content')
    <div class="admin-page-header">
        <div>
            <p class="eyebrow">إدارة النظام</p>
            <h1>الإعدادات</h1>
        </div>
    </div>

    @if ($items->isEmpty())
        <div class="empty-state panel"><span>⚙</span>
            <p>لا توجد إعدادات مخصصة</p>
        </div>
    @else
        <div class="panel table-panel compact-panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>المفتاح</th>
                            <th>القيمة</th>
                            <th>الوصف</th>
                            <th>تاريخ التحديث</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item['key'] }}</td>
                                <td>{{ $item['value'] }}</td>
                                <td>{{ $item['description'] }}</td>
                                <td>{{ $item['updated_at'] }}</td>
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
