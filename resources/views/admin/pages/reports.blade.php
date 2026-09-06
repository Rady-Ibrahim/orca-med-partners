@extends('admin.pages.layout')

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
            <span>المشاركون</span><strong>{{ number_format((float) $items['participants']) }}</strong>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon cyan">↗</div>
            <span>الاستثمارات</span><strong>{{ number_format((float) $items['investments'], 2) }} ر.س</strong>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon">▦</div><span>رأس المال</span><strong>{{ number_format((float) $items['capital'], 2) }}
                ر.س</strong>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon violet">⌁</div>
            <span>الأرباح</span><strong>{{ number_format((float) $items['profits'], 2) }} ر.س</strong>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon amber">◫</div><span>التسويات
                المدفوعة</span><strong>{{ number_format((float) $items['settlements'], 2) }} ر.س</strong>
        </article>
        <article class="kpi-card">
            <div class="kpi-icon green">◌</div><span>الأرصدة</span><strong>{{ number_format((float) $items['funds'], 2) }}
                ر.س</strong>
        </article>
    </div>
@endsection
