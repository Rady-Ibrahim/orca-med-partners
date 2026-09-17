@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">الاحتياطيات المالية</p>
        <h1>الصناديق</h1>
    </div>
    <div class="page-actions">
        <button type="button" class="primary-button" data-modal-open="modal-fund-create">+ إنشاء صندوق</button>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form class="page-filter-bar" method="GET" action="{{ route('admin.funds') }}">
    <label>
        بحث (اسم / كود)
        <input name="search" value="{{ request('search') }}" placeholder="growth_fund...">
    </label>
    <label>
        الحالة
        <select name="status">
            <option value="">الكل</option>
            <option value="active" @selected(request('status') === 'active')>نشط</option>
            <option value="inactive" @selected(request('status') === 'inactive')>غير نشط</option>
        </select>
    </label>
    <div class="filter-actions">
        <button type="submit" class="primary-button">تطبيق</button>
        <a href="{{ route('admin.funds') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>◌</span><p>لا توجد بيانات صناديق</p></div>
@else
    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>اسم الصندوق</th>
                        <th>الكود</th>
                        <th>الرصيد الحالي</th>
                        <th>الحالة</th>
                        <th>عدد الحركات</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><strong>{{ $item['name'] }}</strong></td>
                            <td><code>{{ $item['code'] }}</code></td>
                            <td class="numeric">{{ $item['balance'] }} ر.س</td>
                            <td><span class="status-badge status-{{ $item['status'] }}">{{ $item['status'] === 'active' ? 'نشط' : 'غير نشط' }}</span></td>
                            <td>{{ $item['transactions'] }}</td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="action-edit" data-fill-modal="modal-fund-edit"
                                        data-action-url="{{ route('admin.funds.update', $item['id']) }}"
                                        data-edit='@json($item["edit_payload"])'>تعديل</button>
                                    @if (!$item['system_group'])
                                        <button type="button" class="action-delete" data-post
                                            data-method="DELETE"
                                            data-url="{{ route('admin.funds.destroy', $item['id']) }}"
                                            data-confirm="هل أنت متأكد من حذف هذا الصندوق؟">حذف</button>
                                    @endif
                                    <button type="button" class="action-neutral" data-modal-open="modal-fund-transaction"
                                        data-action-url="{{ route('admin.funds.transactions.store', $item['id']) }}"
                                        data-orca-label='{"#txnFundCaption":"{{ $item['name'] }}"}'>تسجيل حركة</button>
                                    <button type="button" class="action-neutral" data-modal-open="modal-fund-txn-list-{{ $item['id'] }}">الحركات</button>
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

{{-- ── إنشاء صندوق ── --}}
<div class="modal-backdrop" id="modal-fund-create" hidden>
    <div class="modal-card">
        <div class="modal-header">
            <h3>+ إنشاء صندوق</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="{{ route('admin.funds.store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="om-field">
                        <label for="fund_code">الكود</label>
                        <input id="fund_code" name="code" required dir="ltr" placeholder="growth_fund">
                        <span class="hint">كود فريد يُستخدم برمجياً</span>
                    </div>
                    <div class="om-field">
                        <label for="fund_name">الاسم</label>
                        <input id="fund_name" name="name" required>
                    </div>
                    <div class="om-field">
                        <label for="fund_status">الحالة</label>
                        <select id="fund_status" name="status">
                            <option value="active" selected>نشط</option>
                            <option value="inactive">غير نشط</option>
                        </select>
                    </div>
                    <div class="om-field full">
                        <label for="fund_description">الوصف</label>
                        <textarea id="fund_description" name="description" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>إنشاء</button>
            </div>
        </form>
    </div>
</div>

{{-- ── تعديل صندوق ── --}}
<div class="modal-backdrop" id="modal-fund-edit" hidden>
    <div class="modal-card">
        <div class="modal-header">
            <h3>تعديل صندوق</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="om-field">
                        <label for="fund_edit_code">الكود</label>
                        <input id="fund_edit_code" name="code" required dir="ltr" placeholder="growth_fund">
                        <span class="hint">كود فريد يُستخدم برمجياً</span>
                    </div>
                    <div class="om-field">
                        <label for="fund_edit_name">الاسم</label>
                        <input id="fund_edit_name" name="name" required>
                    </div>
                    <div class="om-field">
                        <label for="fund_edit_status">الحالة</label>
                        <select id="fund_edit_status" name="status">
                            <option value="active">نشط</option>
                            <option value="inactive">غير نشط</option>
                        </select>
                    </div>
                    <div class="om-field full">
                        <label for="fund_edit_description">الوصف</label>
                        <textarea id="fund_edit_description" name="description" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>

