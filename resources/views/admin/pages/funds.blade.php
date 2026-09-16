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
                                    <button type="button" class="action-neutral" data-modal-open="modal-fund-transaction"
                                        data-action-url="{{ route('admin.funds.transactions.store', $item['id']) }}"
                                        data-orca-label='{"#txnFundCaption":"{{ $item['name'] }}"}'>تسجيل حركة</button>
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
@endsection
