@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">الأداء المالي</p>
        <h1>الأرباح الشهرية</h1>
    </div>
    <div class="page-actions">
        <button type="button" class="primary-button" data-modal-open="modal-profit-create">+ إنشاء أرباح شهرية</button>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form class="page-filter-bar" method="GET" action="{{ route('admin.monthly-profits') }}">
    <label>
        السنة
        <input type="number" name="year" value="{{ request('year') }}" placeholder="2025" min="2020" max="2099">
    </label>
    <label>
        الشهر
        <select name="month">
            <option value="">الكل</option>
            @foreach(range(1,12) as $m)
                <option value="{{ $m }}" @selected((int)request('month') === $m)>{{ $m }}</option>
            @endforeach
        </select>
    </label>
    <label>
        الحالة
        <select name="status">
            <option value="">الكل</option>
            <option value="draft" @selected(request('status') === 'draft')>مسودة</option>
            <option value="approved" @selected(request('status') === 'approved')>معتمد</option>
            <option value="superseded" @selected(request('status') === 'superseded')>مستبدل</option>
        </select>
    </label>
    <div class="filter-actions">
        <button type="submit" class="primary-button">تطبيق</button>
        <a href="{{ route('admin.monthly-profits') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>⌁</span><p>لا توجد أرباح شهرية</p></div>
@else
    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>الفترة</th>
                        <th>إجمالي الربح</th>
                        <th>رسوم الإدارة</th>
                        <th>الموزع للمشاركين</th>
                        <th>الحالة</th>
                        <th>تاريخ الاعتماد</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><strong>{{ $item['period'] }}</strong></td>
                            <td class="numeric">{{ $item['gross'] }} ر.س</td>
                            <td class="numeric">{{ $item['management'] }} ر.س</td>
                            <td class="numeric">{{ $item['distributed'] }} ر.س</td>
                            <td><span class="status-badge status-{{ $item['status'] }}">{{ match($item['status']) { 'draft' => 'مسودة', 'approved' => 'معتمد', 'superseded' => 'مستبدل', default => $item['status'] } }}</span></td>
                            <td class="muted">{{ $item['approved_at'] ?? '—' }}</td>
                            <td>
                                <div class="row-actions">
                                    @if ($item['status'] === 'draft')
                                        <button type="button" class="action-approve" data-post
                                            data-url="{{ route('admin.monthly-profits.approve', $item['id']) }}"
                                            data-confirm="هل أنت متأكد من اعتماد فترة أرباح {{ $item['period'] }}؟">اعتماد</button>
                                    @elseif ($item['status'] === 'approved')
                                        <button type="button" class="action-neutral" data-modal-open="modal-profit-revise"
                                            data-action-url="{{ route('admin.monthly-profits.revise', $item['id']) }}"
                                            data-prefill='{"gross_profit":"{{ $item['gross_raw'] }}"}'>إنشاء مراجعة</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $items->links() }}</div>
    </div>
@endif

{{-- ── إنشاء أرباح شهرية ── --}}
<div class="modal-backdrop" id="modal-profit-create" hidden>
    <div class="modal-card">
        <div class="modal-header">
            <h3>+ إنشاء أرباح شهرية</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="{{ route('admin.monthly-profits.store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="om-field">
                        <label for="profit_year">السنة</label>
                        <input id="profit_year" name="year" type="number" required min="2000" max="2100"
                            value="{{ now()->year }}">
                    </div>
                    <div class="om-field">
                        <label for="profit_month">الشهر</label>
                        <select id="profit_month" name="month" required>
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}" @selected($m === (int) now()->format('n'))>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="om-field full">
                        <label for="profit_gross">إجمالي الربح (ر.س)</label>
                        <input id="profit_gross" name="gross_profit" type="number" required min="0" step="0.01"
                            placeholder="0.00">
                    </div>
                    <div class="om-field">
                        <label for="profit_snapshot">لقطة رأس المال</label>
                        <select id="profit_snapshot" name="capital_snapshot_id" required>
                            <option value="">اختر لقطة...</option>
                            @foreach ($capitalSnapshots as $snapshot)
                                <option value="{{ $snapshot['id'] }}">
                                    {{ $snapshot['period'] }} ({{ $snapshot['status'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="om-field">
                        <label for="profit_rule">قاعدة التوزيع</label>
                        <select id="profit_rule" name="distribution_rule_id" required>
                            <option value="">اختر قاعدة...</option>
                            @foreach ($distributionRules as $rule)
                                <option value="{{ $rule['id'] }}">{{ $rule['label'] }} ({{ $rule['status'] }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>إنشاء كمسودة</button>
            </div>
        </form>
    </div>
</div>

{{-- ── مراجعة أرباح معتمدة ── --}}
<div class="modal-backdrop" id="modal-profit-revise" hidden>
    <div class="modal-card">
        <div class="modal-header">
            <h3>↺ إنشاء مراجعة أرباح</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="" method="POST">
            @csrf
            <div class="modal-body">
                <p class="hint" style="color:var(--text-muted);font-size:12px;margin-bottom:14px">
                    سيتم إنشاء نسخة منقحة بنفس لقطة رأس المال وقاعدة التوزيع، وسيتم وضع الفترة المعتمدة الحالية في حالة «مستبدلة».
                </p>
                <div class="om-field">
                    <label for="profit_revise_gross">إجمالي الربح الجديد (ر.س)</label>
                    <input id="profit_revise_gross" name="gross_profit" type="number" required min="0" step="0.01">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>إنشاء المراجعة</button>
            </div>
        </form>
    </div>
</div>
@endsection
