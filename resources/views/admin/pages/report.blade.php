@extends('admin.pages.layout')

@section('content')
    <style>
        .table-panel tfoot td {
            background: #f4ecd9;
            color: #7a5c0e;
            font-weight: 700;
            border-top: 2px solid #d4af37;
        }

        .table-panel tfoot td {
            position: sticky;
            bottom: 0;
        }
    </style>

    <div class="admin-page-header">
        <div>
            <p class="eyebrow">التقارير المالية</p>
            <h1>{{ $title }}</h1>
        </div>
        <div class="page-actions">
            <a class="text-link"
                href="{{ route('admin.reports.export.excel', array_merge(['report' => $report], request()->query())) }}">تصدير
                Excel</a>
            <a class="text-link"
                href="{{ route('admin.reports.export.pdf', array_merge(['report' => $report], request()->query())) }}">تصدير
                PDF</a>
        </div>
    </div>

    <form class="panel report-filters" method="GET" action="{{ route('admin.reports.show', ['report' => $report]) }}">
        <label>السنة<input name="year" value="{{ request('year') }}" inputmode="numeric"></label>
        <label>الشهر<input name="month" value="{{ request('month') }}" inputmode="numeric"></label>
        <label>المشارك<input name="participant_id" value="{{ request('participant_id') }}" inputmode="numeric"></label>
        <label>الحالة<input name="status" value="{{ request('status') }}"></label>
        <label>من تاريخ<input type="date" name="date_from" value="{{ request('date_from') }}"></label>
        <label>إلى تاريخ<input type="date" name="date_to" value="{{ request('date_to') }}"></label>
        <label>الصندوق<input name="fund_id" value="{{ request('fund_id') }}" inputmode="numeric"></label>
        <label>نوع الحركة<input name="transaction_type" value="{{ request('transaction_type') }}"></label>
        <button class="primary-button" type="submit">تطبيق الفلاتر</button>
    </form>

    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            @foreach ($columns as $key => $column)
                                <td>{{ is_array($row[$key] ?? null) ? json_encode($row[$key], JSON_UNESCAPED_UNICODE) : $row[$key] ?? '—' }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}">
                                <div class="empty-state compact"><span>◌</span>
                                    <p>لا توجد بيانات لهذا التقرير</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($reportTotals)
                    <tfoot>
                        <tr>
                            @foreach ($columns as $key => $column)
                                <td class="totals-cell">{{ $reportTotals[$key] ?? '—' }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <div class="pagination-wrap">{{ $items->links() }}</div>
    </div>
@endsection
