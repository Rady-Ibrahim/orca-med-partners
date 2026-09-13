<!doctype html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            direction: rtl;
            font-size: 11px;
            color: #0f172a
        }

        h1 {
            text-align: right;
            font-size: 16px;
            margin: 0 0 4px 0
        }

        .company {
            color: #475569;
            font-size: 12px;
            margin-bottom: 20px
        }

        .meta {
            color: #64748b;
            font-size: 10px;
            margin-bottom: 16px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 10px
        }

        h2 {
            font-size: 13px;
            margin: 18px 0 6px 0;
            color: #0f172a
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px
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

        .total {
            font-weight: bold
        }

        .signature {
            margin-top: 40px;
            color: #475569;
            font-size: 10px;
            border-top: 1px dashed #94a3b8;
            padding-top: 8px
        }
    </style>
</head>

<body>
    <h1>كشف التسوية السنوي — ORCA MED</h1>
    <div class="company">منصة الشريك المستثمر · المستند يصدر بشكل رسمي ومؤرشف</div>

    <div class="meta">
        المستثمر: {{ $participantName }} ({{ $participantUsername }}) |
        السنة: {{ $year ?? 'كل السنوات' }} |
        تاريخ الإصدار: {{ $generatedAt }}
    </div>

    @forelse ($rows as $row)
        <h2>تسوية عام {{ $row['year'] }} — النسخة {{ $row['version'] }} ({{ $row['status'] }})</h2>
        <table>
            <thead>
                <tr>
                    <th>الربح السنوي</th>
                    <th>حصة الصندوق</th>
                    <th>المستحق</th>
                    <th>المدفوع</th>
                    <th>المتبقي</th>
                    <th>حالة الدفع</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $row['profit_share'] }}</td>
                    <td>{{ $row['fund_share'] }}</td>
                    <td>{{ $row['amount_due'] }}</td>
                    <td>{{ $row['paid_amount'] }}</td>
                    <td>{{ $row['remaining'] }}</td>
                    <td>{{ $row['payment_status'] }}</td>
                </tr>
            </tbody>
        </table>

        @if (! empty($row['payments']))
            <h2>سجل التحويلات</h2>
            <table>
                <thead>
                    <tr>
                        <th>المبلغ</th>
                        <th>التاريخ</th>
                        <th>طريقة الدفع</th>
                        <th>المرجع</th>
                        <th>الإيصال</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($row['payments'] as $payment)
                        <tr>
                            <td>{{ $payment['amount'] }}</td>
                            <td>{{ $payment['paid_at'] }}</td>
                            <td>{{ $payment['payment_method'] }}</td>
                            <td>{{ $payment['reference'] }}</td>
                            <td>{{ $payment['receipt_available'] ? 'متاح' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @empty
        <p>لا توجد تسويات في هذا النطاق.</p>
    @endforelse

    <div class="signature">
        هذا المستند صادر إلكترونياً من منصة ORCA MED ولا يتطلب توقيعاً فعلياً للاعتماد.
        للمراجعة أو الاعتراض، يرجى التواصل مع فريق الدعم خلال 30 يوماً من تاريخ الإصدار.
    </div>
</body>

</html>