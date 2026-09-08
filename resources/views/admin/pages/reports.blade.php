@extends('admin.pages.layout')

@php($money = fn($value) => \App\Support\DecimalFormatter::money($value))

@section('content')
    <div class="admin-page-header">
        <div>
            <p class="eyebrow">التقارير</p>
            <h1>ملخص الأداء</h1>
        </div>
    </div>

    <div class="kpi-grid">
        <article class="kpi-card">
            <div class="kpi-icon blue">◉</div>
            <span>المشاركون</span><strong>{{ number_format((int) $items['participants']) }}</strong>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon cyan">↗</div>
            <span>الاستثمارات</span><strong>{{ $money($items['investments']) }} ر.س</strong>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon">▦</div><span>رأس المال</span><strong>{{ $money($items['capital']) }}
                ر.س</strong>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon violet">⌁</div>
            <span>الأرباح</span><strong>{{ $money($items['profits']) }} ر.س</strong>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon amber">◫</div><span>التسويات
                المدفوعة</span><strong>{{ $money($items['settlements']) }} ر.س</strong>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon green">◌</div><span>الأرصدة</span><strong>{{ $money($items['funds']) }}
                ر.س</strong>
        </article>
    </div>

    <section class="panel report-index-grid">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">مركز التقارير</p>
                <h2>التقارير التفصيلية</h2>
            </div>
        </div>
        @foreach ([['participants', 'المشاركون'], ['investments', 'الاستثمارات'], ['capital', 'رأس المال'], ['monthly-profits', 'الأرباح الشهرية'], ['annual-profits', 'الأرباح السنوية'], ['distribution', 'التوزيعات'], ['funds', 'الصناديق'], ['fund-transactions', 'حركات الصناديق'], ['depreciation', 'الإهلاك'], ['settlements', 'التسويات السنوية'], ['due-paid', 'المستحق والمدفوع'], ['capital-growth', 'نمو رأس المال']] as [$key, $label])
            <a class="report-index-link"
                href="{{ route('admin.reports.show', $key) }}"><span>{{ $label }}</span><b>←</b></a>
        @endforeach
    </section>
@endsection
