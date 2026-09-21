@extends('admin.pages.layout')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">الحصة الرأسمالية</p>
        <h1>رأس المال</h1>
    </div>
    <div class="page-actions">
        <button type="button" class="primary-button" data-modal-open="modal-capital-create">+ إنشاء لقطة رأس مال</button>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form class="page-filter-bar" method="GET" action="{{ route('admin.capital') }}">
    <label>
        الحالة
        <select name="status">
            <option value="">الكل</option>
            <option value="final" @selected(request('status') === 'final')>نهائي</option>
            <option value="draft" @selected(request('status') === 'draft')>مسودة</option>
        </select>
    </label>
    <div class="filter-actions">
        <button type="submit" class="primary-button">تطبيق</button>
        <a href="{{ route('admin.capital') }}" class="secondary-button">إعادة تعيين</a>
    </div>
</form>

@if ($items->isEmpty())
    <div class="empty-state panel"><span>▦</span><p>لا توجد لقطات رأسمالية</p></div>
@else
    <div class="panel table-panel compact-panel">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>تاريخ اللقطة</th>
                        <th>إجمالي رأس المال</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>{{ $item['date'] }}</td>
                            <td class="numeric">{{ $item['total'] }} ر.س</td>
                            <td><span class="status-badge status-{{ $item['status'] }}">{{ $item['status'] === 'final' ? 'نهائي' : $item['status'] }}</span></td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="action-history" data-history-modal
                                        data-history='@json($item["history"])'>سجل التعديلات</button>
                                    <button type="button" class="action-edit" data-fill-modal="modal-capital-edit"
                                        data-action-url="{{ route('admin.capital.update', $item['id']) }}"
                                        data-edit='@json($item["edit_payload"])'
                                        data-capital-values='@json($item["items"])'>تعديل</button>
                                    <button type="button" class="action-delete" data-post
                                        data-method="DELETE"
                                        data-url="{{ route('admin.capital.destroy', $item['id']) }}"
                                        data-confirm="هل أنت متأكد من حذف هذه اللقطة؟">حذف</button>
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

{{-- ── إنشاء لقطة رأس مال ── --}}
<div class="modal-backdrop" id="modal-capital-create" hidden>
    <div class="modal-card wide">
        <div class="modal-header">
            <h3>+ إنشاء لقطة رأس مال</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="{{ route('admin.capital.store') }}" method="POST">
            @csrf
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="om-field full">
                        <label for="capital_date">تاريخ اللقطة</label>
                        <input id="capital_date" name="snapshot_date" type="date" required value="{{ now()->toDateString() }}">
                        <span class="hint">يُشتق شهر وسنة اللقطة تلقائياً من التاريخ</span>
                    </div>
                    <div class="om-field full">
                        <label>الإجمالي المحسوب</label>
                        <div class="hint" id="capitalTotal" data-capital-total style="font-size:15px;font-weight:800;color:var(--text-head)">0.00</div>
                    </div>
                </div>

                <div class="capital-rows" style="margin-top:16px">
                    @foreach ($participants as $participant)
                        <div class="capital-row">
                            <strong>{{ $participant['name'] }}</strong>
                            <label class="hint" style="color:var(--text-faint)">رأس المال (ر.س)</label>
                            <input type="number" name="items[{{ $loop->index }}][participant_id]"
                                value="{{ $participant['id'] }}" hidden>
                            <input type="number" name="items[{{ $loop->index }}][capital]"
                                class="js-capital-input" min="0" step="0.01" placeholder="0.00" required>
                        </div>
                    @endforeach
                </div>
                @if ($participants->isEmpty())
                    <p class="hint" style="color:var(--text-faint);margin-top:12px">لا يوجد مشاركون لإنشاء اللقطة.</p>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>إنشاء اللقطة</button>
            </div>
        </form>
    </div>
</div>

{{-- ── تعديل لقطة رأس مال ── --}}
<div class="modal-backdrop" id="modal-capital-edit" hidden>
    <div class="modal-card wide">
        <div class="modal-header">
            <h3>تعديل لقطة رأس المال</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <form data-ajax-form action="" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-body">
                <div class="modal-grid">
                    <div class="om-field full">
                        <label for="capital_edit_date">تاريخ اللقطة</label>
                        <input id="capital_edit_date" name="snapshot_date" type="date" required>
                        <span class="hint">يُشتق شهر وسنة اللقطة تلقائياً من التاريخ</span>
                    </div>
                    <div class="om-field full">
                        <label>الإجمالي المحسوب</label>
                        <div class="hint" id="capitalTotalEdit" data-capital-total style="font-size:15px;font-weight:800;color:var(--text-head)">0.00</div>
                    </div>
                </div>

                <div class="capital-rows" style="margin-top:16px">
                    @foreach ($participants as $participant)
                        <div class="capital-row">
                            <strong>{{ $participant['name'] }}</strong>
                            <label class="hint" style="color:var(--text-faint)">رأس المال (ر.س)</label>
                            <input type="number" name="items[{{ $loop->index }}][participant_id]"
                                value="{{ $participant['id'] }}" hidden>
                            <input type="number" name="items[{{ $loop->index }}][capital]"
                                class="js-capital-input" data-capital-for="{{ $participant['id'] }}"
                                min="0" step="0.01" placeholder="0.00" required>
                        </div>
                    @endforeach
                </div>
                @if ($participants->isEmpty())
                    <p class="hint" style="color:var(--text-faint);margin-top:12px">لا يوجد مشاركون لتحرير اللقطة.</p>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="secondary-button" data-modal-close>إلغاء</button>
                <button type="submit" class="primary-button" data-submit>حفظ التعديلات</button>
            </div>
        </form>
    </div>
</div>

{{-- ── سجل التعديلات ── --}}
<div class="modal-backdrop" id="modal-capital-history" hidden>
    <div class="modal-card wide">
        <div class="modal-header">
            <h3>سجل تعديلات رأس المال</h3>
            <button type="button" class="modal-close" data-modal-close aria-label="إغلاق">×</button>
        </div>
        <div class="modal-body">
            <div class="history-list" data-history-list>
                <p class="hint" style="color:var(--text-faint)">لا توجد تعديلات على هذه اللقطة حتى الآن.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="secondary-button" data-modal-close>إغلاق</button>
        </div>
    </div>
</div>
@endsection
