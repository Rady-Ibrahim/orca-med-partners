@extends('admin.pages.layout')

@section('content')
    @php
        $kpis = $data['kpis'];
        $series = $data['series'];
        $distribution = $data['distribution'];
        $compounded = $data['compounded_share'];
        $logs = $data['logs'];
        $formatMoney = fn ($value) => \App\Support\DecimalFormatter::money($value);
        $countPercent = fn ($value) => \App\Support\DecimalFormatter::ratioPercent((string) $value, $series['max_count']);
        $amountPercent = fn ($value) => \App\Support\DecimalFormatter::ratioPercent((string) $value, $series['max_amount']);
        $start = 0;
        $doughnutStops = collect($distribution['items'])->map(function (array $d) use (&$start): string {
            $to = $start + (float) $d['percent'];
            $stop = $d['color'].' '.number_format($start, 2).'% '.number_format($to, 2).'%';
            $start = $to;

            return $stop;
        })->implode(', ');
    @endphp

    <div class="admin-page-header">
        <div>
            <p class="eyebrow">تحليلات الاستثمار</p>
            <h1>{{ $title }}</h1>
        </div>
        <div class="page-actions">
            <span class="text-link">نافذة ١٤ يوماً</span>
        </div>
    </div>

    <form class="page-filter-bar" method="GET" action="{{ route('admin.projection-analytics') }}">
        <label>بحث (المشارك / المبلغ / IP)
            <input name="search" value="{{ request('search') }}" placeholder="أحمد أو 100000">
        </label>
        <label>الفترة
            <select name="period_type">
                <option value="">الكل</option>
                @foreach (['month' => 'شهري', 'quarter' => 'ربع سنوي', 'semi_annual' => 'نصف سنوي', 'annual' => 'سنوي', 'years' => 'سنوات'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('period_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <div class="filter-actions">
            <button type="submit" class="primary-button">تطبيق</button>
            <a href="{{ route('admin.projection-analytics') }}" class="secondary-button">إعادة تعيين</a>
        </div>
    </form>

    <section class="kpi-grid" aria-label="مؤشرات التوقعات">
        <article class="kpi-card kpi-primary">
            <div class="kpi-icon">◈</div>
            <span>إجمالي عمليات التوقع</span>
            <strong>{{ number_format($kpis['total_searches']) }}</strong>
            <em>بحث توقع مسجل</em>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon cyan">≋</div>
            <span>متوسط المبلغ المبحوث عنه</span>
            <strong>{{ $kpis['average_target_amount_label'] }} <small>ر.س</small></strong>
            <em>لكل عملية توقع</em>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon violet">▤</div>
            <span>إجمالي رؤوس الأموال المستهدفة</span>
            <strong>{{ $kpis['total_targeted_capital_label'] }} <small>ر.س</small></strong>
            <em>مجموع مبالغ البحث</em>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon amber">◔</div>
            <span>أكثر فترة مطلوبة</span>
            <strong>{{ $kpis['most_popular_period']['label'] ?? '—' }}</strong>
            <em>
                @if ($kpis['most_popular_period'])
                    {{ number_format($kpis['most_popular_period']['count']) }} عملية · {{ $compounded['percent'] }}% مركّب
                @else
                    لا توجد بيانات
                @endif
            </em>
        </article>
    </section>

    <section class="dashboard-grid overview-grid" id="projection-charts">
        <article class="panel chart-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">عبر الزمن</p>
                    <h2>عمليات التوقع والمبالغ (يومي)</h2>
                </div>
                <span class="legend">
                    <i class="legend-blue"></i> عدد العمليات
                    <i class="legend-light"></i> المبالغ
                </span>
            </div>
            <div class="chart-wrap">
                <div class="y-axis">
                    <span>{{ $series['max_count'] }}</span>
                    <span>50%</span>
                    <span>0</span>
                </div>
                <div class="bars-area">
                    @forelse ($series['points'] as $point)
                        <div class="bar-group">
                            <div class="bar-pair">
                                <span class="bar bar-gross" style="height: {{ $countPercent($point['count']) }}%"
                                    title="{{ $point['full_label'] }}: {{ $point['count'] }} عملية"></span>
                                <span class="bar bar-distributed" style="height: {{ $amountPercent($point['amount']) }}%"
                                    title="{{ $point['full_label'] }}: {{ $formatMoney($point['amount']) }} ر.س"></span>
                            </div>
                            <small>{{ $point['label'] }}</small>
                        </div>
                    @empty
                        <div class="empty-state compact"><span>◌</span><p>لا توجد بيانات</p></div>
                    @endforelse
                </div>
            </div>
        </article>
        <article class="panel rule-panel">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">توزيع الفترات</p>
                    <h2>نسبة الفترات الزمنية</h2>
                </div>
            </div>
            <div class="doughnut-wrap">
                <div class="doughnut-holder">
                    <div class="doughnut" style="background: conic-gradient({{ $doughnutStops ?: '#e2e8f0' }});"></div>
                    <div class="doughnut-center">
                        <strong>{{ number_format($distribution['total']) }}</strong>
                        <small>عملية</small>
                    </div>
                </div>
                <div class="doughnut-legend">
                    @forelse ($distribution['items'] as $item)
                        <div class="rule-row">
                            <span class="rule-dot" style="background: {{ $item['color'] }};"></span>
                            <span>{{ $item['label'] }}</span>
                            <strong>{{ $item['share'] }}%</strong>
                        </div>
                    @empty
                        <div class="empty-state compact"><span>◌</span><p>لا توجد بيانات</p></div>
                    @endforelse
                </div>
            </div>
        </article>
    </section>

    <section class="panel table-panel compact-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">السجل</p>
                <h2>أحدث عمليات التوقع</h2>
            </div>
            <span class="legend">
                <i class="legend-blue"></i> {{ $compounded['total'] - $compounded['compounded'] }} بسيط
                <i class="legend-light"></i> {{ $compounded['compounded'] }} مركّب
            </span>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>المشارك</th>
                        <th>المبلغ المستهدف</th>
                        <th>الفترة</th>
                        <th>الأسلوب</th>
                        <th>الربح المتوقع</th>
                        <th>الرصيد المتوقع</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $row)
                        <tr>
                            <td class="muted">{{ $row['created_at'] }}</td>
                            <td>
                                <div class="person-cell">
                                    <div class="person-avatar">{{ mb_substr($row['participant'], 0, 1) }}</div>
                                    <div>
                                        <strong>{{ $row['participant'] }}</strong>
                                        <small>{{ $row['participant_id'] === null ? 'زائر' : 'مشارك #'.$row['participant_id'] }}</small>
                                    </div>
                                </div>
                            </td>
                            <td class="numeric">{{ $row['amount'] }} <small>ر.س</small></td>
                            <td>{{ $row['period_label'] }} ({{ $row['period_value'] }})</td>
                            <td>
                                <span class="status-badge {{ $row['is_compounded'] ? 'status-active' : '' }}">{{ $row['is_compounded'] ? 'مركّب' : 'بسيط' }}</span>
                            </td>
                            <td class="numeric">{{ $row['expected_net_profit'] }} <small>ر.س</small></td>
                            <td class="numeric">{{ $row['expected_total_balance'] }} <small>ر.س</small></td>
                            <td class="muted">{{ $row['ip_address'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state compact"><span>◌</span><p>لا توجد عمليات توقع بعد</p></div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $logs->links() }}</div>
    </section>

    <style>
        .doughnut-wrap { position: relative; display: grid; grid-template-columns: auto 1fr; gap: 20px; align-items: center; padding: 16px 8px; }
        .doughnut-holder { position: relative; width: 168px; height: 168px; }
        .doughnut { width: 168px; height: 168px; border-radius: 50%; }
        .doughnut-center { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; }
        .doughnut-center strong { font-size: 1.4rem; }
        .doughnut-center small { color: var(--text-muted, #94a3b8); }
        .doughnut-legend { display: flex; flex-direction: column; gap: 8px; }
    </style>
@endsection