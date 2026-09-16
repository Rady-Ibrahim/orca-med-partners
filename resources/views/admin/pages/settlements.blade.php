@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">التسويات السنوية</p>
        <h1>التسويات</h1>
    </div>
    <div class="page-actions">
        <button type="button" class="primary-button" data-modal-open="modal-settlement-create">+ إنشاء تسوية سنوية</button>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form class="page-filter-bar" method="GET" action="{{ route('admin.settlements') }}">
    <label>
        السنة
        <input type="number" name="year" value="{{ request('year') }}" placeholder="2025" min="2020" max="2099">
    </label>
    <label>
        الحالة
        <select name="status">
            <option value="">الكل</option>
            <option value="draft" @selected(request('status') === 'draft')>مسودة</option>
            <option value="approved" @selected(request('status') === 'approved')>معتمدة</option>
            <option value="partially_paid" @selected(request('status') === 'partially_paid')>مدفوعة جزئياً</option>
            <option value="paid" @selected(request('status') === 'paid')>مدفوعة بالكامل</option>
            <option value="cancelled" @selected(request('status') === 'cancelled')>ملغاة</option>
        </select>
    </label>
    <div class="filter-actions">
        <button type="submit" class="primary-button">تطبيق</button>
        <a href="{{ route('admin.settlements') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>◫</span><p>لا توجد تسويات</p></div>
@else
    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>السنة</th>
                        <th>عدد المشاركين</th>
                        <th>إجمالي الربح الموزع</th>
                        <th>المستحق</th>
                        <th>المدفوع</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><strong>{{ $item['year'] }}</strong></td>
                            <td>{{ $item['participants'] }}</td>
                            <td class="numeric">{{ $item['annual_profit'] }} ر.س</td>
                            <td class="numeric">{{ $item['amount_due'] }} ر.س</td>
                            <td class="numeric">{{ $item['paid_amount'] }} ر.س</td>
                            <td><span class="status-badge status-{{ $item['status'] }}">{{ match($item['status']) { 'draft' => 'مسودة', 'approved' => 'معتمدة', 'partially_paid' => 'مدفوعة جزئياً', 'paid' => 'مدفوعة بالكامل', 'cancelled' => 'ملغاة', 'superseded' => 'مستبدلة', default => $item['status'] } }}</span></td>
                            <td>
                                <div class="row-actions">
                                    @if ($item['status'] === 'draft')
                                        <button type="button" class="action-approve" data-post
                                            data-url="{{ route('admin.settlements.approve', $item['id']) }}"
                                            data-confirm="هل أنت متأكد من اعتماد تسوية سنة {{ $item['year'] }}؟">اعتماد</button>
                                    @endif
                                    @if (in_array($item['status'], ['approved', 'partially_paid'], true))
                                        <button type="button" class="action-neutral" data-modal-open="modal-settlement-payment"
                                            data-action-url="{{ route('admin.settlements.payment', $item['id']) }}"
                                            data-orca-label='{"#settlementYearCaption":"{{ $item['year'] }} — المستحق {{ $item['amount_due'] }} ر.س"}'>تسجيل دفعة</button>
                                    @endif
                                    @if ($item['status'] === 'approved')
                                        <button type="button" class="action-danger" data-post
                                            data-url="{{ route('admin.settlements.revise', $item['id']) }}"
                                            data-confirm="سيتم إنشاء تسوية معدلة لسنة {{ $item['year'] }} مع احتساب المدفوعات السابقة. متابعة؟">إنشاء تسوية معدلة</button>
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

{{-- ── إنشاء تسوية سنوية ── --}}
<div class="modal-backdrop" id="modal-settlement-create" hidden>
    <div class="modal-card">
        <div class="modal-header">
            <h3>+ إنشاء تسوية سنوية</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="{{ route('admin.settlements.store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <p class="hint" style="color:var(--text-muted);font-size:12px;margin-bottom:14px">
                    تُنشأ التسوية من الأرباح المعتمدة ومخصصات الصناديق للعام المحدد، وتُسجل التفاصيل (المستحق، الملاحظات) تلقائياً.
                </p>
                <div class="om-field">
                    <label for="settlement_year">السنة المستهدفة</label>
                    <input id="settlement_year" name="year" type="number" required min="2000" max="2100"
                        value="{{ now()->year }}">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>إنشاء</button>
            </div>
        </form>
    </div>
</div>

{{-- ── تسجيل دفعة تسوية ── --}}
<div class="modal-backdrop" id="modal-settlement-payment" hidden>
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <h3>تسجيل دفعة تسوية</h3>
                <span class="hint" id="settlementYearCaption" style="color:var(--text-faint);font-size:11px"></span>
            </div>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="" method="POST">
            @csrf
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="om-field">
                        <label for="pay_amount">المبلغ (ر.س)</label>
                        <input id="pay_amount" name="amount" type="number" required min="0.01" step="0.01" placeholder="0.00">
                    </div>
                    <div class="om-field">
                        <label for="pay_date">تاريخ الدفع</label>
                        <input id="pay_date" name="paid_at" type="date" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="om-field">
                        <label for="pay_source">مصدر الدفع</label>
                        <select id="pay_source" name="payment_source" required>
                            <option value="fund">صندوق</option>
                            <option value="bank">بنك</option>
                            <option value="cash">نقدًا</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    <div class="om-field">
                        <label for="pay_method">طريقة الدفع</label>
                        <input id="pay_method" name="payment_method" placeholder="تحويل بنكي...">
                    </div>
                    <div class="om-field full">
                        <label for="pay_reference">المرجع</label>
                        <input id="pay_reference" name="reference" dir="ltr" placeholder="TRF-2025-0001">
                    </div>
                    <div class="om-field full">
                        <label for="pay_description">الوصف</label>
                        <textarea id="pay_description" name="description" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>تسجيل الدفعة</button>
            </div>
        </form>
    </div>
</div>
@endsection
