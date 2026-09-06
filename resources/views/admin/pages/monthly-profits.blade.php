@extends('admin.pages.layout')

@section('content')
    <div class="admin-page-header">
        <div>
            <p class="eyebrow">الأداء المالي</p>
            <h1>الأرباح الشهرية</h1>
        </div>
    </div>

    @if ($items->isEmpty())
        <div class="empty-state panel"><span>⌁</span>
            <p>لا توجد أرباح شهرية</p>
        </div>
    @else
        <div class="panel table-panel compact-panel">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>الفترة</th>
                            <th>إجمالي الربح</th>
                            <th>الموزع</th>
                            <th>الحالة</th>
                            <th>اعتمد في</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item['period'] }}</td>
                                <td class="numeric">{{ $item['gross'] }} ر.س</td>
                                <td class="numeric">{{ $item['distributed'] }} ر.س</td>
                                <td><span class="status-badge status-{{ $item['status'] }}">{{ $item['status'] }}</span></td>
                                <td>{{ $item['approved_at'] ?? '—' }}</td>
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