{{-- ── تسجيل حركة صندوق ── --}}
<div class="modal-backdrop" id="modal-fund-transaction" hidden>
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <h3>تسجيل حركة مالية</h3>
                <span class="hint" id="txnFundCaption" style="color:var(--text-faint);font-size:11px"></span>
            </div>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="" method="POST">
            @csrf
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="om-field">
                        <label for="txn_type">نوع الحركة</label>
                        <select id="txn_type" name="transaction_type" required>
                            <option value="deposit">إيداع</option>
                            <option value="withdrawal">سحب</option>
                            <option value="adjustment">تسوية</option>
                        </select>
                    </div>
                    <div class="om-field">
                        <label for="txn_amount">المبلغ (ر.س)</label>
                        <input id="txn_amount" name="amount" type="number" required min="0.01" step="0.01" placeholder="0.00">
                    </div>
                    <div class="om-field">
                        <label for="txn_date">التاريخ</label>
                        <input id="txn_date" name="transaction_date" type="date" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="om-field">
                        <label for="txn_reference">المرجع</label>
                        <input id="txn_reference" name="reference" dir="ltr" placeholder="INV-2025-001">
                    </div>
                    <div class="om-field full">
                        <label for="txn_description">الوصف</label>
                        <textarea id="txn_description" name="description" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>تسجيل الحركة</button>
            </div>
        </form>
    </div>
</div>

@foreach ($items as $item)
    {{-- ── حركات الصندوق ── --}}
    <div class="modal-backdrop" id="modal-fund-txn-list-{{ $item['id'] }}" hidden>
        <div class="modal-card wide">
            <div class="modal-header">
                <div>
                    <h3>حركات الصندوق — {{ $item['name'] }}</h3>
                    <span class="hint" style="color:var(--text-faint);font-size:11px">{{ $item['code'] }}</span>
                </div>
                <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
            </div>
            <div class="modal-body">
                @if (empty($item['transaction_items']))
                    <div class="empty-state panel"><span>◌</span><p>لا توجد حركات مسجلة على هذا الصندوق.</p></div>
                @else
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>النوع</th>
                                    <th>المبلغ</th>
                                    <th>الرصيد بعد الحركة</th>
                                    <th>التاريخ</th>
                                    <th>المرجع</th>
                                    <th>الوصف</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($item['transaction_items'] as $txn)
                                    <tr>
                                        <td>
                                            <span class="status-badge status-{{ $txn['type'] === 'deposit' ? 'approved' : ($txn['type'] === 'withdrawal' ? 'rejected' : 'pending') }}">
                                                {{ match($txn['type']) { 'deposit' => 'إيداع', 'withdrawal' => 'سحب', 'adjustment' => 'تسوية', default => $txn['type'] } }}
                                            </span>
                                        </td>
                                        <td class="numeric">{{ $txn['type'] === 'deposit' ? '+' : ($txn['type'] === 'withdrawal' ? '−' : '±') }} {{ $txn['amount'] }} ر.س</td>
                                        <td class="numeric muted">{{ $txn['resulting_balance'] }} ر.س</td>
                                        <td class="muted">{{ $txn['date'] }}</td>
                                        <td>{{ $txn['reference'] ?: '—' }}</td>
                                        <td>{{ $txn['description'] ?: $txn['notes'] ?: '—' }}</td>
                                        <td>
                                            <div class="row-actions">
                                                <button type="button" class="action-edit" data-fill-modal="modal-transaction-edit"
                                                    data-action-url="{{ route('admin.funds.transactions.update', [$item['id'], $txn['id']]) }}"
                                                    data-edit='@json($txn["edit_payload"])'>تعديل</button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إغلاق</button>
            </div>
        </div>
    </div>
@endforeach

{{-- ── تعديل حركة مالية (بيانات وصفية فقط) ── --}}
<div class="modal-backdrop" id="modal-transaction-edit" hidden>
    <div class="modal-card">
        <div class="modal-header">
            <h3>تعديل الحركة المالية</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <p class="hint" style="margin-bottom:12px">المبلغ والنوع والرصيد محميّان ولا يمكن تغييرهما؛ يمكن تعديل البيانات الوصفية فقط.</p>
                <div class="modal-grid">
                    <div class="om-field">
                        <label for="txn_ref">المرجع</label>
                        <input id="txn_ref" name="reference" dir="ltr" placeholder="INV-2025-001">
                    </div>
                    <div class="om-field">
                        <label for="txn_edit_date">التاريخ</label>
                        <input id="txn_edit_date" name="transaction_date" type="date">
                    </div>
                    <div class="om-field full">
                        <label for="txn_desc">الوصف</label>
                        <textarea id="txn_desc" name="description" rows="2"></textarea>
                    </div>
                    <div class="om-field full">
                        <label for="txn_notes">ملاحظات</label>
                        <textarea id="txn_notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>
@endsection
