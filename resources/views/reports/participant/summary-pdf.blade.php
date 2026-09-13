<!doctype html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            direction: rtl;
            font-size: 10px;
            color: #0f172a
        }

        h1 {
            text-align: right;
            font-size: 15px;
            margin: 0 0 4px 0
        }

        .company {
            color: #475569;
            font-size: 12px;
            margin-bottom: 16px
        }

        .meta {
            color: #64748b;
            font-size: 10px;
            margin-bottom: 12px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 8px
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
            background: #eef4ff
        }
    </style>
</head>

<body>
    <h1>{{ $title }}</h1>
    <div class="company">منصة الشريك المستثمر — ORCA MED</div>
    <div class="meta">المستثمر: {{ $participantName }} ({{ $participantUsername }}) |
        السنة: {{ $year ?? 'كل السنوات' }} | تاريخ الإصدار: {{ $generatedAt }}</div>

    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $key => $value)
                        <td>{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headings) }}">لا توجد بيانات</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>