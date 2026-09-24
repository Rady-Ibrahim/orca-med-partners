<!doctype html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            direction: rtl;
            font-size: 10px
        }

        h1 {
            text-align: right
        }

        table {
            width: 100%;
            border-collapse: collapse
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 5px;
            text-align: right
        }

        th {
            background: #eaf5ff
        }

        .meta {
            color: #64748b;
            margin-bottom: 12px
        }
    </style>
</head>

<body>
    <h1>{{ $title }}</h1>
    <div class="meta">تاريخ الإنشاء: {{ now()->format('Y-m-d H:i') }} | الفلاتر:
        {{ json_encode($filters, JSON_UNESCAPED_UNICODE) }}</div>
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
            @empty<tr>
                    <td colspan="{{ count($columns) }}">لا توجد بيانات</td>
                </tr>
            @endforelse
        </tbody>
        @if (! empty($reportTotals))
            <tfoot>
                <tr>
                    @foreach ($columns as $key => $column)
                        <td><strong>{{ $reportTotals[$key] ?? '—' }}</strong></td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
</body>

</html>
