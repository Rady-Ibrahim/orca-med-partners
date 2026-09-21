@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">المشاريع المالية</p>
        <h1>الاستثمارات</h1>
    </div>
    <div class="page-actions">
        <button type="button" class="primary-button" data-modal-open="modal-investment-create">+ إضافة استثمار</button>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form class="page-filter-bar" method="GET" action="{{ route('admin.investments') }}">
    <label>
        اسم المشارك
        <input name="participant" value="{{ request('participant') }}" placeholder="أحمد...">
    </label>
    <label>
        الحالة
        <select name="status">
            <option value="">الكل</option>
            <option value="approved" @selected(request('status') === 'approved')>معتمد</option>
            <option value="pending" @selected(request('status') === 'pending')>قيد الانتظار</option>
            <option value="rejected" @selected(request('status') === 'rejected')>مرفوض</option>
        </select>
    </label>
    <label>
        من تاريخ
        <input type="date" name="date_from" value="{{ request('date_from') }}">
    </label>
    <label>
        إلى تاريخ
        <input type="date" name="date_to" value="{{ request('date_to') }}">
    </label>
    <div class="filter-actions">
        <button type="submit" class="primary-button">تطبيق</button>
        <a href="{{ route('admin.investments') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>↗</span><p>لا توجد بيانات استثمارات</p></div>
@else
    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>المشارك</th>
                        <th>المبلغ</th>
                        <th>الحالة</th>
                        <th>تاريخ الاستثمار</th>
                        <th>ملاحظات</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><strong>{{ $item['participant'] }}</strong></td>
                            <td class="numeric">{{ $item['amount'] }} ر.س</td>
                            <td><span class="status-badge status-{{ $item['status'] }}">{{ match($item['status']) { 'approved' => 'معتمد', 'pending' => 'قيد الانتظار', 'rejected' => 'مرفوض', default => $item['status'] } }}</span></td>
                            <td class="muted">{{ $item['date'] }}</td>
                            <td>{{ $item['notes'] }}</td>
                            <td>
                                <div class="row-actions">
                                    @if ($item['status'] === 'pending')
                                        <button type="button" class="action-approve" data-post
                                            data-url="{{ route('admin.investments.approve', $item['id']) }}"
                                            data-confirm="هل أنت متأكد من اعتماد استثمار «{{ $item['participant'] }}» بقيمة {{ $item['amount'] }} ر.س؟">اعتماد</button>
                                    @endif
                                    <button type="button" class="action-edit" data-fill-modal="modal-investment-edit"
                                        data-action-url="{{ route('admin.investments.update', $item['id']) }}"
                                        data-edit='@json($item["edit_payload"])'>تعديل</button>
                                    <button type="button" class="action-danger" data-post data-method="DELETE"
                                        data-url="{{ route('admin.investments.destroy', $item['id']) }}"
                                        data-confirm="سيتم حذف استثمار «{{ $item['participant'] }}» بقيمة {{ $item['amount'] }} ر.س. متابعة؟">حذف</button>
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

{{-- ── إضافة استثمار ── --}}
<div class="modal-backdrop" id="modal-investment-create" hidden>
    <div class="modal-card">
        <div class="modal-header">
            <h3>+ إضافة استثمار</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="{{ route('admin.investments.store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="om-field full">
                        <label for="inv_participant">المشارك</label>
                        <select id="inv_participant" name="participant_id" required>
                            <option value="">اختر مشارك...</option>
                            @foreach ($participants as $participant)
                                <option value="{{ $participant['id'] }}">{{ $participant['name'] }}</option>
                            @endforeach
                        </select>
                        @if ($participants->isEmpty())
                            <span class="om-error">لا يوجد مشاركون مسجلون بعد.</span>
                        @endif
                    </div>
                    <div class="om-field">
                        <label for="inv_amount">المبلغ (ر.س)</label>
                        <input id="inv_amount" name="amount" type="number" required min="0.01" step="0.01" dir="ltr" placeholder="0.00">
                    </div>
                    <div class="om-field">
                        <label for="inv_date">تاريخ الاستثمار</label>
                        <input id="inv_date" name="invested_at" type="date" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="om-field full">
                        <label for="inv_notes">ملاحظات</label>
                        <textarea id="inv_notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>إنشاء الاستثمار</button>
            </div>
        </form>
    </div>
</div>

{{-- ── تعديل استثمار ── --}}
<div class="modal-backdrop" id="modal-investment-edit" hidden>
    <div class="modal-card">
        <div class="modal-header">
            <h3>تعديل الاستثمار</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="" method="POST" novalidate>
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="om-field full">
                        <label for="inv_edit_participant">المشارك</label>
                        <select id="inv_edit_participant" name="participant_id" required>
                            <option value="">اختر مشارك...</option>
                            @foreach ($participants as $participant)
                                <option value="{{ $participant['id'] }}">{{ $participant['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="om-field">
                        <label for="inv_edit_amount">المبلغ (ر.س)</label>
                        <input id="inv_edit_amount" name="amount" type="number" min="0.01" step="0.01" dir="ltr" placeholder="0.00">
                    </div>
                    <div class="om-field">
                        <label for="inv_edit_date">تاريخ الاستثمار</label>
                        <input id="inv_edit_date" name="invested_at" type="date">
                    </div>
                    <div class="om-field full">
                        <label for="inv_edit_notes">ملاحظات</label>
                        <textarea id="inv_edit_notes" name="notes" rows="2"></textarea>
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
