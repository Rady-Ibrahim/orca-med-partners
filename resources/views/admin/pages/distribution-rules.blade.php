@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">قواعد التشغيل</p>
        <h1>قواعد التوزيع</h1>
    </div>
    <div class="page-actions">
        <button type="button" class="primary-button" data-modal-open="modal-rule-create">+ إنشاء قاعدة توزيع</button>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form class="page-filter-bar" method="GET" action="{{ route('admin.distribution-rules') }}">
    <label>
        الحالة
        <select name="status">
            <option value="">الكل</option>
            <option value="active" @selected(request('status') === 'active')>نشطة</option>
            <option value="inactive" @selected(request('status') === 'inactive')>غير نشطة</option>
        </select>
    </label>
    <label>
        السنة
        <input type="number" name="year" value="{{ request('year') }}" placeholder="2025" min="2020" max="2099">
    </label>
    <div class="filter-actions">
        <button type="submit" class="primary-button">تطبيق</button>
        <a href="{{ route('admin.distribution-rules') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>◎</span><p>لا توجد قواعد توزيع</p></div>
@else
    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>الوصف</th>
                        <th>سريانية من</th>
                        <th>سريانية حتى</th>
                        <th>الإدارة</th>
                        <th>الإهلاك</th>
                        <th>النمو</th>
                        <th>الحوافز</th>
                        <th>الموزع</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><strong>{{ $item['name'] }}</strong></td>
                            <td>{{ $item['effective_from'] }}</td>
                            <td>{{ $item['effective_to'] }}</td>
                            <td class="numeric">{{ $item['management'] }}</td>
                            <td class="numeric">{{ $item['depreciation'] }}</td>
                            <td class="numeric">{{ $item['growth'] }}</td>
                            <td class="numeric">{{ $item['incentive'] }}</td>
                            <td class="numeric">{{ $item['distributed'] }}</td>
                            <td><span class="status-badge status-{{ $item['status'] }}">{{ $item['status'] === 'active' ? 'نشطة' : $item['status'] }}</span></td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="action-edit" data-fill-modal="modal-rule-edit"
                                        data-action-url="{{ route('admin.distribution-rules.update', $item['id']) }}"
                                        data-edit='@json($item["edit_payload"])'>تعديل</button>
                                    <button type="button" class="action-delete" data-post
                                        data-method="DELETE"
                                        data-url="{{ route('admin.distribution-rules.destroy', $item['id']) }}"
                                        data-confirm="هل أنت متأكد من حذف قاعدة التوزيع؟">حذف</button>
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

{{-- ── إنشاء قاعدة توزيع ── --}}
<div class="modal-backdrop" id="modal-rule-create" hidden>
    <div class="modal-card wide">
        <div class="modal-header">
            <h3>+ إنشاء قاعدة توزيع</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="{{ route('admin.distribution-rules.store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="om-field">
                        <label for="rule_from">سارية من</label>
                        <input id="rule_from" name="effective_from" type="date" required value="{{ now()->toDateString() }}">
                    </div>
                    <div class="om-field">
                        <label for="rule_to">سارية حتى (اختياري)</label>
                        <input id="rule_to" name="effective_to" type="date">
                    </div>
                    <div class="om-field">
                        <label for="rule_mgmt">رسوم الإدارة %</label>
                        <input id="rule_mgmt" name="management_fee_rate" type="number" required min="0" max="100" step="0.01">
                    </div>
                    <div class="om-field">
                        <label for="rule_dep">إهلاك %</label>
                        <input id="rule_dep" name="depreciation_fund_rate" type="number" required min="0" max="100" step="0.01">
                    </div>
                    <div class="om-field">
                        <label for="rule_growth">النمو %</label>
                        <input id="rule_growth" name="growth_fund_rate" type="number" required min="0" max="100" step="0.01">
                    </div>
                    <div class="om-field">
                        <label for="rule_incentive">الحوافز %</label>
                        <input id="rule_incentive" name="incentive_fund_rate" type="number" required min="0" max="100" step="0.01">
                    </div>
                    <div class="om-field">
                        <label for="rule_distributed">الموزع للمشاركين %</label>
                        <input id="rule_distributed" name="distributed_share_rate" type="number" required min="0" max="100" step="0.01">
                    </div>
                    <div class="om-field">
                        <label for="rule_status">الحالة</label>
                        <select id="rule_status" name="status" required>
                            <option value="draft">مسودة</option>
                            <option value="active">نشطة</option>
                            <option value="locked">مقفلة</option>
                        </select>
                    </div>
                    <div class="om-field full">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                            <input type="checkbox" name="is_default" value="1" style="width:auto">
                            قاعدة افتراضية
                        </label>
                    </div>
                    <div class="om-field full">
                        <label for="rule_notes">الوصف / الملاحظات</label>
                        <textarea id="rule_notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <p class="hint" style="margin-top:10px;color:var(--text-faint)">أدخل النسب بالمئة (من 0 إلى 100) — يجب أن يكون مجموعها 100 تمامًا.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>إنشاء القاعدة</button>
            </div>
        </form>
    </div>
</div>

{{-- ── تعديل قاعدة توزيع ── --}}
<div class="modal-backdrop" id="modal-rule-edit" hidden>
    <div class="modal-card wide">
        <div class="modal-header">
            <h3>تعديل قاعدة التوزيع</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="om-field">
                        <label for="rule_edit_from">سارية من</label>
                        <input id="rule_edit_from" name="effective_from" type="date" required>
                    </div>
                    <div class="om-field">
                        <label for="rule_edit_to">سارية حتى (اختياري)</label>
                        <input id="rule_edit_to" name="effective_to" type="date">
                    </div>
                    <div class="om-field">
                        <label for="rule_edit_mgmt">رسوم الإدارة %</label>
                        <input id="rule_edit_mgmt" name="management_fee_rate" type="number" required min="0" max="100" step="0.01">
                    </div>
                    <div class="om-field">
                        <label for="rule_edit_dep">إهلاك %</label>
                        <input id="rule_edit_dep" name="depreciation_fund_rate" type="number" required min="0" max="100" step="0.01">
                    </div>
                    <div class="om-field">
                        <label for="rule_edit_growth">النمو %</label>
                        <input id="rule_edit_growth" name="growth_fund_rate" type="number" required min="0" max="100" step="0.01">
                    </div>
                    <div class="om-field">
                        <label for="rule_edit_incentive">الحوافز %</label>
                        <input id="rule_edit_incentive" name="incentive_fund_rate" type="number" required min="0" max="100" step="0.01">
                    </div>
                    <div class="om-field">
                        <label for="rule_edit_distributed">الموزع للمشاركين %</label>
                        <input id="rule_edit_distributed" name="distributed_share_rate" type="number" required min="0" max="100" step="0.01">
                    </div>
                    <div class="om-field">
                        <label for="rule_edit_status">الحالة</label>
                        <select id="rule_edit_status" name="status" required>
                            <option value="draft">مسودة</option>
                            <option value="active">نشطة</option>
                            <option value="locked">مقفلة</option>
                        </select>
                    </div>
                    <div class="om-field full">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                            <input type="checkbox" name="is_default" value="1" style="width:auto">
                            قاعدة افتراضية
                        </label>
                    </div>
                    <div class="om-field full">
                        <label for="rule_edit_notes">الوصف / الملاحظات</label>
                        <textarea id="rule_edit_notes" name="notes" rows="2"></textarea>
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
