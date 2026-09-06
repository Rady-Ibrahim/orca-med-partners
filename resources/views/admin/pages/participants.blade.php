@extends('admin.pages.layout')

@section('content')
    <div class="admin-page-header">
        <div>
            <p class="eyebrow">قاعدة المشاركين</p>
            <h1>المشاركون</h1>
        </div>
        <div class="page-actions">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">العودة إلى لوحة التحكم</a>
        </div>
    </div>

    @if ($items->isEmpty())
        <div class="empty-state panel"><span>◉</span>
            <p>لا توجد بيانات مشاركين</p>
        </div>
    @else
        <div class="panel table-panel compact-panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>المشارك</th>
                            <th>اسم المستخدم</th>
                            <th>الحالة</th>
                            <th>الاستثمار</th>
                            <th>تاريخ الانضمام</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item['name'] }}</td>
                                <td>{{ $item['username'] }}</td>
                                <td><span class="status-badge status-{{ $item['status'] }}">{{ $item['status'] }}</span></td>
                                <td class="numeric">{{ $item['investment'] }} ر.س</td>
                                <td>{{ $item['joined'] }}</td>
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
